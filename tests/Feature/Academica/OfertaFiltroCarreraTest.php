<?php

use App\Modules\Academica\Livewire\Oferta;
use App\Modules\Academica\Services\OfertaService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Filtro de carrera de las estadísticas de oferta (RF10): el Coordinador siempre ve una carrera; el Jefe y el
 * Administrador conservan «todas las carreras de su alcance».
 */
it('CP-RF10-03 al Coordinador le muestra su primera carrera por código, sin la opción de todas', function () {
    $e = escenario();
    actingAs($e['coordinador']);

    $datos = app(OfertaService::class)->estadisticas($e['coordinador']);
    expect($datos['carrera'])->toBe($e['isi'])
        ->and($datos['carreraObligatoria'])->toBeTrue();

    Livewire::test(Oferta::class)
        ->assertSet('filtroCarrera', (string) $e['isi'])
        ->assertDontSee(__('Todas las carreras de su alcance'));
});

it('CP-RF10-03 lleva al Coordinador a su carrera si la URL pide una fuera de su alcance', function () {
    $e = escenario();
    $otra = DbContext::system(fn () => DB::table('careers')->insertGetId([
        'department_id' => $e['dcnm'], 'code' => 'LMA', 'name' => 'Licenciatura en Matemática', 'created_at' => now(), 'updated_at' => now(),
    ]));
    actingAs($e['coordinador']);

    Livewire::withQueryParams(['carrera' => (string) $otra])->test(Oferta::class)
        ->assertSet('filtroCarrera', (string) $e['isi']);
});

it('CP-RF10-03 el Jefe y el Administrador conservan la opción de todas las carreras de su alcance', function () {
    $e = escenario();

    foreach (['jefe', 'administrador'] as $rol) {
        actingAs($e[$rol]);
        $datos = app(OfertaService::class)->estadisticas($e[$rol]);
        expect($datos['carrera'])->toBeNull()
            ->and($datos['carreraObligatoria'])->toBeFalse();

        Livewire::test(Oferta::class)
            ->assertSet('filtroCarrera', '')
            ->assertSee(__('Todas las carreras de su alcance'));
    }
});
