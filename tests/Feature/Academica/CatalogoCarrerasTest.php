<?php

use App\Modules\Academica\Livewire\Catalogo;
use App\Modules\Academica\Services\CatalogoService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Columna de carreras del catálogo (RF06): el Jefe ve las carreras de su departamento en lugar del departamento;
 * el Administrador ve el departamento y todas las carreras.
 */

/**
 * PRN115 también se lleva en una carrera de otro departamento (LMA, de DCNM).
 */
function prnEnOtraCarrera(array $e): void
{
    DbContext::system(function () use ($e) {
        $lma = DB::table('careers')->insertGetId(['department_id' => $e['dcnm'], 'code' => 'LMA', 'name' => 'Licenciatura en Matemática', 'created_at' => now(), 'updated_at' => now()]);
        $plan = DB::table('study_plans')->insertGetId(['career_id' => $lma, 'name' => 'Plan 20', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('career_subjects')->insert(['study_plan_id' => $plan, 'subject_id' => $e['prn'], 'cycle' => 2]);
    });
}

it('CP-RF06-12 el Jefe ve solo las carreras de su departamento y no la columna Depto.', function () {
    $e = escenario();
    prnEnOtraCarrera($e);
    actingAs($e['jefe']);

    expect(app(CatalogoService::class)->carreras($e['jefe'], [$e['prn']]))->toBe([$e['prn'] => ['ISI · Plan 25']]);

    Livewire::test(Catalogo::class)
        ->assertViewHas('veDepartamento', false)
        ->assertSeeHtml('<th>'.__('Carrera y plan').'</th>')
        ->assertDontSeeHtml('<th>'.__('Depto.').'</th>')
        ->assertDontSee('LMA');
});

it('CP-RF06-12 el Administrador ve el departamento y todas las carreras; sin plan muestra un guion', function () {
    $e = escenario();
    prnEnOtraCarrera($e);
    actingAs($e['administrador']);

    $carreras = app(CatalogoService::class)->carreras($e['administrador'], [$e['prn'], $e['mat']]);
    expect($carreras)->toBe([$e['prn'] => ['ISI · Plan 25', 'LMA · Plan 20']]);

    Livewire::test(Catalogo::class)
        ->assertViewHas('veDepartamento', true)
        ->assertSeeHtml('<th>'.__('Depto.').'</th>')
        ->assertSee('LMA');
});
