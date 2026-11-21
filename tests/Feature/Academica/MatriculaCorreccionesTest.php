<?php

use App\Modules\Academica\Services\MatriculaService;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Correcciones de la revisión de flujos del 21/11/2026 (matrícula, RF09, ADR-074, ADR-078).
 */
beforeEach(function () {
    $this->e = escenario();
    $this->matricula = app(MatriculaService::class);
    $e = $this->e;
    $this->destino = DbContext::system(fn () => DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
        'code' => '02', 'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]));
    $this->solicitud = DbContext::system(fn () => DB::table('section_change_requests')->insertGetId(['enrollment_id' => $e['matricula'], 'target_section_id' => $this->destino,
        'justification' => 'Trabajo por la mañana', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
});

it('CP-RF09-15 retirar rechaza las solicitudes de cambio pendientes y una solicitud vieja no revive la matrícula', function () {
    actingAs($this->e['coordinador']);
    $this->matricula->retirar($this->e['coordinador'], $this->e['matricula'], 'Retiro voluntario.');

    expect(DB::table('section_change_requests')->where('id', $this->solicitud)->value('status'))->toBe('rejected');

    // Aunque la solicitud siguiera pendiente, aprobarla no reactiva una matrícula retirada.
    DbContext::system(fn () => DB::table('section_change_requests')->where('id', $this->solicitud)->update(['status' => 'pending']));
    actingAs($this->e['jefe']);
    expect(fn () => $this->matricula->aprobarCambio($this->e['jefe'], $this->solicitud))->toThrow(ValidationException::class, 'ya no está activa');
    expect(DB::table('enrollments')->where('student_id', $this->e['estudiante']->id)->where('status', 'active')->exists())->toBeFalse();
});

it('CP-RF09-16 solo el Jefe del departamento resuelve el cambio de grupo (ADR-078)', function (string $rol) {
    actingAs($this->e[$rol]);

    expect(fn () => $this->matricula->aprobarCambio($this->e[$rol], $this->solicitud))->toThrow(AuthorizationException::class);
    expect(DB::table('section_change_requests')->where('id', $this->solicitud)->value('status'))->toBe('pending');
})->with(['coordinador', 'docente', 'soporte']);

it('CP-RF09-17 no matricula en un grupo de un período que no está en curso', function () {
    $e = $this->e;
    $planificado = DbContext::system(function () use ($e) {
        $periodo = DB::table('academic_periods')->insertGetId(['name' => '2027-I', 'start_date' => '2027-02-01', 'end_date' => '2027-06-18', 'status' => 'planned',
            'created_at' => now(), 'updated_at' => now()]);

        return DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $periodo, 'subject_id' => $e['mat'], 'code' => '01',
            'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
    });
    actingAs($e['administrador']);

    expect(fn () => $this->matricula->inscribir($e['administrador'], $e['estudiante']->id, $planificado))->toThrow(ValidationException::class, 'período en curso');
});

it('CP-RF09-18 no inscribe a un estudiante con la cuenta desactivada', function () {
    DbContext::system(fn () => DB::table('users')->where('id', $this->e['estudiante']->id)->update(['is_active' => false]));
    actingAs($this->e['coordinador']);

    expect(fn () => $this->matricula->inscribir($this->e['coordinador'], $this->e['estudiante']->id, $this->destino))->toThrow(ValidationException::class, 'no es estudiante activo');
});

it('CP-RF34-13 las carreras de un grupo son la suya, o las del plan si es anterior a ADR-074', function () {
    $e = $this->e;
    expect(Alcance::carrerasDeSeccion($e['seccion']))->toBe([$e['isi']]);

    $industrial = DbContext::system(function () use ($e) {
        $ind = DB::table('careers')->insertGetId(['department_id' => $e['dia'], 'code' => 'IND', 'name' => 'Ingeniería Industrial', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sections')->where('id', $this->destino)->update(['career_id' => $ind]);

        return $ind;
    });

    expect(Alcance::carrerasDeSeccion($this->destino))->toBe([$industrial]);
});
