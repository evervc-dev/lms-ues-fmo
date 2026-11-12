<?php

namespace App\Modules\Asistencia\Services;

use App\Models\User;
use App\Modules\Asistencia\Jobs\EvaluarAlertasAsistencia;
use App\Support\Alcance;
use App\Support\DbContext;
use App\Support\HojaCalculo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Asistencia de una sesión importada desde Excel o CSV (RF19, CP-RF19-02): una fila por estudiante, con su carné
 * y su estado. Solo se aplican las filas de estudiantes matriculados en el grupo; las demás se rechazan con su
 * motivo y se muestran en la vista previa antes de confirmar, como el reporte de Meet (RF21).
 */
class ImportacionAsistenciaService
{
    public const EXTENSIONES = ['csv', 'txt', 'xlsx'];

    /** Encabezados aceptados para cada columna, en minúscula y sin tildes. */
    private const COLUMNAS = [
        'carne' => ['carne', 'carnet', 'codigo', 'codigo de estudiante'],
        'estado' => ['estado', 'asistencia'],
    ];

    /** Estado escrito en el archivo (palabra o inicial, sin tildes) => estado del sistema. */
    private const ESTADOS = [
        'presente' => 'present', 'p' => 'present',
        'tarde' => 'late', 'tardanza' => 'late', 't' => 'late',
        'ausente' => 'absent', 'a' => 'absent',
        'justificada' => 'justified', 'justificado' => 'justified', 'j' => 'justified',
    ];

    public function __construct(private Alcance $alcance) {}

    /**
     * Lo que haría la importación, sin escribir nada.
     *
     * @return array{aplicables: list<array{matricula: int, carne: string, nombre: string, estado: string, anterior: ?string}>, rechazadas: list<array{fila: int, carne: string, motivo: string}>}
     */
    public function previsualizar(User $docente, int $seccionId, string $ulid, UploadedFile $archivo): array
    {
        $sesion = $this->sesion($docente, $seccionId, $ulid);
        $hoja = $this->leer($archivo);
        [$columnaCarne, $columnaEstado, $encabezado] = $this->columnas($hoja);

        $matriculados = DB::table('enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')
            ->join('student_profiles as p', 'p.user_id', '=', 'e.student_id')
            ->where('e.section_id', $seccionId)->where('e.status', 'active')
            ->get(['e.id', 'p.student_code', 'u.first_name', 'u.last_name'])
            ->keyBy(fn (object $m) => mb_strtoupper(trim((string) $m->student_code)));
        $anteriores = DB::table('attendance_records')->where('session_id', $sesion->id)->pluck('status', 'enrollment_id');

        $aplicables = [];
        $rechazadas = [];
        $vistos = [];
        foreach (array_slice($hoja, $encabezado + 1, null, true) as $indice => $fila) {
            $carne = mb_strtoupper(trim($fila[$columnaCarne] ?? ''));
            $estado = self::ESTADOS[self::normalizar($fila[$columnaEstado] ?? '')] ?? null;
            $motivo = match (true) {
                $carne === '' => __('Falta el carné.'),
                isset($vistos[$carne]) => __('El carné se repite en el archivo.'),
                ! $matriculados->has($carne) => __('No está matriculado en este grupo.'),
                $estado === null => __('Estado no válido: use presente, tarde, ausente o justificada.'),
                default => null,
            };
            $vistos[$carne] = true;

            if ($motivo !== null) {
                $rechazadas[] = ['fila' => $indice + 1, 'carne' => $carne === '' ? '—' : $carne, 'motivo' => $motivo];

                continue;
            }
            $matricula = $matriculados[$carne];
            $anterior = $anteriores[$matricula->id] ?? null;
            $aplicables[] = ['matricula' => (int) $matricula->id, 'carne' => $carne, 'nombre' => $matricula->last_name.', '.$matricula->first_name,
                'estado' => (string) $estado, 'anterior' => $anterior === null ? null : (string) $anterior];
        }

        return ['aplicables' => $aplicables, 'rechazadas' => $rechazadas];
    }

    /**
     * Aplica las filas válidas del archivo. Un estudiante sin registro queda registrado con el método «Importado»;
     * uno que ya tenía registro y cambia de estado queda como corrección, con el archivo como motivo (auditada).
     *
     * @return int Registros creados o corregidos.
     */
    public function importar(User $docente, int $seccionId, string $ulid, UploadedFile $archivo): int
    {
        $previa = $this->previsualizar($docente, $seccionId, $ulid, $archivo);
        $cambios = array_values(array_filter($previa['aplicables'], fn (array $f) => $f['anterior'] !== $f['estado']));
        if ($previa['aplicables'] === []) {
            throw ValidationException::withMessages(['archivo' => __('Ninguna fila del archivo se puede importar.')]);
        }
        $sesion = $this->sesion($docente, $seccionId, $ulid);
        $motivo = __('Importado desde el archivo «:a».', ['a' => $archivo->getClientOriginalName()]);

        DbContext::run(function () use ($cambios, $sesion, $seccionId, $docente, $motivo) {
            foreach ($cambios as $fila) {
                if ($fila['anterior'] === null) {
                    DB::table('attendance_records')->insert(['session_id' => $sesion->id, 'section_id' => $seccionId, 'enrollment_id' => $fila['matricula'],
                        'status' => $fila['estado'], 'method' => 'import', 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                } else {
                    DB::table('attendance_records')->where('session_id', $sesion->id)->where('enrollment_id', $fila['matricula'])
                        ->update(['status' => $fila['estado'], 'corrected_by' => $docente->id, 'correction_reason' => $motivo, 'updated_at' => now()]);
                }
            }
        }, 'attendance.imported', $motivo);

        if ($cambios !== []) {
            EvaluarAlertasAsistencia::dispatch($seccionId);
        }

        return count($cambios);
    }

    /**
     * La sesión de la sección, ya cerrada: mientras está abierta, los estudiantes siguen marcando con el QR.
     */
    private function sesion(User $docente, int $seccionId, string $ulid): object
    {
        if (! $this->alcance->puedeEnSeccion($docente, 'asistencia.corregir', $seccionId)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
        $sesion = DB::table('attendance_sessions')->where('section_id', $seccionId)->where('ulid', $ulid)->first() ?? abort(404);
        if ($sesion->status === 'open') {
            throw ValidationException::withMessages(['archivo' => __('Cierre la sesión antes de importar: mientras está abierta, los estudiantes siguen marcando con el QR.')]);
        }

        return $sesion;
    }

    /**
     * @return list<list<string>>
     */
    private function leer(UploadedFile $archivo): array
    {
        $extension = strtolower($archivo->getClientOriginalExtension() ?: (string) $archivo->guessExtension());
        if (! in_array($extension, self::EXTENSIONES, true)) {
            throw ValidationException::withMessages(['archivo' => __('Suba la asistencia en CSV o Excel (.xlsx).')]);
        }
        try {
            return HojaCalculo::leer((string) $archivo->getRealPath(), $extension);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['archivo' => __('No se pudo leer el archivo.')]);
        }
    }

    /**
     * Índices de las columnas de carné y estado, y la fila del encabezado: la primera de las diez primeras que
     * tenga las dos (puede haber un título encima, como en las exportaciones del sistema).
     *
     * @param  list<list<string>>  $hoja
     * @return array{0: int, 1: int, 2: int}
     */
    private function columnas(array $hoja): array
    {
        foreach (array_slice($hoja, 0, 10, true) as $indice => $fila) {
            $nombres = array_map(self::normalizar(...), $fila);
            $carne = array_find_key($nombres, fn (string $n) => in_array($n, self::COLUMNAS['carne'], true));
            $estado = array_find_key($nombres, fn (string $n) => in_array($n, self::COLUMNAS['estado'], true));
            if ($carne !== null && $estado !== null) {
                return [(int) $carne, (int) $estado, (int) $indice];
            }
        }

        throw ValidationException::withMessages(['archivo' => __('El archivo debe tener una columna «Carné» y otra «Estado».')]);
    }

    private static function normalizar(string $texto): string
    {
        return Str::ascii(mb_strtolower(trim($texto)));
    }
}
