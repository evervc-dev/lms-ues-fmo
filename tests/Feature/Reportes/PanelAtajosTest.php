<?php

use App\Modules\Reportes\Services\PanelService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Inicio como atajo (RF24, QA 24/11/2026): cada tarjeta lleva a la lista ya filtrada y, si pide atención, trae su
 * botón; el bloque «Qué sigue» junta los pendientes del rol, ordenados por fecha, con el botón que los resuelve.
 */
it('las tarjetas del Administrador llevan a la lista filtrada y piden la acción cuando hay algo pendiente', function () {
    $e = escenario();
    DB::table('failed_jobs')->insert(['uuid' => (string) str()->uuid(), 'connection' => 'redis', 'queue' => 'google', 'payload' => '{}', 'exception' => 'Error', 'failed_at' => now()]);

    actingAs($e['administrador'])->get(route('inicio'))->assertOk()
        ->assertSee('href="'.route('carga-academica', ['estado' => 'sin_docente']).'"', false)
        ->assertSee('href="'.route('reportes', ['tipo' => 'bajo_rendimiento']).'"', false)
        ->assertSee('href="'.route('matricula').'"', false)
        ->assertSee('href="'.route('horizon.index').'"', false)
        ->assertSeeText(['Asignar', 'Qué sigue', 'Asignar docente a los grupos teóricos', 'Revisar los trabajos fallidos']);
});

it('una tarjeta en cero se ve en verde y sin botón de acción', function () {
    $e = escenario();

    $html = actingAs($e['jefe'])->get(route('inicio'))->assertOk()->getContent();

    // El grupo de DIA tiene docente: «Sin docente» queda en 0, en verde y sin «Asignar».
    expect($html)->toMatch('/Sin docente.*?text-emerald-700[^>]*>\s*0\s*</s')
        ->and(app(PanelService::class)->jefe($e['jefe'])['queSigue'])->toBe([]);
    actingAs($e['jefe'])->get(route('inicio'))->assertSeeText('Está al día: no hay nada pendiente.');
});

it('«Qué sigue» del Coordinador trae el acta por validar con su botón', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('grade_sheets')->insert([
        'section_id' => $e['seccion'], 'status' => 'published', 'published_by' => $e['docente']->id, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]));

    $pendientes = app(PanelService::class)->coordinador($e['coordinador'])['queSigue'];

    expect($pendientes)->toHaveCount(1)
        ->and($pendientes[0])->titulo->toBe('Validar el acta de PRN115 · 01')->accion->toBe('Validar')->url->toBe(route('actas'));
    actingAs($e['coordinador'])->get(route('inicio'))->assertOk()->assertSeeText(['Qué sigue', 'Validar el acta de PRN115 · 01'])
        ->assertDontSeeText('Pendientes de decisión');
});

it('«Qué sigue» del Docente pide tomar la asistencia de la clase de hoy y enlaza sus tarjetas', function () {
    $e = escenario();
    $this->travelTo('2026-11-23 06:30'); // lunes: el grupo tiene clase de 07:00 a 08:40

    $pendientes = app(PanelService::class)->docente($e['docente'])['queSigue'];

    expect($pendientes[0])->titulo->toBe('Tomar asistencia de PRN115 · 01')->accion->toBe('Abrir asistencia')
        ->url->toBe(route('secciones.asistencia', ['seccion' => $e['ulid']]));
    actingAs($e['docente'])->get(route('inicio'))->assertOk()
        ->assertSee('href="'.route('pendientes-de-calificar').'"', false)
        ->assertSee('href="'.route('secciones', ['ambito' => 'propias']).'"', false)
        ->assertSeeText(['Qué sigue', 'Próximas evaluaciones']);
});

it('sin el permiso de la pantalla la tarjeta no enlaza', function () {
    $e = escenario();

    // El Coordinador no ve el monitoreo técnico ni los períodos: esas tarjetas son solo del Administrador.
    actingAs($e['coordinador'])->get(route('inicio'))->assertOk()
        ->assertDontSee('href="'.route('horizon.index').'"', false)
        ->assertDontSee('href="'.route('periodos').'"', false);
});

it('la fórmula provisional de Oferta va en el ícono de información y no en una caja fija', function () {
    $e = escenario();

    actingAs($e['coordinador'])->get(route('oferta'))->assertOk()
        ->assertSeeInOrder(['role="tooltip"', 'Fórmula provisional', 'Demanda = reprobados'], false)
        ->assertDontSee('bg-sky-50 text-sky-900', false); // la caja informativa de x-ui.alert
});
