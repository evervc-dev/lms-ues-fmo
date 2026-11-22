<?php

use App\Modules\Academica\Livewire\AsignaturaFormulario;
use App\Modules\Academica\Livewire\Carreras;
use App\Modules\Academica\Services\CatalogoService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('CP-RF07-24 el Administrador crea una carrera con su primer plan desde las carreras del departamento', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::withQueryParams(['departamento' => $e['dcnm']])->test(Carreras::class)
        ->assertSee('Nueva carrera')
        ->call('nuevaCarrera')->assertSet('carreraDepartamento', $e['dcnm'])
        ->set('carreraCodigo', 'lma')->set('carreraNombre', 'Licenciatura en Matemática')->set('carreraPlan', 'Plan 20')
        ->call('crearCarrera')->assertHasNoErrors()->assertDispatched('cerrar-modal', 'nueva-carrera')
        ->assertSee('LMA')->assertSee('Plan 20');

    $carrera = DB::table('careers')->where('code', 'LMA')->first();
    expect((int) $carrera->department_id)->toBe($e['dcnm'])
        ->and(DB::table('study_plans')->where('career_id', $carrera->id)->pluck('name')->all())->toBe(['Plan 20']);

    // Código repetido, sin distinguir mayúsculas.
    expect(fn () => app(CatalogoService::class)->crearCarrera($e['administrador'], $e['dia'], 'isi', 'Otra', 'Plan 1'))
        ->toThrow(ValidationException::class, 'Ya existe una carrera con ese código.');
});

it('CP-RF07-24 el Administrador agrega un plan a una carrera y no repite el nombre', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(Carreras::class)->call('nuevoPlan', $e['isi'])->set('planNombre', 'Plan 98')->call('agregarPlan')->assertHasNoErrors()
        ->assertSee('Plan 25 · Plan 98');
    Livewire::test(Carreras::class)->call('nuevoPlan', $e['isi'])->set('planNombre', 'plan 98')->call('agregarPlan')->assertHasErrors(['planNombre']);
});

it('CP-RF07-24 el Jefe y el Coordinador no crean carreras ni planes', function (string $rol) {
    $e = escenario();
    actingAs($e[$rol]);

    Livewire::test(Carreras::class)->assertDontSee('Nueva carrera')->assertDontSee('Agregar plan');
    expect(fn () => app(CatalogoService::class)->crearCarrera($e[$rol], $e['dia'], 'NUE', 'Nueva', 'Plan 1'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CatalogoService::class)->agregarPlan($e[$rol], $e['isi'], 'Plan 98'))->toThrow(AuthorizationException::class);
})->with(['jefe', 'coordinador']);

it('CP-RF07-25 solo ofrece como prerrequisito las materias del plan de la asignatura', function () {
    $e = escenario();
    actingAs($e['administrador']);
    // MAT115 no está en ningún plan del escenario; PRN115 sí, en el Plan 25 de ISI.
    $pantalla = Livewire::test(AsignaturaFormulario::class)->assertSee('Elija primero la carrera y el plan de estudios');

    $pantalla->set('planes.0.carrera', $e['isi'])->set('planes.0.plan', $e['plan'])
        ->assertSee('PRN115')->assertDontSee('MAT115');
});

it('CP-RF07-25 rechaza guardar un prerrequisito de otro plan de estudios y conserva los que ya tenía', function () {
    $e = escenario();
    $datos = ['codigo' => 'ELE200', 'nombre' => 'Electiva', 'creditos' => 3, 'departamento' => $e['dia'], 'planes' => [['plan' => $e['plan'], 'ciclo' => 3]]];

    expect(fn () => app(CatalogoService::class)->guardarAsignatura($e['administrador'], null, $datos + ['prerrequisitos' => [$e['prn'], $e['mat']]]))
        ->toThrow(ValidationException::class, 'No están en él: MAT115.');

    // Un prerrequisito anterior fuera del plan se conserva al editar otra cosa.
    $id = app(CatalogoService::class)->guardarAsignatura($e['administrador'], null, $datos + ['prerrequisitos' => [$e['prn']]]);
    DbContext::system(fn () => DB::table('subject_prerequisites')->insert(['subject_id' => $id, 'prerequisite_id' => $e['mat']]));
    app(CatalogoService::class)->guardarAsignatura($e['administrador'], $id, ['nombre' => 'Electiva técnica', 'prerrequisitos' => [$e['prn'], $e['mat']]] + $datos);

    expect(DB::table('subject_prerequisites')->where('subject_id', $id)->pluck('prerequisite_id')->map(fn ($p) => (int) $p)->sort()->values()->all())
        ->toBe(collect([$e['prn'], $e['mat']])->sort()->values()->all());
});
