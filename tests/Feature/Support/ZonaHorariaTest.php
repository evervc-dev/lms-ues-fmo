<?php

use App\Modules\Evaluaciones\Livewire\NuevaTarea;
use App\Modules\Evaluaciones\Livewire\Tarea;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * La app trabaja en la hora de El Salvador (ADR-041): lo que el docente escribe es lo que ve el estudiante.
 */
it('keeps the closing time the teacher types when the student sees the task', function () {
    $e = escenario();
    $componente = DbContext::system(fn () => DB::table('grading_components')->insertGetId([
        'section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now(),
    ]));
    $cierre = now()->addDays(2)->setTime(23, 59);
    actingAs($e['docente']);

    Livewire::test(NuevaTarea::class, ['seccion' => $e['ulid']])->set('titulo', 'Informe')->set('componente', $componente)->set('peso', 50)
        ->set('abre', now()->subHour()->format('Y-m-d\TH:i'))->set('cierra', $cierre->format('Y-m-d\TH:i'))->set('extensiones', 'pdf')
        ->call('guardar')->assertHasNoErrors();

    actingAs($e['estudiante']);
    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => DB::table('assessments')->value('ulid')])
        ->assertSeeText($cierre->format('d/m/Y').' 23:59');
    expect(Carbon::parse(DB::table('assessments')->value('closes_at'))->equalTo($cierre))->toBeTrue();
});

it('works in the El Salvador timezone, also in the PostgreSQL session', function () {
    expect(config('app.timezone'))->toBe('America/El_Salvador')
        ->and(config('database.connections.pgsql.timezone'))->toBe('America/El_Salvador');
});

it('stores the exact instant in PostgreSQL timestamptz', function () {
    $local = Carbon::parse('2026-11-20 23:59:00');

    expect(DB::selectOne('select (?::timestamptz at time zone \'UTC\')::text as utc', [$local->format('Y-m-d H:i:s')])->utc)->toBe('2026-11-21 05:59:00');
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La zona de la sesión solo aplica a PostgreSQL.');
