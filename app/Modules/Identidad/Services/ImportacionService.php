<?php

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Support\Alcance;
use App\Support\DbContext;
use App\Support\HojaCalculo;
use App\Support\Nombre;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Carga masiva de usuarios (RF04, Flujo Carga masiva de usuarios). Columnas: carnet, nombres, apellidos,
 * correo, rol, carrera, plan, ciclo. El carné del estudiante sale del correo; la columna carnet es opcional y, si viene, debe
 * coincidir. El Coordinador solo importa estudiantes de su carrera; Soporte, estudiantes
 * de cualquier carrera (ADR-037); el Administrador, cualquier rol. Lee CSV y .xlsx con App\Support\HojaCalculo
 * (ADR-048), y la pantalla ofrece la plantilla ya llena con una carrera y un plan reales. El paso a la cola
 * «importaciones» sigue pendiente.
 */
class ImportacionService
{
    public const COLUMNAS = ['carnet', 'nombres', 'apellidos', 'correo', 'rol', 'carrera', 'plan', 'ciclo'];

    public function __construct(private Alcance $alcance, private JerarquiaService $jerarquia) {}

    /**
     * Plantilla del archivo a llenar: los encabezados y una fila de ejemplo **con una carrera y un plan reales
     * del alcance de quien la descarga**, para que el ejemplo pase la validación en vez de ser un relleno.
     * Si no alcanza ninguna carrera, la fila va con los marcadores entre llaves.
     *
     * @return array{columnas: array<int, string>, filas: array<int, array<int, string>>}
     */
    public function plantilla(User $actor): array
    {
        $alcance = $this->alcance->exigir($actor, 'usuarios.importar');

        $carrera = DB::table('careers')
            ->when($alcance === 'C', fn ($q) => $q->whereIn('careers.id', $this->alcance->carreras($actor)))
            ->join('study_plans', 'study_plans.career_id', '=', 'careers.id')
            ->orderBy('careers.code')->orderByDesc('study_plans.name')
            ->first(['careers.code', 'study_plans.name as plan']);

        return [
            'columnas' => self::COLUMNAS,
            'filas' => [[
                'll22017',
                'Luis Alberto',
                'López Ramírez',
                'll22017@ues.edu.sv',
                'estudiante',
                $carrera?->code ?? '{código de la carrera}',
                $carrera?->plan ?? '{nombre del plan}',
                '1',
            ]],
        ];
    }

    /**
     * Lee y valida el archivo sin guardar nada.
     *
     * @return array{validas: Collection<int, array<string, mixed>>, errores: Collection<int, array<string, mixed>>, duplicados: int}
     */
    public function vistaPrevia(User $actor, string $ruta, string $extension = 'csv'): array
    {
        $alcance = $this->alcance->exigir($actor, 'usuarios.importar');
        $controlTotal = $this->jerarquia->controlTotal($actor);
        $filas = $this->leer($ruta, $extension);
        $carreras = DB::table('careers')->when($alcance === 'C', fn ($q) => $q->whereIn('id', $this->alcance->carreras($actor)))->pluck('id', 'code')->mapWithKeys(fn ($id, $c) => [mb_strtoupper($c) => (int) $id]);
        $existentes = DB::table('users')->pluck('institutional_email')->map(fn ($c) => mb_strtolower($c))->flip();
        $carnesExistentes = DB::table('student_profiles')->pluck('student_code')->map(fn ($c) => mb_strtolower($c))->flip();
        $vistos = [];
        $carnesVistos = [];
        $validas = collect();
        $errores = collect();
        $duplicados = 0;

        foreach ($filas as $n => $f) {
            $correo = mb_strtolower(trim($f['correo'] ?? ''));
            $rol = mb_strtolower(trim($f['rol'] ?? 'estudiante')) ?: 'estudiante';
            $carrera = $carreras[mb_strtoupper(trim($f['carrera'] ?? ''))] ?? null;
            // El carné sale del correo (ll22017@ues.edu.sv → ll22017); la columna carnet solo cuenta si el correo no da uno.
            $carnet = mb_strtolower(trim($f['carnet'] ?? ''));
            $delCorreo = AsignacionService::carneDeCorreo($correo);
            $carne = $delCorreo ?? $carnet;
            $error = match (true) {
                ! str_ends_with($correo, '@ues.edu.sv') => ['correo', __('El correo no es @ues.edu.sv')],
                $existentes->has($correo) || isset($vistos[$correo]) => ['correo', __('El correo ya existe')],
                $rol === 'estudiante' && $delCorreo !== null && $carnet !== '' && $carnet !== $delCorreo => ['carnet', __('El carnet no coincide con el correo (:carne)', ['carne' => $delCorreo])],
                $rol === 'estudiante' && ($carne === '' || mb_strlen($carne) > 20) => ['carnet', __('Falta el carnet o tiene más de 20 caracteres')],
                $rol === 'estudiante' && ($carnesExistentes->has($carne) || isset($carnesVistos[$carne])) => ['carnet', __('El carnet ya existe')],
                ! in_array($rol, ['estudiante', 'docente', 'coordinador', 'jefe_departamento', 'administrador', 'soporte_tecnico'], true) => ['rol', __('El rol no existe')],
                ! $controlTotal && $rol !== 'estudiante' => ['rol', __('Solo puede importar estudiantes')],
                $rol === 'estudiante' && $carrera === null => ['carrera', __('La carrera no existe o no es suya')],
                $rol === 'estudiante' && ! DB::table('study_plans')->where('career_id', $carrera)->where('name', trim($f['plan'] ?? ''))->exists() => ['plan', __('El plan no existe en la carrera')],
                $rol === 'estudiante' && ((int) ($f['ciclo'] ?? 0) < 1 || (int) ($f['ciclo'] ?? 0) > AsignacionService::CICLO_MAXIMO) => ['ciclo', __('El ciclo debe estar entre 1 y :maximo', ['maximo' => AsignacionService::CICLO_MAXIMO])],
                blank($f['nombres'] ?? null) || blank($f['apellidos'] ?? null) => ['nombres', __('Faltan nombres o apellidos')],
                ($problema = Nombre::error((string) $f['nombres']) ?? Nombre::error((string) $f['apellidos'])) !== null => ['nombres', $problema],
                default => null,
            };
            if ($error) {
                in_array($error[1], [__('El correo ya existe'), __('El carnet ya existe')], true) && $duplicados++;
                $errores->push(['fila' => $n + 2, 'campo' => $error[0], 'mensaje' => $error[1], 'datos' => $f]);

                continue;
            }
            $vistos[$correo] = true;
            if ($rol === 'estudiante') {
                $carnesVistos[$carne] = true;
            }
            $validas->push($f + ['_correo' => $correo, '_rol' => $rol, '_carrera' => $carrera, '_carne' => $carne]);
        }

        return ['validas' => $validas, 'errores' => $errores, 'duplicados' => $duplicados];
    }

    /**
     * Crea las cuentas válidas y registra el lote con sus errores.
     */
    public function importar(User $actor, string $ruta, string $nombre): int
    {
        $previa = $this->vistaPrevia($actor, $ruta, pathinfo($nombre, PATHINFO_EXTENSION) ?: 'csv');

        return DbContext::run(function () use ($actor, $previa, $nombre) {
            $lote = DB::table('import_batches')->insertGetId(['kind' => 'users', 'file_name' => $nombre, 'uploaded_by' => $actor->id,
                'total_rows' => $previa['validas']->count() + $previa['errores']->count(), 'valid_rows' => $previa['validas']->count(), 'error_rows' => $previa['errores']->count(),
                'status' => 'finished', 'finished_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($previa['validas'] as $f) {
                $usuario = User::query()->create(['institutional_email' => $f['_correo'], 'first_name' => Nombre::normalizar($f['nombres']), 'last_name' => Nombre::normalizar($f['apellidos']), 'is_active' => true]);
                DB::table('users')->where('id', $usuario->id)->update(['import_batch_id' => $lote]);
                // ADR-053: la carga masiva pasa por la misma comprobación de roles compatibles que la gestión manual.
                $this->jerarquia->exigirRolesCompatibles([...$usuario->getRoleNames()->all(), $f['_rol']]);
                $usuario->assignRole($f['_rol']);
                if ($f['_rol'] === 'estudiante') {
                    DB::table('student_profiles')->insert(['user_id' => $usuario->id, 'student_code' => $f['_carne'], 'created_at' => now(), 'updated_at' => now()]);
                    DB::table('student_careers')->insert(['student_id' => $usuario->id, 'career_id' => $f['_carrera'],
                        'study_plan_id' => DB::table('study_plans')->where('career_id', $f['_carrera'])->where('name', trim($f['plan']))->value('id'),
                        'current_cycle' => (int) $f['ciclo'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            foreach ($previa['errores'] as $e) {
                DB::table('import_batch_errors')->insert(['batch_id' => $lote, 'row_number' => $e['fila'], 'field' => $e['campo'], 'message' => $e['mensaje'], 'raw_data' => json_encode($e['datos']), 'created_at' => now()]);
            }

            return $lote;
        }, 'users.imported');
    }

    /**
     * @return array{lote: object, errores: Collection<int, object>}
     */
    public function lote(User $actor, int $id): array
    {
        $alcance = $this->alcance->exigir($actor, 'usuarios.importar');
        $lote = DB::table('import_batches as b')->join('users as u', 'u.id', '=', 'b.uploaded_by')->where('b.id', $id)
            ->when($alcance !== 'G', fn ($q) => $q->where('b.uploaded_by', $actor->id))
            ->first(['b.*', DB::raw("u.first_name || ' ' || u.last_name as subio")]) ?? abort(404);

        return ['lote' => $lote, 'errores' => DB::table('import_batch_errors')->where('batch_id', $id)->orderBy('row_number')->get()];
    }

    /**
     * @return Collection<int, object>
     */
    public function lotes(User $actor): Collection
    {
        $alcance = $this->alcance->exigir($actor, 'usuarios.importar');

        return DB::table('import_batches')->when($alcance !== 'G', fn ($q) => $q->where('uploaded_by', $actor->id))->orderByDesc('created_at')->limit(20)->get();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function leer(string $ruta, string $extension): array
    {
        try {
            $filas = HojaCalculo::leer($ruta, $extension);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['archivo' => __('No se pudo leer el archivo.')]);
        }

        $primera = array_shift($filas) ?? [];
        $encabezado = array_map(fn (string $c) => mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', $c))), $primera);
        if (array_diff(['correo', 'nombres', 'apellidos'], $encabezado)) {
            throw ValidationException::withMessages(['archivo' => __('Faltan columnas. Use: :c.', ['c' => implode(', ', self::COLUMNAS)])]);
        }

        return array_map(
            fn (array $linea) => array_combine($encabezado, array_pad(array_slice($linea, 0, count($encabezado)), count($encabezado), '')),
            $filas,
        );
    }
}
