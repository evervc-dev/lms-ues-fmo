<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\InsertsRows;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Personas de prueba (solo local y staging), con volumen realista de ISI:
 * 244 estudiantes (161 del Plan 98 y 83 del Plan 25), 30 docentes, 3 jefes, 4 coordinadores,
 * 2 de soporte, 1 administrador y las 6 cuentas del inicio de sesión de desarrollo (*.dev@ues.edu.sv).
 */
class DemoPeopleSeeder extends Seeder
{
    use InsertsRows;

    /** Ciclo (período impar 2026-II) => [plan, cantidad]. */
    private const STUDENTS = [1 => ['Plan 25', 50], 3 => ['Plan 25', 33], 5 => ['Plan 98', 40], 7 => ['Plan 98', 60], 9 => ['Plan 98', 61]];

    public function run(): void
    {
        $dept = DB::table('departments')->pluck('id', 'code');
        $careers = DB::table('careers')->pluck('id', 'code');
        $plans = DB::table('study_plans')->where('career_id', $careers['ISI'])->pluck('id', 'name');
        $roles = Role::query()->pluck('id', 'name');

        $assign = [];
        $roleOf = function (int $userId, string $role) use (&$assign, $roles): void {
            $assign[] = ['role_id' => $roles[$role], 'model_type' => User::class, 'model_id' => $userId];
        };

        // Cuentas de desarrollo (inicio de sesión de desarrollo, LMS_DEV_LOGIN).
        $dev = [];
        foreach ([
            'admin' => ['Administración', 'Desarrollo', 'administrador'],
            'jefe' => ['Jefatura', 'Desarrollo', 'jefe_departamento'],
            'coordinador' => ['Coordinación', 'Desarrollo', 'coordinador'],
            'docente' => ['Docente', 'Desarrollo', 'docente'],
            'estudiante' => ['Estudiante', 'Desarrollo', 'estudiante'],
            'soporte' => ['Soporte', 'Desarrollo', 'soporte_tecnico'],
        ] as $key => [$first, $last, $role]) {
            $dev[$key] = $this->user("{$key}.dev@ues.edu.sv", $first, $last);
            $roleOf($dev[$key], $role);
        }

        // Jefes de departamento: el de DIA es jefe.dev.
        $heads = ['DIA' => $dev['jefe'], 'DCNM' => $this->person(), 'DCE' => $this->person()];
        foreach ($heads as $code => $userId) {
            if ($userId !== $dev['jefe']) {
                $roleOf($userId, 'jefe_departamento');
            }
            DB::table('department_heads')->insert(['user_id' => $userId, 'department_id' => $dept[$code], 'valid_from' => '2025-01-01'] + $this->stamps());
            DB::table('teacher_profiles')->insert(['user_id' => $userId, 'department_id' => $dept[$code]] + $this->stamps());
        }

        // Coordinadores: el de ISI es coordinador.dev; hubo uno anterior (historial de vigencia).
        $previous = $this->person();
        $roleOf($previous, 'docente');
        DB::table('teacher_profiles')->insert(['user_id' => $previous, 'department_id' => $dept['DIA']] + $this->stamps());
        DB::table('career_coordinators')->insert(['user_id' => $previous, 'career_id' => $careers['ISI'], 'valid_from' => '2022-01-01', 'valid_to' => '2024-12-31'] + $this->stamps());
        foreach (['ISI' => $dev['coordinador'], 'ICI' => $this->person(), 'LMA' => $this->person(), 'LAE' => $this->person()] as $code => $userId) {
            if ($userId !== $dev['coordinador']) {
                $roleOf($userId, 'coordinador');
            }
            DB::table('career_coordinators')->insert(['user_id' => $userId, 'career_id' => $careers[$code], 'valid_from' => '2025-01-01'] + $this->stamps());
        }

        // 30 docentes: 20 de DIA (incluye docente.dev y el coordinador anterior), 6 de DCNM y 4 de DCE.
        $teachers = [$dev['docente']];
        DB::table('teacher_profiles')->insert(['user_id' => $dev['docente'], 'department_id' => $dept['DIA']] + $this->stamps());
        foreach (['DIA' => 18, 'DCNM' => 6, 'DCE' => 4] as $code => $count) {
            for ($i = 0; $i < $count; $i++) {
                $userId = $this->person();
                $teachers[] = $userId;
                $roleOf($userId, 'docente');
                DB::table('teacher_profiles')->insert(['user_id' => $userId, 'department_id' => $dept[$code]] + $this->stamps());
            }
        }
        // El coordinador de ISI también da clases.
        $roleOf($dev['coordinador'], 'docente');
        DB::table('teacher_profiles')->insert(['user_id' => $dev['coordinador'], 'department_id' => $dept['DIA']] + $this->stamps());

        $support = $this->person();
        $roleOf($support, 'soporte_tecnico');

        // Lote de importación del Coordinador (estudiantes del Plan 25).
        $batch = DB::table('import_batches')->insertGetId([
            'kind' => 'users', 'file_name' => 'nuevo_ingreso_2026.xlsx', 'uploaded_by' => $dev['coordinador'],
            'total_rows' => 86, 'valid_rows' => 83, 'error_rows' => 3, 'status' => 'finished', 'finished_at' => now()->subMonths(3),
        ] + $this->stamps(now()->subMonths(3)));
        $this->insertRows('import_batch_errors', [
            ['batch_id' => $batch, 'row_number' => 14, 'field' => 'correo', 'message' => 'El correo no es @ues.edu.sv', 'raw_data' => json_encode(['correo' => 'mario.p@gmail.com']), 'created_at' => now()->subMonths(3)],
            ['batch_id' => $batch, 'row_number' => 37, 'field' => 'correo', 'message' => 'El correo ya existe', 'raw_data' => json_encode(['correo' => 'gs21004@ues.edu.sv']), 'created_at' => now()->subMonths(3)],
            ['batch_id' => $batch, 'row_number' => 59, 'field' => 'carrera', 'message' => 'La carrera no existe', 'raw_data' => json_encode(['carrera' => 'ISX']), 'created_at' => now()->subMonths(3)],
        ]);

        // 244 estudiantes. estudiante.dev está en ciclo 5 (Plan 98).
        $profiles = [];
        $careerRows = [];
        $usedCodes = [];
        $isFirst = true;
        foreach (self::STUDENTS as $cycle => [$plan, $count]) {
            for ($i = 0; $i < $count; $i++) {
                if ($cycle === 5 && $isFirst) {
                    $userId = $dev['estudiante'];
                    $code = 'DE26001';
                    $isFirst = false;
                } else {
                    do {
                        $code = strtoupper(fake()->randomLetter().fake()->randomLetter()).fake()->numberBetween(10, 26).str_pad((string) fake()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT);
                    } while (isset($usedCodes[$code]));
                    $userId = $this->user(strtolower($code).'@ues.edu.sv', fake()->firstName(), fake()->lastName().' '.fake()->lastName(), $plan === 'Plan 25' ? $batch : null);
                }
                $usedCodes[$code] = true;
                if ($userId !== $dev['estudiante']) {
                    $roleOf($userId, 'estudiante');
                }
                $profiles[] = ['user_id' => $userId, 'student_code' => $code] + $this->stamps();
                $careerRows[] = ['student_id' => $userId, 'career_id' => $careers['ISI'], 'study_plan_id' => $plans[$plan], 'current_cycle' => $cycle, 'status' => 'active'] + $this->stamps();
            }
        }
        $this->insertRows('student_profiles', $profiles);
        $this->insertRows('student_careers', $careerRows);
        $this->insertRows('model_has_roles', $assign);

        // estudiante.dev queda sin teléfono: así registra el suyo para probar la asistencia con QR (RF20, RF27).
        $this->seedDevicesAndLogins(collect($profiles)->pluck('user_id')->reject(fn (int $id) => $id === $dev['estudiante'])->values()->all(), $support);
    }

    /**
     * @param  array<int, int>  $students
     */
    private function seedDevicesAndLogins(array $students, int $support): void
    {
        $devices = [];
        foreach ($students as $index => $studentId) {
            if ($index % 14 === 13) {
                continue; // ~7 % sin teléfono registrado (el 92.9 % tiene teléfono inteligente).
            }
            $devices[] = ['student_id' => $studentId, 'credential_id' => Str::random(43), 'status' => 'active', 'last_used_at' => now()->subDays(fake()->numberBetween(0, 6))] + $this->stamps(now()->subMonths(2));
        }
        // Cuatro teléfonos revocados (cambiados) antes del actual.
        foreach (array_slice($students, 0, 4) as $studentId) {
            $devices[] = ['student_id' => $studentId, 'credential_id' => Str::random(43), 'status' => 'revoked', 'revoked_at' => now()->subMonths(2)->subDay(), 'revoked_by' => $support] + $this->stamps(now()->subMonths(3));
        }
        $this->insertRows('student_devices', $devices);

        $events = DB::table('student_devices')->get(['id', 'status', 'created_at'])->flatMap(fn ($d) => array_filter([
            ['device_id' => $d->id, 'event_type' => 'registered', 'occurred_at' => $d->created_at],
            $d->status === 'revoked' ? ['device_id' => $d->id, 'event_type' => 'revoked', 'occurred_at' => now()->subMonths(2)] : null,
        ]))->values()->all();
        $this->insertRows('device_events', $events);

        $this->insertRows('device_change_requests', [
            ['student_id' => $students[10], 'reason' => 'Me robaron el teléfono en el bus.', 'status' => 'pending'] + $this->stamps(now()->subHours(5)),
            ['student_id' => $students[25], 'reason' => 'Cambié de teléfono porque se dañó la pantalla.', 'status' => 'pending'] + $this->stamps(now()->subDay()),
            ['student_id' => $students[40], 'reason' => 'Teléfono nuevo.', 'status' => 'rejected', 'resolved_by' => $support, 'resolved_at' => now()->subDays(3)] + $this->stamps(now()->subDays(4)),
            ['student_id' => $students[0], 'reason' => 'Perdí el teléfono.', 'status' => 'approved', 'resolved_by' => $support, 'resolved_at' => now()->subMonths(2)] + $this->stamps(now()->subMonths(2)->subDays(2)),
        ]);

        $logins = [];
        $users = DB::table('users')->limit(150)->get(['id', 'institutional_email']);
        foreach ($users as $user) {
            for ($d = 0; $d < 3; $d++) {
                $logins[] = ['user_id' => $user->id, 'attempted_email' => $user->institutional_email, 'ip_address' => '10.10.'.fake()->numberBetween(1, 30).'.'.fake()->numberBetween(2, 250),
                    'user_agent' => fake()->userAgent(), 'result' => 'success', 'failure_reason' => null, 'occurred_at' => now()->subDays($d * 2)->subMinutes(fake()->numberBetween(0, 600))];
            }
        }
        foreach ([['juan.perez@gmail.com', 'domain'], ['xx99999@ues.edu.sv', 'not_registered'], ['zz11111@ues.edu.sv', 'not_registered'], ['ana.lopez@hotmail.com', 'domain']] as [$email, $reason]) {
            $logins[] = ['user_id' => null, 'attempted_email' => $email, 'ip_address' => '10.10.5.'.fake()->numberBetween(2, 250), 'user_agent' => fake()->userAgent(), 'result' => 'failure', 'failure_reason' => $reason, 'occurred_at' => now()->subDays(fake()->numberBetween(0, 5))];
        }
        $this->insertRows('login_history', $logins);
    }

    private function user(string $email, string $first, string $last, ?int $batch = null): int
    {
        return DB::table('users')->insertGetId([
            'institutional_email' => $email, 'first_name' => $first, 'last_name' => $last,
            'is_active' => true, 'import_batch_id' => $batch, 'last_login_at' => now()->subDays(fake()->numberBetween(0, 10)),
        ] + $this->stamps(now()->subMonths(4)));
    }

    private function person(): int
    {
        $first = fake()->firstName();
        $last = fake()->lastName();
        $email = Str::slug($first.'.'.$last, '.').'.'.fake()->unique()->numberBetween(10, 999).'@ues.edu.sv';

        return $this->user($email, $first, $last.' '.fake()->lastName());
    }
}
