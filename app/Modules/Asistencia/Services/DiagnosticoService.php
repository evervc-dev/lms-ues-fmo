<?php

namespace App\Modules\Asistencia\Services;

use App\Models\User;
use App\Support\Alcance;
use App\Support\Busqueda;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Diagnóstico de asistencia (RF20, maqueta 31): intentos de marcación con su motivo y la distancia al
 * área. Solo lectura para Soporte; nunca expone coordenadas.
 */
class DiagnosticoService
{
    public const MOTIVOS = [
        'ok' => 'Registrado', 'token_expired' => 'Código vencido', 'out_of_range' => 'Fuera del área',
        'invalid_device' => 'Dispositivo inválido', 'no_location' => 'Sin ubicación', 'scan_limit' => 'Sin intentos',
        'session_closed' => 'Sesión cerrada', 'not_enrolled' => 'No matriculado', 'already_recorded' => 'Ya registrado',
    ];

    /** Valor del filtro «motivo» que junta todos los rechazos (cualquier resultado distinto de `ok`). */
    public const SOLO_RECHAZOS = 'rechazos';

    /** Intentos que se muestran en la pantalla; la exportación llega más lejos. */
    public const MAXIMO_PANTALLA = 100;

    public const MAXIMO_EXPORTACION = 10000;

    public function __construct(private Alcance $alcance) {}

    /**
     * Intentos filtrados. Sin búsqueda ni fechas se muestran los de hoy; al buscar a un estudiante (correo,
     * carné o nombre) se ven sus últimos intentos de cualquier fecha, salvo que se fije un rango. La pantalla
     * arranca con el filtro «solo los rechazos».
     *
     * @param  array{buscar?: string, desde?: string, hasta?: string, motivo?: string}  $filtros
     * @return Collection<int, object>
     */
    public function intentos(User $actor, array $filtros = []): Collection
    {
        $this->alcance->exigir($actor, 'asistencia.diagnostico.ver');

        return $this->consulta($filtros)->limit(self::MAXIMO_PANTALLA)->get($this->columnas());
    }

    /**
     * Los mismos intentos del filtro, para descargar en CSV. Sin coordenadas: solo la distancia al área.
     *
     * @param  array{buscar?: string, desde?: string, hasta?: string, motivo?: string}  $filtros
     * @return array{columnas: array<int, string>, filas: array<int, array<int, string>>}
     */
    public function exportar(User $actor, array $filtros = []): array
    {
        $this->alcance->exigir($actor, 'asistencia.diagnostico.ver');
        $filas = $this->consulta($filtros)->limit(self::MAXIMO_EXPORTACION)->get($this->columnas());

        return [
            'columnas' => [__('Fecha y hora'), __('Estudiante'), __('Carné'), __('Correo'), __('Materia'), __('Grupo'), __('Fecha de la sesión'), __('Resultado'), __('Distancia (m)')],
            'filas' => $filas->map(fn (object $i) => [
                Carbon::parse($i->occurred_at)->format('d/m/Y H:i:s'),
                trim($i->first_name.' '.$i->last_name),
                (string) ($i->student_code ?? ''),
                $i->institutional_email,
                $i->materia,
                $i->grupo,
                Carbon::parse($i->session_date)->format('d/m/Y'),
                __(self::MOTIVOS[$i->result] ?? $i->result),
                $i->distance_m !== null ? (string) (int) $i->distance_m : '',
            ])->all(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function resumenDeHoy(User $actor): array
    {
        $this->alcance->exigir($actor, 'asistencia.diagnostico.ver');

        return DB::table('attendance_scan_attempts')->where('occurred_at', '>=', now()->startOfDay())
            ->groupBy('result')->selectRaw('result, count(*) as total')->pluck('total', 'result')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * @param  array{buscar?: string, desde?: string, hasta?: string, motivo?: string}  $filtros
     */
    private function consulta(array $filtros): Builder
    {
        $buscar = trim($filtros['buscar'] ?? '');
        $desde = $this->fecha($filtros['desde'] ?? '');
        $hasta = $this->fecha($filtros['hasta'] ?? '');
        $motivo = $filtros['motivo'] ?? '';
        $fragmento = Busqueda::fragmento($buscar);

        return DB::table('attendance_scan_attempts as a')
            ->join('attendance_sessions as s', 's.id', '=', 'a.session_id')
            ->join('sections', 'sections.id', '=', 's.section_id')
            ->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->join('users as u', 'u.id', '=', 'a.student_id')
            ->leftJoin('student_profiles as p', 'p.user_id', '=', 'u.id')
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->whereRaw("lower(u.institutional_email) like ? escape '\'", [$fragmento])
                ->orWhereRaw("lower(p.student_code) like ? escape '\'", [$fragmento])
                ->orWhere(Busqueda::porNombre($buscar, ['u.first_name', 'u.last_name']))))
            ->when($desde !== null, fn ($q) => $q->where('a.occurred_at', '>=', $desde->startOfDay()))
            ->when($hasta !== null, fn ($q) => $q->where('a.occurred_at', '<=', $hasta->endOfDay()))
            // Sin búsqueda ni rango, la pantalla se queda en lo ocurrido hoy.
            ->when($buscar === '' && $desde === null && $hasta === null, fn ($q) => $q->where('a.occurred_at', '>=', now()->startOfDay()))
            ->when($motivo === self::SOLO_RECHAZOS, fn ($q) => $q->where('a.result', '!=', 'ok'))
            ->when($motivo !== '' && $motivo !== self::SOLO_RECHAZOS, fn ($q) => $q->where('a.result', $motivo))
            ->orderByDesc('a.occurred_at');
    }

    /**
     * Fecha del filtro (AAAA-MM-DD) o null si viene vacía o con cualquier otro formato.
     */
    private function fecha(string $valor): ?Carbon
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($valor)) === 1 ? Carbon::parse(trim($valor)) : null;
    }

    /**
     * @return array<int, string>
     */
    private function columnas(): array
    {
        return ['a.id', 'a.result', 'a.distance_m', 'a.occurred_at', 's.session_date', 'subjects.code as materia', 'sections.code as grupo',
            'u.first_name', 'u.last_name', 'u.institutional_email', 'p.student_code'];
    }
}
