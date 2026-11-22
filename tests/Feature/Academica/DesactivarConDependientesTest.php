<?php

use App\Modules\Academica\Livewire\Catalogo;
use App\Modules\Academica\Services\CatalogoService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Desactivar una asignatura desactiva las que dependen de ella por prerrequisito (RF07, CP-RF07-23).
 */

/**
 * PR1 ← PR2 ← PR3 (cada una es prerrequisito de la siguiente), todas del departamento indicado.
 *
 * @return array<string, int> código => id
 */
function cadenaDePrerrequisitos(int $departamento, array $codigos = ['PR1', 'PR2', 'PR3']): array
{
    return DbContext::system(function () use ($departamento, $codigos) {
        $ids = [];
        $anterior = null;
        foreach ($codigos as $codigo) {
            $ids[$codigo] = DB::table('subjects')->insertGetId(['code' => $codigo, 'name' => 'Prueba '.$codigo, 'credits' => 4, 'department_id' => $departamento, 'created_at' => now(), 'updated_at' => now()]);
            if ($anterior !== null) {
                DB::table('subject_prerequisites')->insert(['subject_id' => $ids[$codigo], 'prerequisite_id' => $anterior]);
            }
            $anterior = $ids[$codigo];
        }

        return $ids;
    });
}

function activa(int $id): bool
{
    return (bool) DB::table('subjects')->where('id', $id)->value('is_active');
}

it('CP-RF07-23 desactivar una asignatura desactiva toda la cadena que depende de ella', function () {
    $e = escenario();
    $pr = cadenaDePrerrequisitos($e['dia']);

    $cascada = app(CatalogoService::class)->cambiarEstadoAsignatura($e['jefe'], $pr['PR1'], false);

    expect($cascada)->toBe(['PR2', 'PR3'])
        ->and(activa($pr['PR1']))->toBeFalse()
        ->and(activa($pr['PR2']))->toBeFalse()
        ->and(activa($pr['PR3']))->toBeFalse();
});

it('CP-RF07-23 al reactivar solo vuelve la asignatura elegida', function () {
    $e = escenario();
    $pr = cadenaDePrerrequisitos($e['dia']);
    $catalogo = app(CatalogoService::class);
    $catalogo->cambiarEstadoAsignatura($e['administrador'], $pr['PR1'], false);

    expect($catalogo->cambiarEstadoAsignatura($e['administrador'], $pr['PR1'], true))->toBe([])
        ->and(activa($pr['PR1']))->toBeTrue()
        ->and(activa($pr['PR2']))->toBeFalse()
        ->and(activa($pr['PR3']))->toBeFalse();
});

it('CP-RF07-23 bloquea al Jefe si una dependiente activa es de otro departamento, y el Administrador sí puede', function () {
    $e = escenario();
    $pr = cadenaDePrerrequisitos($e['dia'], ['PR1']);
    $ajena = cadenaDePrerrequisitos($e['dcnm'], ['MA9']);
    DbContext::system(fn () => DB::table('subject_prerequisites')->insert(['subject_id' => $ajena['MA9'], 'prerequisite_id' => $pr['PR1']]));
    $catalogo = app(CatalogoService::class);

    expect(fn () => $catalogo->cambiarEstadoAsignatura($e['jefe'], $pr['PR1'], false))->toThrow(ValidationException::class, 'MA9');
    expect(activa($pr['PR1']))->toBeTrue()->and(activa($ajena['MA9']))->toBeTrue();

    expect($catalogo->cambiarEstadoAsignatura($e['administrador'], $pr['PR1'], false))->toBe(['MA9'])
        ->and(activa($ajena['MA9']))->toBeFalse();
});

it('CP-RF07-23 la confirmación nombra las dependientes y el aviso las lista', function () {
    $e = escenario();
    $pr = cadenaDePrerrequisitos($e['dia']);
    actingAs($e['jefe']);

    Livewire::test(Catalogo::class)
        ->assertSeeHtml(e(__('¿Desactivar :c? También se desactivarán las que dependen de ella: :d.', ['c' => 'PR1', 'd' => 'PR2, PR3'])))
        ->call('alternarEstado', $pr['PR1'])
        ->assertDispatched('aviso', tipo: 'exito', mensaje: __('Asignatura desactivada junto con las que dependen de ella: :codigos. No se ofrecerán al crear grupos teóricos.', ['codigos' => 'PR2, PR3']));
});
