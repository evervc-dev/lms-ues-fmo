<?php

use App\Support\DbContext;
use Database\Seeders\BaseSystemSeeder;
use Database\Seeders\EquipoSeeder;
use Database\Seeders\InstitutionSeeder;
use Database\Seeders\ResumenSiembra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Perfil de siembra «equipo» (LMS_SEED_PERFIL=equipo): las cuentas son las del equipo con su correo real, más
 * admin.dev y la cuenta de emergencia si el .env la define; roles al azar con semilla fija y sin datos de uso.
 */
function sembrarEquipo(int $semilla, array $emergencia = ['correo' => '', 'clave' => '']): array
{
    config(['lms.seed_semilla' => $semilla, 'lms.emergencia' => $emergencia]);
    DbContext::system(function (): void {
        (new BaseSystemSeeder)->run();
        (new InstitutionSeeder)->run();
        (new EquipoSeeder)->run();
    }, 'seed');

    return DB::table('model_has_roles as m')->join('roles as r', 'r.id', '=', 'm.role_id')->join('users as u', 'u.id', '=', 'm.model_id')
        ->orderBy('u.institutional_email')->orderBy('r.name')->get(['u.institutional_email', 'r.name'])
        ->groupBy('institutional_email')->map(fn ($filas) => $filas->pluck('name')->all())->all();
}

it('siembra solo las cuentas del equipo y admin.dev, con 1 jefe, 1 coordinador, 3 docentes y el resto estudiantes', function () {
    $roles = sembrarEquipo(2026);
    $equipo = require database_path('seeders/data/testers.php');

    expect(DB::table('users')->count())->toBe(count($equipo) + 1)
        ->and($roles['admin.dev@ues.edu.sv'])->toBe(['administrador'])
        ->and(collect($roles)->filter(fn ($r) => in_array('jefe_departamento', $r, true))->count())->toBe(1)
        ->and(collect($roles)->filter(fn ($r) => in_array('coordinador', $r, true))->count())->toBe(1)
        ->and(collect($roles)->filter(fn ($r) => in_array('docente', $r, true))->count())->toBe(5)
        ->and(collect($roles)->filter(fn ($r) => $r === ['estudiante'])->count())->toBe(count($equipo) - 5)
        // ADR-081: ningún estudiante tiene otro rol.
        ->and(collect($roles)->filter(fn ($r) => in_array('estudiante', $r, true) && count($r) > 1))->toBeEmpty()
        ->and(DB::table('academic_periods')->pluck('status', 'name')->all())->toBe(['2026-I' => 'closed', '2026-II' => 'active', '2027-I' => 'planned'])
        ->and(DB::table('grades')->count())->toBe(0)
        ->and(DB::table('attendance_records')->count())->toBe(0)
        ->and(DB::table('enrollments')->where('status', 'active')->count())->toBeGreaterThan(0)
        ->and(DB::table('student_profiles')->whereRaw('lower(student_code) || ? = (select institutional_email from users where users.id = student_profiles.user_id)', ['@ues.edu.sv'])->count())
        ->toBe(count($equipo) - 5);
});

it('con la misma semilla repite el reparto y con otra lo baraja', function () {
    $dues = array_keys(require database_path('seeders/data/testers.php'));

    expect(EquipoSeeder::reparto($dues, 2026))->toBe(EquipoSeeder::reparto($dues, 2026))
        ->and(EquipoSeeder::reparto($dues, 7))->not->toBe(EquipoSeeder::reparto($dues, 2026))
        ->and(collect(EquipoSeeder::reparto($dues, 2026))->flatten()->sort()->values()->all())->toBe(collect($dues)->sort()->values()->all());
});

it('no siembra la cuenta de emergencia si el entorno no trae su correo y su contraseña', function () {
    sembrarEquipo(2026);

    expect(DB::table('users')->where('is_emergency', true)->exists())->toBeFalse();
});

it('siembra la cuenta de emergencia con la contraseña del entorno', function () {
    sembrarEquipo(2026, ['correo' => 'emergencia@ues.edu.sv', 'clave' => 'una-clave-de-prueba']);

    $cuenta = DB::table('users')->where('is_emergency', true)->sole();
    expect($cuenta->institutional_email)->toBe('emergencia@ues.edu.sv')
        ->and(Hash::check('una-clave-de-prueba', $cuenta->password))->toBeTrue();
});

it('el resumen final muestra cada cuenta del equipo con su rol y su asignación, leído de la base', function () {
    sembrarEquipo(2026);
    $reparto = EquipoSeeder::reparto(array_keys(require database_path('seeders/data/testers.php')), 2026);
    $datos = ResumenSiembra::datos();
    $fila = fn (string $due) => collect($datos['cuentas'])->firstWhere(1, "{$due}@ues.edu.sv");

    expect($datos['detalleCompleto'])->toBeTrue()
        ->and($datos['cuentas'])->toHaveCount(21)
        ->and($fila($reparto['jefe'])[0])->toBe('Jefe de Departamento + Docente')
        ->and($fila($reparto['jefe'])[3])->toContain('Jefe de DIA')
        ->and($fila($reparto['coordinador'])[3])->toContain('Coordina ISI')
        ->and($fila($reparto['estudiantes'][0])[0])->toBe('Estudiante')
        ->and($fila($reparto['estudiantes'][0])[3])->toContain('ciclo')
        ->and(collect($datos['porRol'])->firstWhere(0, 'Total de cuentas')[1])->toBe(21)
        ->and(collect($datos['periodo'])->firstWhere(0, 'Notas / registros de asistencia')[1])->toBe('0 / 0');
});
