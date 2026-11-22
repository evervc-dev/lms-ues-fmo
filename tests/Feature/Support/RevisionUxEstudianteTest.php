<?php

use App\Modules\Academica\Livewire\MisMaterias;
use App\Modules\Asistencia\Livewire\EscanearQr;
use App\Modules\Calificaciones\Livewire\MisNotas;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Identidad\Livewire\Perfil;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Revisión UX/UI del rol Estudiante (21/11/2026): lo que el estudiante ve cuando todavía no hay datos (notas,
| teléfono, unidades) y los atajos que antes llevaban a un callejón sin salida (cambio de grupo vencido).
*/

it('CP-RF17-01 sin notas publicadas la boleta dice «Sin notas aún» y no un 0.0 de reprobado', function () {
    $e = escenario();
    actingAs($e['estudiante']);

    Livewire::test(MisNotas::class)->assertSeeText('Sin notas aún')->assertDontSeeText('0.0');
});

it('CP-RF20-01 sin teléfono registrado pide registrarlo antes de escanear y no muestra el lector', function () {
    $e = escenario();
    actingAs($e['estudiante']);

    Livewire::test(EscanearQr::class)
        ->assertSeeText('Escanear QR')
        ->assertSeeText('Registre su teléfono')
        ->assertSee(route('perfil.dispositivo'), false)
        ->assertDontSee('lector-qr-video', false)
        ->assertSee('pattern="[0-9]{6}"', false);
});

it('CP-RF20-01 con teléfono registrado muestra el lector', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('student_devices')->insert(['student_id' => $e['estudiante']->id, 'credential_id' => 'abc', 'status' => 'active',
        'created_at' => now(), 'updated_at' => now()]));
    actingAs($e['estudiante']);

    Livewire::test(EscanearQr::class)->assertSee('lector-qr-video', false)->assertDontSeeText('Registre su teléfono');
});

it('CP-RF27-01 sin teléfono registrado ofrece un QR para abrir la pantalla desde el celular', function () {
    $e = escenario();
    actingAs($e['estudiante']);

    Livewire::test(Perfil::class, ['pestana' => 'dispositivo'])
        ->assertSeeText('Abra esta página desde su teléfono')
        ->assertSee('<svg', false)
        ->assertSeeText('Registrar este teléfono');
});

it('CP-RF09-07 ofrece «Solicitar cambio de grupo» solo dentro del plazo', function () {
    $e = escenario();
    actingAs($e['estudiante']);

    // 2026-II empieza el 27/07 y el plazo son 3 semanas: hasta el 17/08/2026.
    $this->travelTo('2026-08-10 10:00');
    // El botón de cada tarjeta (el título del modal siempre está en la página).
    Livewire::test(MisMaterias::class)->assertSee('wire:click="abrirCambio(', false);

    $this->travelTo('2026-10-01 10:00');
    Livewire::test(MisMaterias::class)->assertDontSee('wire:click="abrirCambio(', false)
        ->assertSeeText('El plazo para pedir cambios de grupo terminó el 17/08/2026.');
});

it('CP-RF18-01 el estudiante ve en Progreso un texto para él y la miga pasa por «Mis materias»', function () {
    $e = escenario();

    actingAs($e['estudiante'])->get(route('secciones.progreso', ['seccion' => $e['ulid']]))->assertOk()
        ->assertSeeText('Su docente todavía no organiza el curso por unidades.')
        ->assertDontSeeText('Cree la primera unidad')
        ->assertSee(route('materias'), false);

    actingAs($e['docente'])->get(route('secciones.progreso', ['seccion' => $e['ulid']]))->assertOk()
        ->assertSeeText('Cree la primera unidad con sus materiales para seguir el avance.');
});

it('CP-RF17-01 la boleta dice cuánto del curso está evaluado y cuántos puntos lleva', function () {
    $e = escenario();
    // Dos cómputos: el primero (30 %) con 8.8 publicado, el segundo (70 %) sin notas.
    DbContext::system(function () use ($e) {
        DB::table('grading_components')->where('section_id', $e['seccion'])->delete();
        $t = fn (array $fila) => $fila + ['created_at' => now(), 'updated_at' => now()];
        $uno = DB::table('grading_components')->insertGetId($t(['section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27', 'ends_on' => '2026-09-30', 'weight_percent' => 30]));
        $dos = DB::table('grading_components')->insertGetId($t(['section_id' => $e['seccion'], 'number' => 2, 'name' => 'Cómputo 2', 'starts_on' => '2026-10-01', 'ends_on' => '2026-12-11', 'weight_percent' => 70]));
        $item = DB::table('grade_items')->insertGetId($t(['component_id' => $uno, 'section_id' => $e['seccion'], 'item_type' => 'manual', 'title' => 'Parcial 1', 'weight_percent' => 100, 'is_published' => true]));
        DB::table('grade_items')->insert($t(['component_id' => $dos, 'section_id' => $e['seccion'], 'item_type' => 'manual', 'title' => 'Parcial 2', 'weight_percent' => 100, 'is_published' => false]));
        DB::table('grades')->insert($t(['grade_item_id' => $item, 'enrollment_id' => $e['matricula'], 'section_id' => $e['seccion'], 'score' => 8.8, 'status' => 'published', 'published_at' => now()]));
    });
    actingAs($e['estudiante']);

    $boleta = app(CalificacionService::class)->boleta($e['estudiante'], $e['seccion']);
    expect($boleta['final'])->toBe(8.8)->and($boleta['evaluado'])->toBe(30.0)->and($boleta['puntos'])->toBe(2.6);

    $this->get(route('secciones.notas', ['seccion' => $e['ulid']]))->assertOk()
        ->assertSeeText('Promedio de lo evaluado')
        ->assertSeeText('30 % del curso evaluado · lleva 2.6 de 10 puntos')
        ->assertDontSeeText('Nota acumulada');
});
