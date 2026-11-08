<?php

use App\Models\User;
use App\Modules\Academica\Livewire\ParticipantesSeccion;
use App\Modules\Identidad\Services\PerfilContextualService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Perfil contextual (RF36, ADR-056): el perfil cambia según el rol de quien se visita, y solo se ve con un vínculo
| académico o jerárquico en el período en curso. Escenario: PRN115·01 (DIA, plan ISI) con su docente y su
| estudiante; MAT115·01 (DCNM) sin docente; coordinador de ISI y Jefe de DIA.
*/

function perfiles(): PerfilContextualService
{
    return app(PerfilContextualService::class);
}

/** Un estudiante de ISI inscrito en la sección indicada del escenario. */
function perfilEstudianteEn(array $e, int $seccion, ?int $carrera = null, ?int $plan = null): User
{
    $estudiante = usuarioCon('estudiante');
    DbContext::system(function () use ($e, $estudiante, $seccion, $carrera, $plan) {
        DB::table('student_careers')->insert(['student_id' => $estudiante->id, 'career_id' => $carrera ?? $e['isi'], 'study_plan_id' => $plan ?? $e['plan'],
            'current_cycle' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $asignatura = (int) DB::table('sections')->where('id', $seccion)->value('subject_id');
        DB::table('enrollments')->insert(['student_id' => $estudiante->id, 'section_id' => $seccion, 'period_id' => $e['periodo'], 'subject_id' => $asignatura,
            'status' => 'active', 'enrolled_via' => 'admin', 'enrolled_by' => $e['administrador']->id, 'created_at' => now(), 'updated_at' => now()]);
    });

    return $estudiante;
}

/** Un docente con perfil en el departamento indicado y sin secciones. */
function perfilDocenteDe(int $departamento): User
{
    $docente = usuarioCon('docente');
    DbContext::system(fn () => DB::table('teacher_profiles')->insert(['user_id' => $docente->id, 'department_id' => $departamento, 'created_at' => now(), 'updated_at' => now()]));

    return $docente;
}

/** Una carrera de DCNM (Licenciatura en Matemática) con un estudiante sin matrículas. */
function perfilEstudianteDeOtraCarrera(array $e): User
{
    $estudiante = usuarioCon('estudiante');
    DbContext::system(function () use ($e, $estudiante) {
        $carrera = DB::table('careers')->insertGetId(['department_id' => $e['dcnm'], 'code' => 'LMA', 'name' => 'Licenciatura en Matemática', 'created_at' => now(), 'updated_at' => now()]);
        $plan = DB::table('study_plans')->insertGetId(['career_id' => $carrera, 'name' => 'Plan 20', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('student_careers')->insert(['student_id' => $estudiante->id, 'career_id' => $carrera, 'study_plan_id' => $plan,
            'current_cycle' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    });

    return $estudiante;
}

it('CP-RF36-01 adapta el perfil al rol de la persona visitada', function () {
    $e = escenario();

    expect(perfiles()->bloques($e['jefe']->id, ['jefe_departamento'], false))->toBe(['jefe_departamento' => ['departamentos' => ['Ingeniería y Arquitectura']]])
        ->and(perfiles()->bloques($e['coordinador']->id, ['coordinador'], false))->toBe(['coordinador' => ['carreras' => ['Ingeniería de Sistemas Informáticos']]]);

    $docente = perfiles()->bloques($e['docente']->id, ['docente'], false)['docente'];
    expect($docente['departamento'])->toBe('Ingeniería y Arquitectura')
        ->and($docente['carreras'])->toBe(['Ingeniería de Sistemas Informáticos'])
        ->and($docente['materias'])->toHaveCount(1)
        ->and($docente['materias'][0]->code)->toBe('PRN115')
        ->and($docente['materias'][0]->ciclos)->toBe(['ISI · ciclo 1']);

    expect(perfiles()->bloques($e['estudiante']->id, ['estudiante'], false))->toBe(['estudiante' => ['carrera' => 'Ingeniería de Sistemas Informáticos']]);
});

it('CP-RF36-01 muestra un bloque por cada rol de quien tiene varios', function () {
    $e = escenario();
    DbContext::system(fn () => $e['coordinador']->assignRole('docente'));
    actingAs($e['administrador']);

    expect(array_keys(perfiles()->perfil($e['administrador'], $e['coordinador']->id)['bloques']))->toBe(['coordinador', 'docente']);
});

it('CP-RF36-02 da el nivel completo al Administrador, con año de ingreso y materias inscritas del estudiante', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('student_careers')->where('student_id', $e['estudiante']->id)->update(['admission_period_id' => $e['periodo']]));
    actingAs($e['administrador']);

    $perfil = perfiles()->perfil($e['administrador'], $e['estudiante']->id);
    $estudiante = $perfil['bloques']['estudiante'];

    expect($perfil['nivel'])->toBe(PerfilContextualService::COMPLETO)
        ->and($estudiante['plan'])->toBe('Plan 25')
        ->and($estudiante['ciclo'])->toBe(1)
        ->and($estudiante['carne'])->toBe('GS21004')
        ->and($estudiante['ingreso'])->toBe(['anio' => 2026, 'periodo' => '2026-II'])
        ->and(collect($estudiante['materias'])->pluck('code')->all())->toBe(['PRN115']);

    actingAs($e['administrador'])->get(route('personas.show', ['usuario' => $e['estudiante']->id]))->assertOk()
        ->assertSeeText(['Año de ingreso', '2026 (2026-II)', 'GS21004', 'Administrar cuenta']);
});

it('CP-RF36-03 da el nivel mínimo al Docente: sin carné, ciclo ni materias del estudiante', function () {
    $e = escenario();
    actingAs($e['docente']);

    $perfil = perfiles()->perfil($e['docente'], $e['estudiante']->id);

    expect($perfil['nivel'])->toBe(PerfilContextualService::MINIMO)
        ->and($perfil['bloques']['estudiante'])->toBe(['carrera' => 'Ingeniería de Sistemas Informáticos']);
    actingAs($e['docente'])->get(route('personas.show', ['usuario' => $e['estudiante']->id]))->assertOk()
        ->assertSeeText([$e['estudiante']->institutional_email, 'Ingeniería de Sistemas Informáticos'])
        ->assertDontSeeText(['GS21004', 'Carné', 'Materias inscritas', 'Administrar cuenta']);
});

it('CP-RF36-04 deja al estudiante ver a sus compañeros, a su docente y al coordinador de su carrera', function () {
    $e = escenario();
    $companero = perfilEstudianteEn($e, $e['seccion']);

    foreach ([$companero, $e['docente'], $e['coordinador']] as $visible) {
        actingAs($e['estudiante'])->get(route('personas.show', ['usuario' => $visible->id]))->assertOk();
    }
});

it('CP-RF36-04 no deja al estudiante ver a quien no comparte su sección, al Jefe, al Administrador ni a Soporte', function () {
    $e = escenario();
    $ajeno = perfilEstudianteEn($e, $e['otra']);
    actingAs($e['estudiante']);

    foreach ([$ajeno, $e['jefe'], $e['administrador'], $e['soporte']] as $oculto) {
        expect(fn () => perfiles()->perfil($e['estudiante'], $oculto->id))->toThrow(AuthorizationException::class);
        actingAs($e['estudiante'])->get(route('personas.show', ['usuario' => $oculto->id]))->assertForbidden();
    }
});

it('CP-RF36-04 deja ver al Jefe cuando también es docente del estudiante', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('teaching_assignments')->insert(['section_id' => $e['seccion'], 'teacher_id' => $e['jefe']->id, 'is_responsible' => false,
        'started_at' => '2026-07-27', 'created_at' => now(), 'updated_at' => now()]));

    actingAs($e['estudiante'])->get(route('personas.show', ['usuario' => $e['jefe']->id]))->assertOk();
});

it('CP-RF36-05 deja al docente ver a sus estudiantes, a los docentes de su departamento, a su Jefe y a los coordinadores donde imparte', function () {
    $e = escenario();
    $colega = perfilDocenteDe($e['dia']);
    actingAs($e['docente']);

    expect(perfiles()->visibles($e['docente'], [$e['estudiante']->id, $colega->id, $e['jefe']->id, $e['coordinador']->id]))
        ->toEqualCanonicalizing([$e['estudiante']->id, $colega->id, $e['jefe']->id, $e['coordinador']->id]);
});

it('CP-RF36-05 no deja al docente ver a estudiantes a los que no da clases ni a docentes de otro departamento', function () {
    $e = escenario();
    $ajeno = perfilEstudianteEn($e, $e['otra']);
    $deOtroDepartamento = perfilDocenteDe($e['dcnm']);
    actingAs($e['docente']);

    expect(perfiles()->visibles($e['docente'], [$ajeno->id, $deOtroDepartamento->id, $e['administrador']->id]))->toBe([]);
    actingAs($e['docente'])->get(route('personas.show', ['usuario' => $ajeno->id]))->assertForbidden();
});

it('CP-RF36-06 limita al Jefe a su departamento y al Coordinador a su carrera', function () {
    $e = escenario();
    $docenteDia = perfilDocenteDe($e['dia']);
    $docenteDcnm = perfilDocenteDe($e['dcnm']);
    $deOtraCarrera = perfilEstudianteDeOtraCarrera($e);

    actingAs($e['jefe']);
    expect(perfiles()->visibles($e['jefe'], [$docenteDia->id, $docenteDcnm->id, $e['estudiante']->id, $e['coordinador']->id, $deOtraCarrera->id]))
        ->toEqualCanonicalizing([$docenteDia->id, $e['estudiante']->id, $e['coordinador']->id]);

    // El docente de PRN115 imparte una materia del plan ISI; el docente de DIA sin secciones no.
    actingAs($e['coordinador']);
    expect(perfiles()->visibles($e['coordinador'], [$e['docente']->id, $docenteDia->id, $e['estudiante']->id, $e['jefe']->id, $deOtraCarrera->id]))
        ->toEqualCanonicalizing([$e['docente']->id, $e['estudiante']->id, $e['jefe']->id]);
    actingAs($e['coordinador'])->get(route('personas.show', ['usuario' => $deOtraCarrera->id]))->assertForbidden();
});

it('CP-RF36-06 deja al Administrador y a Soporte ver a cualquiera', function (string $rol) {
    $e = escenario();
    $deOtraCarrera = perfilEstudianteDeOtraCarrera($e);

    actingAs($e[$rol])->get(route('personas.show', ['usuario' => $deOtraCarrera->id]))->assertOk();
})->with(['administrador', 'soporte']);

it('CP-RF36-07 lista los participantes de la sección con enlace solo donde hay vínculo', function () {
    $e = escenario();
    $companero = perfilEstudianteEn($e, $e['seccion']);
    $deOtraCarrera = perfilEstudianteDeOtraCarrera($e);
    DbContext::system(fn () => DB::table('enrollments')->insert(['student_id' => $deOtraCarrera->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
        'status' => 'active', 'enrolled_via' => 'admin', 'enrolled_by' => $e['administrador']->id, 'created_at' => now(), 'updated_at' => now()]));

    actingAs($e['estudiante'])->get(route('secciones.participantes', ['seccion' => $e['ulid']]))->assertOk()
        ->assertSeeText(['Docentes', 'Coordinación de la carrera', 'Estudiantes (3)', $e['docente']->first_name, $companero->first_name])
        ->assertSee(route('personas.show', ['usuario' => $companero->id]), false)
        ->assertSee(route('personas.show', ['usuario' => $e['coordinador']->id]), false);

    // El Coordinador de ISI ve en la lista al estudiante de Matemática, pero sin enlace a su perfil.
    actingAs($e['coordinador']);
    $estudiantes = Livewire::test(ParticipantesSeccion::class, ['seccion' => $e['ulid']])->viewData('estudiantes')->keyBy('id');
    expect($estudiantes[$deOtraCarrera->id]->visible)->toBeFalse()
        ->and($estudiantes[$companero->id]->visible)->toBeTrue();
});

it('CP-RF36-07 no muestra los participantes a quien no pertenece a la sección', function () {
    $e = escenario();
    $ajeno = perfilEstudianteEn($e, $e['otra']);

    actingAs($ajeno)->get(route('secciones.participantes', ['seccion' => $e['ulid']]))->assertForbidden();
});

it('CP-RF36-08 completa la ficha de Usuarios con las carreras y ciclos del docente y el ingreso del estudiante', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('student_careers')->where('student_id', $e['estudiante']->id)->update(['admission_period_id' => $e['periodo']]));

    actingAs($e['administrador'])->get(route('usuarios.show', ['usuario' => $e['docente']->id]))->assertOk()
        ->assertSeeText(['Carreras donde imparte', 'Ingeniería de Sistemas Informáticos', 'ISI · ciclo 1', 'Ver perfil']);
    actingAs($e['administrador'])->get(route('usuarios.show', ['usuario' => $e['estudiante']->id]))->assertOk()
        ->assertSeeText(['Año de ingreso', '2026 (2026-II)', 'Materias inscritas este período', 'PRN115 · 01']);
});
