<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\InsertsRows;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Datos base del sistema, válidos en todos los entornos (también producción):
 * roles y permisos (Matriz RBAC), parámetros (system_settings) y eventos de notificación.
 * Es idempotente: se puede correr de nuevo sin duplicar.
 */
class BaseSystemSeeder extends Seeder
{
    use InsertsRows;

    public function run(): void
    {
        $this->seedRolesAndPermissions();
        $this->seedSettings();
        $this->seedNotificationEvents();
    }

    private function seedRolesAndPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** @var array{roles: array<int, string>, permissions: array<string, array<string, string>>} $rbac */
        $rbac = require database_path('seeders/data/rbac.php');

        $roles = collect($rbac['roles'])->mapWithKeys(fn (string $name) => [$name => Role::findOrCreate($name, 'web')]);

        foreach ($rbac['permissions'] as $permission => $scopes) {
            Permission::findOrCreate($permission, 'web');
        }

        // El DatabaseSeeder usa WithoutModelEvents: spatie no limpia su caché al crear permisos.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($roles as $name => $role) {
            $role->syncPermissions(collect($rbac['permissions'])->filter(fn (array $scopes) => isset($scopes[$name]))->keys()->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedSettings(): void
    {
        // Valores provisionales hasta tener el reglamento académico (ADR-030): is_confirmed = false.
        $settings = [
            'passing_score' => [6.0, 'Nota mínima de aprobación'],
            'grade_rounding' => [['decimals' => 1, 'mode' => 'half_up'], 'Redondeo de la nota final'],
            'missing_grade_policy' => ['zero', 'Una actividad sin nota cuenta como 0'],
            'min_attendance_percent' => [75, 'Asistencia mínima por cómputo'],
            'attendance_alert_thresholds' => [[85, 78], 'Umbrales de aviso preventivo y crítico'],
            'attendance_partial_weight' => [0.5, 'Peso de la tardanza en el porcentaje'],
            'justified_counts_as_absence' => [false, 'Las inasistencias justificadas no cuentan como falta'],
            'max_scans_per_student' => [3, 'Intentos de QR por estudiante y sesión'],
            'qr_rotation_seconds_default' => [10, 'Rotación del token QR en segundos'],
            'virtual_present_min_percent' => [70, 'Porcentaje mínimo del reporte de Meet para estar presente'],
            'trash_retention_days' => [30, 'Días en la papelera antes de purgar'],
            'qr_token_retention_days' => [7, 'Días que se conservan los tokens QR vencidos'],
            'group_change_weeks_default' => [3, 'Semanas para solicitar cambio de grupo'],
            'enrollment_deadline_weeks_default' => [2, 'Semanas desde el inicio del período para inscribir materias, si el departamento no fijó la fecha (ADR-051)'],
            'ciclo_ii_mes_inicio' => [8, 'Mes en que empieza el ciclo II del año; los meses anteriores son del ciclo I'],
            'grade_review_days' => [5, 'Días para solicitar la revisión de una nota publicada (RF35)'],
            'max_teachers_per_section' => [2, 'Máximo de docentes asignados a una sección (ADR-054, provisional hasta que lo fije la Jefatura)'],
            'max_weekly_hours_per_section' => [10, 'Máximo de horas semanales del horario de una sección (ADR-054, provisional hasta que lo fije la Jefatura)'],
            'quiz_review_delay_minutes' => [10, 'Minutos tras el cierre de un cuestionario para mostrar la corrección y aceptar solicitudes de revisión (RF16, RF35)'],
        ];

        foreach ($settings as $key => [$value, $description]) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value), 'is_confirmed' => false, 'description' => $description] + $this->stamps(),
            );
        }
    }

    private function seedNotificationEvents(): void
    {
        $events = [
            'assessment.created' => 'Nueva tarea o actividad',
            'resource.published' => 'Nuevo material de estudio',
            'video_session.upcoming' => 'Videoconferencia próxima',
            'schedule.changed' => 'Cambio de horario o aula',
            'grade.published' => 'Calificación publicada',
            'feedback.available' => 'Retroalimentación disponible',
            'deadline.upcoming' => 'Vencimiento cercano',
            'enrollment.changed' => 'Cambio de matrícula',
            'academic_alert.opened' => 'Alerta académica (nota o asistencia)',
            'grade_sheet.returned' => 'Acta devuelta con observaciones',
            'grade_sheet.published' => 'Acta por validar',
            'device_request.pending' => 'Nueva solicitud de cambio de teléfono',
            'device_request.resolved' => 'Solicitud de cambio de teléfono resuelta',
            'class.cancelled' => 'Clase cancelada o cambio de modalidad',
            'forum.reply' => 'Respuesta en un foro',
            'mass_message.sent' => 'Mensaje del docente',
            'grade_review.requested' => 'Solicitud de revisión de una nota',
            'grade_review.resolved' => 'Solicitud de revisión resuelta',
            'extension.granted' => 'Prórroga concedida',
            'period.reopened' => 'Período reabierto',
        ];

        foreach ($events as $code => $description) {
            DB::table('notification_events')->updateOrInsert(['code' => $code], ['description' => $description] + $this->stamps());
        }
    }
}
