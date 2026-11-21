<?php

use App\Modules\Academica\Livewire\CargaAcademica;
use App\Modules\Academica\Services\CargaAcademicaService;
use App\Modules\Academica\Services\MatriculaService;
use App\Modules\Academica\Services\PeriodoService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Preparación del período siguiente (RF06, RF08, Ciclo de vida del período académico): se prepara mientras otro está
 * en curso, y cada etapa exige lo suyo. Corrección A5 de la revisión de flujos del 21/11/2026.
 */
beforeEach(function () {
    $this->e = escenario();
    $this->siguiente = DbContext::system(fn () => DB::table('academic_periods')->insertGetId(['name' => '2027-I', 'start_date' => '2027-02-01',
        'end_date' => '2027-06-18', 'status' => 'planned', 'preparation_stage' => 'planning', 'created_at' => now(), 'updated_at' => now()]));
    $this->periodos = app(PeriodoService::class);
});

it('CP-RF06-10 cada etapa de la preparación exige su condición antes de avanzar', function () {
    $e = $this->e;
    actingAs($e['administrador']);
    expect(fn () => $this->periodos->avanzarEtapa($e['administrador'], $this->siguiente))->toThrow(ValidationException::class, 'aún no tiene grupos');

    $grupo = DbContext::system(fn () => DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $this->siguiente, 'subject_id' => $e['prn'],
        'code' => '01', 'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]));
    expect($this->periodos->avanzarEtapa($e['administrador'], $this->siguiente))->toBe('schedule_definition');
    expect(fn () => $this->periodos->avanzarEtapa($e['administrador'], $this->siguiente))->toThrow(ValidationException::class, 'sin horario con aula');

    DbContext::system(fn () => DB::table('section_schedules')->insert(['section_id' => $grupo, 'period_id' => $this->siguiente, 'classroom_id' => $e['aula'], 'weekday' => 2,
        'start_time' => '07:00', 'end_time' => '08:40', 'created_at' => now(), 'updated_at' => now()]));
    expect($this->periodos->avanzarEtapa($e['administrador'], $this->siguiente))->toBe('teacher_assignment');
    expect(fn () => $this->periodos->avanzarEtapa($e['administrador'], $this->siguiente))->toThrow(ValidationException::class, 'sin docente');

    DbContext::system(fn () => DB::table('teaching_assignments')->insert(['section_id' => $grupo, 'teacher_id' => $e['docente']->id, 'is_responsible' => true,
        'started_at' => '2027-01-15', 'created_at' => now(), 'updated_at' => now()]));
    expect($this->periodos->avanzarEtapa($e['administrador'], $this->siguiente))->toBe('ready');
});

it('CP-RF06-11 no se activa un período «Listo» sin grupos', function () {
    $e = $this->e;
    DbContext::system(fn () => DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => 'closed']));
    DbContext::system(fn () => DB::table('academic_periods')->where('id', $this->siguiente)->update(['preparation_stage' => 'ready']));
    actingAs($e['administrador']);

    expect(fn () => $this->periodos->activar($e['administrador'], $this->siguiente))->toThrow(ValidationException::class, 'aún no tiene grupos');
    expect(DB::table('academic_periods')->where('id', $this->siguiente)->value('status'))->toBe('planned');
});

it('CP-RF08-13 el Jefe crea grupos del período en preparación mientras otro está en curso', function () {
    $e = $this->e;
    actingAs($e['jefe']);

    Livewire::test(CargaAcademica::class)->set('periodoId', $this->siguiente)->assertSee('2027-I')
        ->call('abrir', 'nueva-seccion')->set('asignaturaId', $e['prn'])->set('grupo', '01')->set('cupo', 35)
        ->call('crearSeccion')->assertHasNoErrors();

    expect(DB::table('sections')->where('period_id', $this->siguiente)->where('subject_id', $e['prn'])->exists())->toBeTrue();

    // Un período cerrado no se prepara.
    DbContext::system(fn () => DB::table('academic_periods')->where('id', $this->siguiente)->update(['status' => 'closed']));
    expect(fn () => app(CargaAcademicaService::class)->crearSeccion($e['jefe'], $this->siguiente, $e['prn'], '02', 30))->toThrow(ValidationException::class, 'en preparación');
});

it('CP-RF09-19 las claves se configuran también para el período en preparación', function () {
    $e = $this->e;
    actingAs($e['coordinador']);

    app(MatriculaService::class)->generarClave($e['coordinador'], $e['prn'], periodoId: $this->siguiente);

    expect(DB::table('enrollment_keys')->where('subject_id', $e['prn'])->where('period_id', $this->siguiente)->where('is_active', true)->exists())->toBeTrue()
        ->and(DB::table('enrollment_keys')->where('period_id', $e['periodo'])->exists())->toBeFalse();
});
