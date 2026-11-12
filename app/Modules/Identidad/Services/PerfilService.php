<?php

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Modules\Comunicacion\Services\NotificacionService;
use App\Support\DbContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mi perfil (docs/13 Layout/Layout base.md): datos de solo lectura, preferencias de aviso por correo
 * (RF23) y sesiones abiertas (RNF01). To do sobre lo propio (alcance P).
 */
class PerfilService
{
    /**
     * @return array<string, mixed>
     */
    public function datos(User $usuario): array
    {
        return [
            'estudiante' => DB::table('student_careers')->join('careers', 'careers.id', '=', 'student_careers.career_id')
                ->join('study_plans', 'study_plans.id', '=', 'student_careers.study_plan_id')
                ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'student_careers.student_id')
                ->where('student_careers.student_id', $usuario->id)->where('student_careers.status', 'active')
                ->first(['careers.name as carrera', 'study_plans.name as plan', 'student_careers.current_cycle as ciclo', 'student_profiles.student_code as carne']),
            'departamento' => DB::table('teacher_profiles')->join('departments', 'departments.id', '=', 'teacher_profiles.department_id')
                ->where('user_id', $usuario->id)->value('departments.name'),
            'coordina' => DB::table('career_coordinators')->join('careers', 'careers.id', '=', 'career_coordinators.career_id')
                ->where('user_id', $usuario->id)->whereNull('valid_to')->pluck('careers.name')->all(),
            'dirige' => DB::table('department_heads')->join('departments', 'departments.id', '=', 'department_heads.department_id')
                ->where('user_id', $usuario->id)->whereNull('valid_to')->pluck('departments.name')->all(),
        ];
    }

    /**
     * Avisos que el usuario puede recibir por su rol (config/notificaciones.php): a nadie se le ofrece elegir
     * sobre avisos que nunca le van a llegar. Los de correo obligatorio se muestran, pero no se pueden apagar.
     *
     * @return Collection<int, object{codigo: string, descripcion: string, correo: bool, obligatorio: bool}>
     */
    public function preferencias(User $usuario): Collection
    {
        $apagados = DB::table('notification_preferences')->where('user_id', $usuario->id)->where('mail_enabled', false)->pluck('event_code')->all();

        return DB::table('notification_events')->whereNotIn('code', $this->ajenos($usuario))->orderBy('code')->get(['code', 'description'])
            ->map(fn ($e) => (object) [
                'codigo' => $e->code,
                'descripcion' => $e->description,
                'correo' => ! in_array($e->code, $apagados, true),
                'obligatorio' => in_array($e->code, NotificacionService::CORREO_OBLIGATORIO, true),
            ]);
    }

    /**
     * Códigos de aviso que no son de ningún rol del usuario. Un evento sin audiencia declarada no se oculta.
     *
     * @return array<int, string>
     */
    private function ajenos(User $usuario): array
    {
        $roles = $usuario->getRoleNames()->all();

        /** @var array<string, array<int, string>> $audiencias */
        $audiencias = config('notificaciones.audiencias', []);

        return collect($audiencias)->reject(fn (array $audiencia) => array_intersect($audiencia, $roles) !== [])->keys()->all();
    }

    public function alternarCorreo(User $usuario, string $evento): bool
    {
        $usuario->checkPermissionTo('notificaciones.preferencias') || abort(403);
        abort_unless(DB::table('notification_events')->where('code', $evento)->exists(), 404);
        abort_if(in_array($evento, NotificacionService::CORREO_OBLIGATORIO, true), 403, __('Ese aviso siempre llega por correo.'));

        $actual = DB::table('notification_preferences')->where('user_id', $usuario->id)->where('event_code', $evento)->value('mail_enabled');
        $nuevo = $actual === null ? false : ! (bool) $actual;
        DbContext::run(fn () => DB::table('notification_preferences')->updateOrInsert(['user_id' => $usuario->id, 'event_code' => $evento], ['mail_enabled' => $nuevo]));

        return $nuevo;
    }

    /**
     * Sesiones abiertas (driver «database») y últimos accesos.
     *
     * @return array{sesiones: Collection<int, object>, accesos: Collection<int, object>, disponible: bool}
     */
    public function sesiones(User $usuario): array
    {
        $disponible = config('session.driver') === 'database';

        return [
            'disponible' => $disponible,
            'sesiones' => $disponible ? DB::table('sessions')->where('user_id', $usuario->id)->orderByDesc('last_activity')->get(['id', 'ip_address', 'user_agent', 'last_activity']) : collect(),
            'accesos' => DB::table('login_history')->where('user_id', $usuario->id)->orderByDesc('occurred_at')->limit(10)->get(['occurred_at', 'ip_address', 'user_agent', 'result']),
        ];
    }

    public function cerrarOtrasSesiones(User $usuario, string $sesionActual): int
    {
        $usuario->checkPermissionTo('perfil.sesiones.cerrar') || abort(403);

        return DB::table('sessions')->where('user_id', $usuario->id)->where('id', '!=', $sesionActual)->delete();
    }

    /**
     * @return array{activo: object|null, solicitud: object|null}
     */
    public function dispositivo(User $usuario): array
    {
        return [
            'activo' => DB::table('student_devices')->where('student_id', $usuario->id)->where('status', 'active')->first(['created_at', 'last_used_at']),
            'solicitud' => DB::table('device_change_requests')->where('student_id', $usuario->id)->orderByDesc('created_at')->first(['status', 'reason', 'created_at', 'resolved_at']),
        ];
    }
}
