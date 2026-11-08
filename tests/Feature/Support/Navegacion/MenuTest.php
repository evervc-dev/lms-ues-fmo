<?php

use App\Models\User;
use App\Support\DbContext;
use App\Support\Navegacion\Menu;
use App\Support\Navegacion\Opcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Menú unificado (ADR-034, docs/13 Layout/Layout base.md).
 *
 * @return array<string, array<int, string>> título del grupo (o «—») => claves de sus opciones
 */
function menuDe(User $usuario): array
{
    $menu = app(Menu::class)->para($usuario);

    return collect($menu['grupos'])->mapWithKeys(fn (array $grupo) => [
        $grupo['titulo'] ?? '—' => array_map(fn (Opcion $opcion) => $opcion->clave, $grupo['opciones']),
    ])->all();
}

it('shows a student only the study and account groups', function () {
    expect(menuDe(usuarioCon('estudiante')))->toBe([
        '—' => ['inicio'],
        'Estudio' => ['escanear-qr', 'inscripcion', 'mis-materias', 'mis-notas', 'mi-asistencia'],
        'Cuenta' => ['perfil', 'ayuda'],
    ]);
});

it('joins the groups of a coordinator who also teaches', function () {
    $menu = menuDe(usuarioCon('coordinador', 'docente'));

    expect(array_keys($menu))->toBe(['—', 'Docencia', 'Coordinación', 'Consultas', 'Cuenta'])
        ->and($menu['Coordinación'])->toContain('matricula', 'actas', 'carreras')
        ->and($menu['Coordinación'])->not->toContain('importar-estudiantes');
});

it('does not give teachers the enrollment option although they hold matricula.gestionar', function () {
    $menu = menuDe(usuarioCon('docente'));

    expect(collect($menu)->flatten())->not->toContain('matricula');
});

it('shows a shared option once, in the first group that grants it', function () {
    $menu = menuDe(usuarioCon('coordinador', 'administrador'));

    expect(collect($menu)->flatten()->filter(fn ($clave) => $clave === 'carga-academica'))->toHaveCount(1)
        ->and($menu['Coordinación'])->toContain('carga-academica')
        ->and($menu['Administración'])->not->toContain('carga-academica');
});

it('hides an option when the role loses its permission', function () {
    $usuario = usuarioCon('jefe_departamento');
    DbContext::system(fn () => Role::findByName('jefe_departamento')->revokePermissionTo('auditoria.ver'));

    expect(menuDe($usuario->fresh())['Consultas'])->not->toContain('auditoria');
});

it('marks options that still lead to an under-construction page', function () {
    config(['navegacion.grupos.0.opciones' => [
        ['clave' => 'inicio', 'etiqueta' => 'Inicio', 'ruta' => 'inicio', 'icono' => 'home'],
        ['clave' => 'calendario', 'etiqueta' => 'Google Calendar', 'ruta' => 'perfil.google-calendar', 'icono' => 'calendar-days'],
    ]]);
    $opciones = collect(app(Menu::class)->para(usuarioCon('docente'))['grupos'][0]['opciones'])->keyBy('clave');

    expect($opciones['inicio']->pendiente)->toBeFalse()
        ->and($opciones['calendario']->url)->toBe(route('perfil.google-calendar'))
        ->and($opciones['calendario']->pendiente)->toBeTrue();
});

it('disables options whose route does not exist', function () {
    config(['navegacion.grupos.0.opciones' => [['clave' => 'fantasma', 'etiqueta' => 'Fantasma', 'ruta' => 'no.existe', 'icono' => 'home']]]);

    $opcion = app(Menu::class)->para(usuarioCon('estudiante'))['grupos'][0]['opciones'][0];

    expect($opcion->url)->toBeNull()->and($opcion->disponible())->toBeFalse();
});

it('shows group titles only when there are at least two titled groups', function () {
    expect(app(Menu::class)->para(usuarioCon('soporte_tecnico'))['conTitulos'])->toBeTrue();

    $sinRol = DbContext::system(fn () => User::factory()->create());
    expect(app(Menu::class)->para($sinRol)['conTitulos'])->toBeFalse();
});

it('marks only the most precise option when one route is a prefix of another', function () {
    // «usuarios» cubre también «usuarios.*», así que en Importar estudiantes se resaltaban las dos.
    $html = $this->actingAs(usuarioCon('administrador'))->get(route('usuarios.importar'))->assertOk()->getContent();

    preg_match_all('/<a\s[^>]*href="([^"]+)"[^>]*aria-current="page"/', $html, $marcadas);

    expect(array_unique($marcadas[1]))->toBe([route('usuarios.importar')]);
});

it('indents an option declared as secondary and opens an external one without wire:navigate', function () {
    $opciones = collect(app(Menu::class)->para(usuarioCon('administrador'))['grupos'])->pluck('opciones')->flatten()->keyBy('clave');

    expect($opciones['importar-estudiantes']->secundaria)->toBeTrue()
        ->and($opciones['usuarios']->secundaria)->toBeFalse()
        ->and($opciones['colas']->externa)->toBeTrue()
        ->and($opciones['usuarios']->externa)->toBeFalse();
});

it('opens the external technical monitor in another tab, with its label and notice', function () {
    // QA sesión 4: «Colas» llevaba en la misma pestaña al panel de Horizon, que parecía otra aplicación.
    $html = $this->actingAs(usuarioCon('administrador'))->get(route('usuarios'))->assertOk()->getContent();

    expect($html)->toMatch('/<a\s[^>]*href="'.preg_quote(route('horizon.index'), '/').'"[^>]*target="_blank"/')
        ->and($html)->toContain('Monitoreo técnico', '(se abre en otra pestaña)');
});
