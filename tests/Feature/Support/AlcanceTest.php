<?php

use App\Models\User;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Alcance por rol (docs/04 Roles/Matriz RBAC.md, ADR-026).
 */
function seccionesVisibles(User $usuario, string $permiso): array
{
    return app(Alcance::class)->secciones(DB::table('sections'), $usuario, $permiso)->orderBy('id')->pluck('sections.id')->all();
}

it('gives each role its scope letter for a permission', function () {
    $e = escenario();
    $alcance = app(Alcance::class);

    expect($alcance->de($e['administrador'], 'matricula.gestionar'))->toBe('G')
        ->and($alcance->de($e['jefe'], 'matricula.gestionar'))->toBe('D')
        ->and($alcance->de($e['coordinador'], 'matricula.gestionar'))->toBe('C')
        ->and($alcance->de($e['docente'], 'matricula.gestionar'))->toBe('S')
        ->and($alcance->de($e['estudiante'], 'contenidos.ver'))->toBe('M')
        ->and($alcance->de($e['soporte'], 'matricula.gestionar'))->toBe('G')
        ->and($alcance->de($e['estudiante'], 'matricula.gestionar'))->toBeNull();
});

it('limits sections to the scope of each role', function () {
    $e = escenario();

    expect(seccionesVisibles($e['administrador'], 'matricula.gestionar'))->toBe([$e['seccion'], $e['otra']])
        ->and(seccionesVisibles($e['jefe'], 'matricula.gestionar'))->toBe([$e['seccion']])
        ->and(seccionesVisibles($e['coordinador'], 'matricula.gestionar'))->toBe([$e['seccion']])
        ->and(seccionesVisibles($e['docente'], 'matricula.gestionar'))->toBe([$e['seccion']])
        ->and(seccionesVisibles($e['estudiante'], 'contenidos.ver'))->toBe([$e['seccion']]);
});

it('uses the widest scope when the user has several roles', function () {
    $e = escenario();
    DbContext::system(fn () => $e['docente']->assignRole('coordinador'));

    expect(app(Alcance::class)->de($e['docente']->fresh(), 'matricula.gestionar'))->toBe('C');
});

it('rejects a user without the permission', function () {
    $e = escenario();

    app(Alcance::class)->secciones(DB::table('sections'), $e['estudiante'], 'matricula.gestionar');
})->throws(AuthorizationException::class);

it('limits students to the career of the coordinator', function () {
    $e = escenario();
    $otro = usuarioCon('estudiante');

    $ids = app(Alcance::class)->estudiantes(DB::table('users'), $e['coordinador'], 'matricula.gestionar')->pluck('id');

    expect($ids)->toContain($e['estudiante']->id)->not->toContain($otro->id);
});
