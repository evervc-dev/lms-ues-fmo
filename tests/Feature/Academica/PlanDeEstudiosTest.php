<?php

use App\Modules\Academica\Services\PlanesService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/** Un segundo plan de ISI (el antiguo) y una carrera de otro departamento con su plan. */
function segundoPlanYCarrera(array $e): array
{
    return DbContext::system(function () use ($e) {
        $t = fn (array $fila) => $fila + ['created_at' => now(), 'updated_at' => now()];
        $plan98 = DB::table('study_plans')->insertGetId($t(['career_id' => $e['isi'], 'name' => 'Plan 98']));
        $lma = DB::table('careers')->insertGetId($t(['department_id' => $e['dcnm'], 'code' => 'LMA', 'name' => 'Licenciatura en Matemática']));
        $plan20 = DB::table('study_plans')->insertGetId($t(['career_id' => $lma, 'name' => 'Plan 20']));
        DB::table('career_subjects')->insert([
            ['study_plan_id' => $plan98, 'subject_id' => $e['prn'], 'cycle' => 1],
            ['study_plan_id' => $e['plan'], 'subject_id' => $e['mat'], 'cycle' => 1],
            ['study_plan_id' => $plan20, 'subject_id' => $e['mat'], 'cycle' => 1],
        ]);

        return compact('plan98', 'lma', 'plan20');
    });
}

it('rotula el pensum de cada asignatura y antepone la carrera si la llevan varias', function () {
    $e = escenario();
    $extra = segundoPlanYCarrera($e);

    $planes = app(PlanesService::class);

    // El plan 25 se creó antes que el 98 en el escenario: van en el orden en que se crearon.
    expect($planes->etiquetas([$e['prn'], $e['mat']]))->toBe([
        $e['prn'] => 'Plan 25 · Plan 98',
        $e['mat'] => 'ISI: Plan 25 · LMA: Plan 20',
    ])->and($planes->deAsignatura($e['mat'], [$e['isi']]))->toBe('Plan 25')
        ->and($planes->deAsignatura($e['mat'], [$extra['lma']]))->toBe('Plan 20')
        ->and($planes->etiquetas([]))->toBe([]);
});

it('muestra el pensum en Mis materias, en la cabecera del grupo y en Inscripción', function () {
    $e = escenario();
    segundoPlanYCarrera($e);
    actingAs($e['estudiante']);

    $this->get(route('materias'))->assertOk()->assertSeeText('Plan 25 · Plan 98');
    $this->get(route('secciones.contenidos', ['seccion' => $e['ulid']]))->assertOk()->assertSeeText('Plan 25 · Plan 98');
    $this->get(route('inscripcion'))->assertOk()->assertSeeText('materias de su Plan 25');
});

it('muestra carrera y pensum en el Catálogo y el pensum en Carga académica', function () {
    $e = escenario();
    segundoPlanYCarrera($e);
    actingAs($e['administrador']);

    $this->get(route('catalogo'))->assertOk()->assertSeeText(['Carrera y plan', 'ISI · Plan 25, Plan 98', 'LMA · Plan 20']);
    $this->get(route('carga-academica'))->assertOk()->assertSeeText('Plan 25 · Plan 98');
});

it('el estudiante ve en Inicio, Mis notas y la cabecera solo los planes de su carrera', function () {
    $e = escenario();
    $extra = segundoPlanYCarrera($e);
    // PRN115 también se lleva en LMA: el personal lo ve, el estudiante de ISI no.
    DbContext::system(fn () => DB::table('career_subjects')->insert(['study_plan_id' => $extra['plan20'], 'subject_id' => $e['prn'], 'cycle' => 3]));

    actingAs($e['estudiante']);
    $this->get(route('inicio'))->assertOk()->assertSeeText('Plan 25 · Plan 98');
    $this->get(route('mis-notas'))->assertOk()->assertSeeText('Plan 25 · Plan 98');
    $this->get(route('secciones.contenidos', ['seccion' => $e['ulid']]))->assertOk()
        ->assertSeeText('Plan 25 · Plan 98')->assertDontSeeText('LMA: Plan 20');

    actingAs($e['docente'])->get(route('secciones.contenidos', ['seccion' => $e['ulid']]))->assertOk()
        ->assertSeeText('ISI: Plan 25, Plan 98 · LMA: Plan 20');
});
