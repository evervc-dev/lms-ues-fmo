<?php

use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Layout Aplicación y su cabecera (docs/13 Layout/Layout base.md).
 */
it('redirects guests to the login page', function () {
    $this->get(route('inicio'))->assertRedirect(route('login'));
});

it('renders the menu of the user role and not the others', function () {
    $this->actingAs(usuarioCon('estudiante'))
        ->get(route('inicio'))
        ->assertOk()
        ->assertSeeText(['Escanear QR', 'Mis materias', 'Mi perfil', 'Cerrar sesión'])
        ->assertDontSeeText(['Carga académica', 'Auditoría', 'Roles y permisos']);
});

it('shows one panel block per role of the user', function () {
    $this->actingAs(usuarioCon('coordinador', 'docente'))
        ->get(route('inicio'))
        ->assertOk()
        ->assertSeeTextInOrder(['Docente', 'Clases de hoy', 'Coordinador', 'Matrículas activas de estudiantes'])
        ->assertDontSeeText('Solicitudes de teléfono pendientes');
});

it('shows the active period in the header', function () {
    DbContext::system(fn () => DB::table('academic_periods')->insert([
        'name' => '2026-II', 'start_date' => '2026-07-27', 'end_date' => '2026-12-11', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]));

    $this->actingAs(usuarioCon('docente'))->get(route('inicio'))->assertOk()->assertSeeText('Período 2026-II · en curso');
});

it('warns when the emergency account is in use', function () {
    $usuario = usuarioCon('administrador');
    DbContext::system(fn () => $usuario->forceFill(['is_emergency' => true, 'password' => 'secreto-de-prueba'])->save());

    $this->actingAs($usuario)->get(route('inicio'))->assertOk()->assertSeeText('Está usando la cuenta de emergencia');
});

it('counts unread notifications in the bell', function () {
    $usuario = usuarioCon('estudiante');
    DbContext::system(fn () => $usuario->notifications()->create([
        'id' => (string) str()->uuid(), 'type' => 'Prueba', 'data' => ['title' => 'Nota publicada'],
    ]));

    $this->actingAs($usuario)->get(route('inicio'))->assertOk()->assertSee('Notificaciones: 1 sin leer');
});

it('logs out and returns to the login page', function () {
    $this->actingAs(usuarioCon('estudiante'))->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('renders error pages in Spanish with the guest layout', function () {
    $this->get('/no-existe')->assertNotFound()->assertSeeText(['No encontramos lo que busca', 'Volver a Inicio']);
});

it('renders the exam layout without menu or bell', function () {
    $html = Blade::render('<x-layouts::exam title="Parcial 1"><p>Pregunta 1</p></x-layouts::exam>');

    expect($html)->toContain('Parcial 1', 'Pregunta 1')->not->toContain('Menú principal', 'Notificaciones');
});

it('renders the projection layout without student data', function () {
    $html = Blade::render('<x-layouts::projection title="PRN115 · Asistencia"><p>QR</p></x-layouts::projection>');

    expect($html)->toContain('PRN115 · Asistencia', 'QR')->not->toContain('Menú principal');
});
