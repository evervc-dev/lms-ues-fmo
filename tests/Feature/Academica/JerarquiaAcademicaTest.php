<?php

use App\Modules\Academica\Livewire\CargaAcademica;
use App\Modules\Academica\Services\CargaAcademicaService;
use App\Modules\Academica\Services\MatriculaService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Carga académica y matrícula según la jerarquía de roles (ADR-037): el Jefe asigna docentes y cambia el cupo
 * en su departamento; Soporte matricula en cualquier sección.
 */
function docenteDe(int $departamento): int
{
    $docente = usuarioCon('docente');
    DbContext::system(fn () => DB::table('teacher_profiles')->insert(['user_id' => $docente->id, 'department_id' => $departamento, 'created_at' => now(), 'updated_at' => now()]));

    return $docente->id;
}

it('CP-RF08-04 lets the head assign teachers only to sections of their department', function () {
    $e = escenario();
    $docente = docenteDe($e['dia']);
    $carga = app(CargaAcademicaService::class);
    actingAs($e['jefe']);

    $carga->asignarDocente($e['jefe'], $e['seccion'], $docente, false);

    $this->assertDatabaseHas('teaching_assignments', ['section_id' => $e['seccion'], 'teacher_id' => $docente, 'ended_at' => null]);
    expect(fn () => $carga->asignarDocente($e['jefe'], $e['otra'], $docente))->toThrow(AuthorizationException::class);
    $this->assertDatabaseMissing('teaching_assignments', ['section_id' => $e['otra'], 'teacher_id' => $docente]);
});

it('CP-RF08-04 shows the head the sections of their department and lets them create one only there', function () {
    $e = escenario();
    $carga = app(CargaAcademicaService::class);

    actingAs($e['jefe'])->get(route('carga-academica'))->assertOk()->assertSeeText(['PRN115', 'Asignar docente', 'Cupo', 'Nuevo grupo teórico'])->assertDontSeeText('MAT115');
    expect($carga->asignaturas($e['jefe'])->pluck('code')->all())->toBe(['PRN115'])
        ->and(fn () => $carga->crearSeccion($e['jefe'], $e['periodo'], $e['mat'], '02', 40))->toThrow(AuthorizationException::class);

    $grupo = $carga->crearSeccion($e['jefe'], $e['periodo'], $e['prn'], '02', 35);
    expect((int) DB::table('sections')->where('id', $grupo)->value('max_capacity'))->toBe(35);
});

it('CP-RF08-05 lets the head change the capacity of a section of their department', function () {
    $e = escenario();
    $carga = app(CargaAcademicaService::class);
    actingAs($e['jefe']);

    $carga->cambiarCupo($e['jefe'], $e['seccion'], 55);

    expect(DB::table('sections')->where('id', $e['seccion'])->value('max_capacity'))->toBe(55)
        ->and(fn () => $carga->cambiarCupo($e['jefe'], $e['otra'], 55))->toThrow(AuthorizationException::class)
        ->and(DB::table('sections')->where('id', $e['otra'])->value('max_capacity'))->toBe(40);
});

it('CP-RF08-05 rejects a capacity below the active enrollments or the section minimum', function () {
    $e = escenario();
    $estudiantes = [usuarioCon('estudiante'), usuarioCon('estudiante')];
    DbContext::system(function () use ($e, $estudiantes) {
        DB::table('sections')->where('id', $e['seccion'])->update(['min_capacity' => 5]);
        foreach ($estudiantes as $estudiante) {
            DB::table('enrollments')->insert(['student_id' => $estudiante->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
                'status' => 'active', 'enrolled_via' => 'admin', 'enrolled_by' => $e['administrador']->id, 'created_at' => now(), 'updated_at' => now()]);
        }
    });
    actingAs($e['jefe']);

    Livewire::test(CargaAcademica::class)->call('abrirCupo', $e['seccion'])->set('cupoNuevo', 4)->call('cambiarCupo')->assertHasErrors('cupoNuevo');
    expect(fn () => app(CargaAcademicaService::class)->cambiarCupo($e['jefe'], $e['seccion'], 2))
        ->toThrow(ValidationException::class, 'Hay 3 estudiantes inscritos: el cupo no puede ser menor.')
        ->and(DB::table('sections')->where('id', $e['seccion'])->value('max_capacity'))->toBe(40);
});

it('CP-RF08-05 does not let the coordinator change the capacity', function () {
    $e = escenario();
    actingAs($e['coordinador']);

    expect(fn () => app(CargaAcademicaService::class)->cambiarCupo($e['coordinador'], $e['seccion'], 55))->toThrow(AuthorizationException::class);
});

it('CP-RF09-11 lets technical support enroll a student in any section', function () {
    $e = escenario();
    // MAT115 entra al plan ISI: la matrícula solo acepta asignaturas del plan de la carrera (CP-RF09-14).
    DbContext::system(fn () => DB::table('career_subjects')->insert(['study_plan_id' => $e['plan'], 'subject_id' => $e['mat'], 'cycle' => 1]));
    actingAs($e['soporte']);

    $matricula = app(MatriculaService::class)->inscribir($e['soporte'], $e['estudiante']->id, $e['otra']);

    $this->assertDatabaseHas('enrollments', ['id' => $matricula, 'student_id' => $e['estudiante']->id, 'section_id' => $e['otra'], 'enrolled_by' => $e['soporte']->id, 'status' => 'active']);
});

it('CP-RF08-04 does not let the head remove teachers or schedule sections outside their reach', function () {
    $e = escenario();
    $ajeno = docenteDe($e['dcnm']);
    DbContext::system(fn () => DB::table('teaching_assignments')->insert(['section_id' => $e['otra'], 'teacher_id' => $ajeno, 'is_responsible' => true, 'started_at' => '2026-07-01', 'created_at' => now(), 'updated_at' => now()]));
    $carga = app(CargaAcademicaService::class);
    actingAs($e['jefe']);

    expect(fn () => $carga->quitarDocente($e['jefe'], $e['otra'], $ajeno))->toThrow(AuthorizationException::class)
        ->and(fn () => $carga->agregarHorario($e['jefe'], $e['otra'], 2, '09:00', '10:40', null))->toThrow(AuthorizationException::class);
    $this->assertDatabaseHas('teaching_assignments', ['section_id' => $e['otra'], 'teacher_id' => $ajeno, 'ended_at' => null]);
    expect(DB::table('section_schedules')->where('section_id', $e['otra'])->count())->toBe(0);
});

it('CP-RF08-05 rejects a capacity outside 1 to 200 in the service', function () {
    $e = escenario();
    actingAs($e['jefe']);

    expect(fn () => app(CargaAcademicaService::class)->cambiarCupo($e['jefe'], $e['seccion'], 201))->toThrow(ValidationException::class, 'El cupo debe estar entre 1 y 200.');
});

/* ------------------------------------------------------- CP-RF08-06 (topes de la carga académica, ADR-054) */

it('CP-RF08-06 rechaza el tercer docente de la sección nombrando el tope y los docentes actuales', function () {
    $e = escenario();
    $carga = app(CargaAcademicaService::class);
    actingAs($e['administrador']);

    // La sección ya trae un docente: el segundo entra y el tercero pasa el tope de system_settings (2).
    $carga->asignarDocente($e['administrador'], $e['seccion'], docenteDe($e['dia']), false);

    expect(fn () => $carga->asignarDocente($e['administrador'], $e['seccion'], docenteDe($e['dia']), false))
        ->toThrow(ValidationException::class, 'El grupo teórico ya tiene 2 docentes y el máximo es 2.');
    expect(DB::table('teaching_assignments')->where('section_id', $e['seccion'])->whereNull('ended_at')->count())->toBe(2);
});

it('CP-RF08-06 rechaza el bloque de horario que pasaría las 10 horas semanales de la sección', function () {
    Notification::fake();
    $e = escenario();
    $carga = app(CargaAcademicaService::class);
    actingAs($e['administrador']);

    // La sección parte de 1 h 40; se le suman 5 h y 3 h en días libres y sin aula: 9 h 40.
    $carga->agregarHorario($e['administrador'], $e['seccion'], 2, '07:00', '12:00', null);
    $carga->agregarHorario($e['administrador'], $e['seccion'], 3, '07:00', '10:00', null);

    expect(fn () => $carga->agregarHorario($e['administrador'], $e['seccion'], 4, '07:00', '08:00', null))
        ->toThrow(ValidationException::class, 'El grupo teórico ya suma 9.7 h semanales y con este bloque pasaría el máximo de 10 h.');
    expect(DB::table('section_schedules')->where('section_id', $e['seccion'])->count())->toBe(3)
        ->and(DB::table('section_schedules')->where('section_id', $e['seccion'])->where('weekday', 4)->exists())->toBeFalse();
});

it('CP-RF08-07 el Jefe arma un grupo teórico de su departamento: lo crea con su cupo, le pone horario y aula, y cambia el cupo', function () {
    $e = escenario();
    $carga = app(CargaAcademicaService::class);
    actingAs($e['jefe']);

    $grupo = $carga->crearSeccion($e['jefe'], $e['periodo'], $e['prn'], '03', 30);
    $carga->agregarHorario($e['jefe'], $grupo, 2, '09:00', '10:40', $e['aula']);
    $carga->cambiarCupo($e['jefe'], $grupo, 45);

    expect((int) DB::table('sections')->where('id', $grupo)->value('max_capacity'))->toBe(45)
        ->and(DB::table('section_schedules')->where('section_id', $grupo)->where('classroom_id', $e['aula'])->where('weekday', 2)->exists())->toBeTrue();
});

it('CP-RF08-08 el Jefe no crea ni programa grupos teóricos de otro departamento', function () {
    $e = escenario();
    $carga = app(CargaAcademicaService::class);

    expect(fn () => $carga->crearSeccion($e['jefe'], $e['periodo'], $e['mat'], '02', 40))->toThrow(AuthorizationException::class)
        ->and(fn () => $carga->agregarHorario($e['jefe'], $e['otra'], 3, '09:00', '10:40', $e['aula']))->toThrow(AuthorizationException::class)
        ->and(fn () => $carga->cambiarCupo($e['jefe'], $e['otra'], 20))->toThrow(AuthorizationException::class);

    expect(DB::table('sections')->where('subject_id', $e['mat'])->count())->toBe(1);
});

it('CP-RF08-09 el Jefe crea el grupo teórico eligiendo la carrera y una materia de esa carrera, sin elegir departamento', function () {
    $e = escenario();
    actingAs($e['jefe']);

    Livewire::test(CargaAcademica::class)->call('abrir', 'nueva-seccion')
        ->assertViewHas('eligeDepartamento', false)
        ->assertSet('carreraGrupo', $e['isi']) // DIA tiene una sola carrera: se elige sola.
        ->assertViewHas('asignaturas', fn ($asignaturas) => $asignaturas->pluck('code')->all() === ['PRN115'])
        ->set('asignaturaId', $e['prn'])->set('grupo', '02')->set('cupo', 35)
        ->call('crearSeccion')->assertHasNoErrors();

    expect(DB::table('sections')->where('subject_id', $e['prn'])->where('code', '02')->value('max_capacity'))->toBe(35)
        ->and(fn () => app(CargaAcademicaService::class)->crearSeccion($e['jefe'], $e['periodo'], $e['prn'], '03', 30, carreraId: DbContext::system(fn () => DB::table('careers')->insertGetId(['department_id' => $e['dcnm'], 'code' => 'LMA', 'name' => 'Matemática', 'created_at' => now(), 'updated_at' => now()]))))
        ->toThrow(AuthorizationException::class);
});

it('CP-RF08-10 el Administrador elige departamento, carrera y una materia del plan de esa carrera', function () {
    $e = escenario();
    actingAs($e['administrador']);

    $pantalla = Livewire::test(CargaAcademica::class)->call('abrir', 'nueva-seccion')
        ->assertViewHas('eligeDepartamento', true)
        ->assertViewHas('carrerasGrupo', fn ($carreras) => $carreras->isEmpty())
        ->set('carreraGrupo', $e['isi'])->set('asignaturaId', $e['prn'])->call('crearSeccion')->assertHasErrors(['departamentoGrupo' => 'required'])
        ->set('departamentoGrupo', $e['dia'])
        ->assertSet('carreraGrupo', $e['isi'])
        ->assertViewHas('carrerasGrupo', fn ($carreras) => $carreras->pluck('id')->all() === [$e['isi']]);

    // MAT115 no está en el plan de ISI: no se ofrece y el Service la rechaza.
    $pantalla->set('asignaturaId', $e['mat'])->set('grupo', '05')->call('crearSeccion')->assertHasErrors(['asignaturaId']);
    $pantalla->set('asignaturaId', $e['prn'])->set('grupo', '05')->call('crearSeccion')->assertHasNoErrors();

    expect(DB::table('sections')->where('subject_id', $e['prn'])->where('code', '05')->exists())->toBeTrue()
        ->and(DB::table('sections')->where('subject_id', $e['mat'])->where('code', '05')->exists())->toBeFalse();
});
