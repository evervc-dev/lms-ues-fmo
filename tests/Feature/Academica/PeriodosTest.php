<?php

use App\Modules\Academica\Livewire\PeriodoDetalle;
use App\Modules\Academica\Livewire\Periodos;
use App\Modules\Academica\Services\PeriodoService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Períodos académicos (RF06): solo puede haber un período activo (CP-RF06-01), cierre y reapertura (CP-RF06-02),
| consulta y exportación de un período cerrado (CP-RF06-05), fechas sin cruces (CP-RF06-07) y la acción visible en la
| lista (CP-RF06-08). Lo gestiona el Administrador (periodos.gestionar G). El rechazo del segundo período activo está
| en GestionAcademicaTest y en SchemaTest; el cierre con departamentos pendientes (CP-RF06-03), en CierrePeriodoTest;
| la exportación de un período en curso (CP-RF06-06), en ExportacionesTest.
*/

/** Un período planificado que ya terminó su preparación: listo para activarse. */
function periodoListo(): int
{
    return DbContext::system(fn () => DB::table('academic_periods')->insertGetId([
        'name' => '2027-I', 'start_date' => '2027-02-01', 'end_date' => '2027-06-30', 'status' => 'planned', 'preparation_stage' => 'ready',
        'created_at' => now(), 'updated_at' => now(),
    ]));
}

it('CP-RF06-01 activa el período listo cuando no hay otro activo', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => 'closed']));
    $nuevo = periodoListo();
    actingAs($e['administrador']);

    app(PeriodoService::class)->activar($e['administrador'], $nuevo);

    expect(DB::table('academic_periods')->where('status', 'active')->pluck('id')->all())->toBe([$nuevo]);
});

it('CP-RF06-01 solo el Administrador activa un período', function (string $rol) {
    $e = escenario();
    DbContext::system(fn () => DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => 'closed']));
    $nuevo = periodoListo();

    expect(fn () => app(PeriodoService::class)->activar($e[$rol], $nuevo))->toThrow(AuthorizationException::class);

    expect(DB::table('academic_periods')->where('id', $nuevo)->value('status'))->toBe('planned');
})->with(['coordinador', 'jefe', 'soporte']);

/**
 * Cierra el período del escenario como el Administrador, con los dos departamentos autorizados (RF34).
 *
 * @param  array<string, mixed>  $e
 */
function reaperturaCerrar(array $e): void
{
    DbContext::system(function () use ($e) {
        foreach ([$e['dia'], $e['dcnm']] as $departamento) {
            DB::table('department_closures')->insert([
                'period_id' => $e['periodo'], 'department_id' => $departamento, 'authorized_by' => $e['jefe']->id,
                'authorized_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    });
    actingAs($e['administrador']);
    app(PeriodoService::class)->cerrar($e['administrador'], $e['periodo']);
}

/**
 * Reabre el período como el actor y devuelve los errores de validación (vacío si reabrió).
 *
 * @return array<string, array<int, string>>
 */
function reaperturaErrores(object $actor, int $periodo, string $motivo = 'Corrección del acta de PRN115.'): array
{
    try {
        app(PeriodoService::class)->reabrir($actor, $periodo, $motivo);

        return [];
    } catch (ValidationException $e) {
        return $e->errors();
    }
}

/* ------------------------------------------------------------------ CP-RF06-02 (cierre y reapertura) */

it('CP-RF06-02 el Administrador cierra y reabre el período, que conserva las autorizaciones y los resultados', function () {
    $e = escenario();
    reaperturaCerrar($e);
    expect(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('closed');

    expect(reaperturaErrores($e['administrador'], $e['periodo']))->toBe([])
        ->and(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('active')
        ->and(DB::table('department_closures')->where('period_id', $e['periodo'])->count())->toBe(2)
        ->and(DB::table('enrollment_results')->where('enrollment_id', $e['matricula'])->exists())->toBeTrue();

    app(PeriodoService::class)->cerrar($e['administrador'], $e['periodo']);
    expect(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('closed');
});

it('CP-RF06-02 solo el Administrador reabre un período cerrado', function (string $rol) {
    $e = escenario();
    reaperturaCerrar($e);
    actingAs($e[$rol]);

    expect(fn () => app(PeriodoService::class)->reabrir($e[$rol], $e['periodo'], 'Corrección del acta.'))->toThrow(AuthorizationException::class)
        ->and(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('closed');
})->with(['docente', 'coordinador', 'jefe', 'soporte']);

it('CP-RF06-02 rechaza la reapertura sin motivo', function (string $motivo) {
    $e = escenario();
    reaperturaCerrar($e);

    expect(reaperturaErrores($e['administrador'], $e['periodo'], $motivo))->toHaveKey('motivo')
        ->and(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('closed');
})->with(['vacío' => '', 'espacios' => '     ', 'corto' => 'abc']);

it('CP-RF06-02 solo reabre un período cerrado y sin otro en curso', function () {
    $e = escenario();
    actingAs($e['administrador']);
    expect(reaperturaErrores($e['administrador'], $e['periodo'])['estado'][0])->toContain('Solo se reabre un período cerrado');

    reaperturaCerrar($e);
    $nuevo = periodoListo();
    app(PeriodoService::class)->activar($e['administrador'], $nuevo);

    expect(reaperturaErrores($e['administrador'], $e['periodo'])['estado'][0])->toContain('solo puede haber uno activo')
        ->and(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('closed');
});

it('CP-RF06-02 avisa a los Coordinadores de la reapertura con el motivo', function () {
    $e = escenario();
    reaperturaCerrar($e);

    reaperturaErrores($e['administrador'], $e['periodo'], 'Corrección del acta de PRN115.');

    $aviso = DB::table('notifications')->where('notifiable_id', $e['coordinador']->id)->where('event_code', 'period.reopened')->first();
    expect($aviso)->not->toBeNull()
        ->and(json_decode($aviso->data, true)['body'])->toContain('2026-II', 'Corrección del acta de PRN115.')
        ->and(DB::table('notifications')->where('notifiable_id', $e['docente']->id)->where('event_code', 'period.reopened')->exists())->toBeFalse();
});

it('CP-RF06-02 reabre desde el detalle del período con el motivo del modal', function () {
    $e = escenario();
    reaperturaCerrar($e);

    Livewire::test(PeriodoDetalle::class, ['periodo' => $e['periodo']])
        ->assertSee('Reabrir período')->assertDontSee('Cerrar período')
        ->call('reabrir')->assertHasErrors(['motivo' => 'required'])
        ->set('motivo', 'Corrección del acta de PRN115.')->call('reabrir')->assertHasNoErrors()->assertDispatched('aviso', tipo: 'exito');

    expect(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('active');
});

it('CP-RF06-02 audita el cierre y la reapertura con el actor y el motivo', function () {
    $e = escenario();
    reaperturaCerrar($e);
    reaperturaErrores($e['administrador'], $e['periodo'], 'Corrección del acta de PRN115.');

    DbContext::auditScope(all: true);
    $auditoria = DB::table('audit_logs')->where('entity_type', 'academic_periods')->where('entity_id', $e['periodo'])
        ->where('actor_user_id', $e['administrador']->id)->get(['event_type', 'reason', 'old_values', 'new_values']);
    $reapertura = $auditoria->firstWhere('event_type', 'period.reopened');

    expect($auditoria->pluck('event_type')->all())->toContain('period.closed', 'period.reopened')
        ->and($reapertura->reason)->toBe('Corrección del acta de PRN115.')
        ->and(json_decode($reapertura->old_values, true)['status'])->toBe('closed')
        ->and(json_decode($reapertura->new_values, true)['status'])->toBe('active');
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'Requiere PostgreSQL');

/* ------------------------------------------------------------------ CP-RF06-05 (período cerrado) */

it('CP-RF06-05 un período cerrado se consulta en solo lectura y se exporta a Excel y PDF', function () {
    $e = escenario();
    reaperturaCerrar($e);

    Livewire::test(PeriodoDetalle::class, ['periodo' => $e['periodo']])
        ->assertOk()->assertSee('Cerrado')->assertDontSee('Cerrar período')->assertDontSee('Poner en curso')
        ->call('exportar', 'xlsx')->assertFileDownloaded('periodo-periodo-2026-ii.xlsx');
    Livewire::test(PeriodoDetalle::class, ['periodo' => $e['periodo']])->call('exportar', 'pdf')->assertFileDownloaded();

    expect(app(PeriodoService::class)->detalleExportable($e['administrador'], $e['periodo'])['subtitulo'])->toContain('Cerrado');
});

/* ------------------------------------------------------------------ CP-RF06-07 (fechas sin cruces) */

it('CP-RF06-07 rechaza un período cuyas fechas se cruzan con otro, en cualquier estado, nombrando el choque', function (string $estado) {
    $e = escenario();
    DbContext::system(fn () => DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => $estado]));
    actingAs($e['administrador']);

    expect(fn () => app(PeriodoService::class)->crear($e['administrador'], '2026-X', '2026-12-01', '2027-02-15'))
        ->toThrow(ValidationException::class, 'Las fechas se cruzan con el período 2026-II (27/07/2026 – 11/12/2026).')
        ->and(DB::table('academic_periods')->where('name', '2026-X')->exists())->toBeFalse();
})->with(['planned', 'active', 'closed']);

it('CP-RF06-07 acepta un período que empieza el día en que termina el anterior', function () {
    $e = escenario();
    actingAs($e['administrador']);

    $id = app(PeriodoService::class)->crear($e['administrador'], '2027-I', '2026-12-11', '2027-06-30');

    expect(DB::table('academic_periods')->where('id', $id)->value('status'))->toBe('planned');
});

/* ------------------------------------------------------------------ CP-RF06-08 (acción visible) */

it('CP-RF06-08 la lista muestra una acción de edición visible en el período planificado', function () {
    $e = escenario();
    $planificado = periodoListo();
    actingAs($e['administrador']);

    $html = Livewire::test(Periodos::class)->assertSeeInOrder(['2027-I', 'Gestionar', '2026-II'])->html();

    // El nombre y el botón «Gestionar»: dos enlaces al detalle, no solo el nombre.
    expect(substr_count($html, 'href="'.route('periodos.show', $planificado).'"'))->toBe(2);
});
