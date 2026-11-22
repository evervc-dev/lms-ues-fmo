<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

/**
 * Resumen de cómo quedó la siembra, leído de la base (no de lo que cada seeder creyó crear): cuántas cuentas hay
 * por rol, quién tiene qué rol y qué asignación, y cuántos grupos y matrículas tiene el período en curso.
 * DatabaseSeeder lo imprime al final, con todas las cuentas.
 */
class ResumenSiembra
{
    private const ORDEN_ROLES = ['superadmin', 'administrador', 'jefe_departamento', 'coordinador', 'docente', 'soporte_tecnico', 'estudiante'];

    /**
     * @return array{
     *     porRol: list<array{0: string, 1: int}>,
     *     cuentas: list<array{0: string, 1: string, 2: string, 3: string}>,
     *     periodo: list<array{0: string, 1: string}>
     * }
     */
    public static function datos(): array
    {
        $roles = DB::table('model_has_roles as m')->join('roles as r', 'r.id', '=', 'm.role_id')->get(['m.model_id', 'r.name'])
            ->groupBy('model_id')->map(fn ($filas) => collect(self::ORDEN_ROLES)->intersect($filas->pluck('name'))->values()->all());

        $porRol = collect(self::ORDEN_ROLES)->map(fn (string $rol) => [__('roles.'.$rol), $roles->filter(fn ($r) => in_array($rol, $r, true))->count()])
            ->filter(fn ($fila) => $fila[1] > 0)->values()->all();
        $porRol[] = [__('Total de cuentas'), DB::table('users')->count()];

        $usuarios = DB::table('users')->get(['id', 'institutional_email', 'first_name', 'last_name', 'is_emergency']);

        $periodo = (int) DB::table('academic_periods')->where('status', 'active')->value('id');
        $jefaturas = DB::table('department_heads as h')->join('departments as d', 'd.id', '=', 'h.department_id')->whereNull('h.valid_to')->pluck('d.code', 'h.user_id');
        $coordinaciones = DB::table('career_coordinators as c')->join('careers as k', 'k.id', '=', 'c.career_id')->whereNull('c.valid_to')->pluck('k.code', 'c.user_id');
        $gruposDe = DB::table('teaching_assignments as t')->join('sections as s', 's.id', '=', 't.section_id')->whereNull('t.ended_at')->where('s.period_id', $periodo)
            ->select('t.teacher_id', DB::raw('count(*) as n'))->groupBy('t.teacher_id')->pluck('n', 'teacher_id');
        $ciclos = DB::table('student_careers')->where('status', 'active')->pluck('current_cycle', 'student_id');
        $materiasDe = DB::table('enrollments')->where('period_id', $periodo)->where('status', 'active')
            ->select('student_id', DB::raw('count(*) as n'))->groupBy('student_id')->pluck('n', 'student_id');

        $cuentas = $usuarios->map(function (object $u) use ($roles, $jefaturas, $coordinaciones, $gruposDe, $ciclos, $materiasDe) {
            $suyos = $roles->get($u->id, []);
            $asignacion = collect([
                isset($jefaturas[$u->id]) ? __('Jefe de :d', ['d' => $jefaturas[$u->id]]) : null,
                isset($coordinaciones[$u->id]) ? __('Coordina :c', ['c' => $coordinaciones[$u->id]]) : null,
                in_array('docente', $suyos, true) ? trans_choice('{0} sin grupos|{1} 1 grupo|[2,*] :count grupos', (int) ($gruposDe[$u->id] ?? 0)) : null,
                in_array('estudiante', $suyos, true) ? __('ciclo :c', ['c' => $ciclos[$u->id] ?? '—']).' · '.trans_choice('{0} sin materias|{1} 1 materia|[2,*] :count materias', (int) ($materiasDe[$u->id] ?? 0)) : null,
                $u->is_emergency ? __('cuenta de emergencia') : null,
                in_array('superadmin', $suyos, true) ? __('entra por /acceso-local') : null,
            ])->filter()->implode(' · ');

            return [
                implode(' + ', array_map(fn (string $r) => __('roles.'.$r), $suyos)) ?: __('(sin rol)'),
                $u->institutional_email,
                trim($u->first_name.' '.$u->last_name),
                $asignacion,
                array_search($suyos[0] ?? 'zz', self::ORDEN_ROLES, true),
            ];
        })->sortBy(fn ($fila) => [$fila[4] === false ? 99 : $fila[4], $fila[1]])->map(fn ($fila) => array_slice($fila, 0, 4))->values()->all();

        $grupos = DB::table('sections')->where('period_id', $periodo)->count();
        $conDocente = DB::table('teaching_assignments as t')->join('sections as s', 's.id', '=', 't.section_id')->whereNull('t.ended_at')->where('s.period_id', $periodo)->distinct()->count('t.section_id');

        return [
            'porRol' => $porRol,
            'cuentas' => $cuentas,
            'periodo' => [
                [__('Período en curso'), (string) (DB::table('academic_periods')->where('id', $periodo)->value('name') ?? '—')],
                [__('Grupos teóricos'), (string) $grupos],
                [__('Con docente / sin docente'), $conDocente.' / '.($grupos - $conDocente)],
                [__('Matrículas activas'), (string) DB::table('enrollments')->where('period_id', $periodo)->where('status', 'active')->count()],
                [__('Notas / registros de asistencia'), DB::table('grades')->count().' / '.DB::table('attendance_records')->count()],
            ],
        ];
    }
}
