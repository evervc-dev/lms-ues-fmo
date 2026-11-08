<?php

use App\Modules\Academica\Livewire\PeriodoDetalle;
use App\Modules\Academica\Services\PeriodoService;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Cierre del período (RF34, RF17): autorización departamental previa, congelamiento de resultados y bloqueo posterior.
 */

/**
 * Tres cómputos (30/30/40) en la sección del escenario, cada uno con una actividad publicada de peso 100.
 *
 * @param  array<string, mixed>  $e
 * @return array<int, int> id de la actividad de cada cómputo, en orden
 */
function cierreActividades(array $e): array
{
    return DbContext::system(function () use ($e) {
        return collect([[1, 30], [2, 30], [3, 40]])->map(function (array $c) use ($e) {
            $componente = DB::table('grading_components')->insertGetId([
                'section_id' => $e['seccion'], 'number' => $c[0], 'name' => "Cómputo {$c[0]}", 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11',
                'weight_percent' => $c[1], 'created_at' => now(), 'updated_at' => now(),
            ]);

            return DB::table('grade_items')->insertGetId([
                'component_id' => $componente, 'section_id' => $e['seccion'], 'item_type' => 'manual', 'title' => "Actividad {$c[0]}",
                'weight_percent' => 100, 'is_published' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        })->all();
    });
}

/**
 * Registra las notas publicadas de una matrícula; un null deja la actividad sin nota.
 *
 * @param  array<string, mixed>  $e
 * @param  array<int, int>  $actividades
 * @param  array<int, float|null>  $notas
 */
function cierreNotas(array $e, array $actividades, int $matricula, array $notas, ?int $seccion = null): void
{
    DbContext::system(function () use ($e, $actividades, $matricula, $notas, $seccion) {
        foreach ($notas as $i => $nota) {
            if ($nota === null) {
                continue;
            }
            DB::table('grades')->insert([
                'grade_item_id' => $actividades[$i], 'enrollment_id' => $matricula, 'section_id' => $seccion ?? $e['seccion'], 'score' => $nota,
                'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    });
}

/**
 * Matrícula de un estudiante nuevo en la sección indicada (por defecto la del escenario).
 *
 * @param  array<string, mixed>  $e
 */
function cierreMatricula(array $e, string $estado = 'active', bool $otraSeccion = false): int
{
    $estudiante = usuarioCon('estudiante');

    return DbContext::system(fn () => DB::table('enrollments')->insertGetId([
        'student_id' => $estudiante->id, 'section_id' => $otraSeccion ? $e['otra'] : $e['seccion'], 'period_id' => $e['periodo'],
        'subject_id' => $otraSeccion ? $e['mat'] : $e['prn'], 'status' => $estado, 'enrolled_via' => 'admin', 'enrolled_by' => $e['administrador']->id,
        'created_at' => now(), 'updated_at' => now(),
    ]));
}

/**
 * Autoriza el cierre de los departamentos indicados, como el Jefe.
 *
 * @param  array<string, mixed>  $e
 */
function cierreAutorizar(array $e, int ...$departamentos): void
{
    DbContext::system(function () use ($e, $departamentos) {
        foreach ($departamentos as $departamento) {
            DB::table('department_closures')->insert([
                'period_id' => $e['periodo'], 'department_id' => $departamento, 'authorized_by' => $e['jefe']->id,
                'authorized_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    });
}

/**
 * Departamento sin ninguna sección en el período: no debe bloquear el cierre.
 */
function cierreDepartamentoVacio(): int
{
    return DbContext::system(fn () => DB::table('departments')->insertGetId(['code' => 'VAC', 'name' => 'Departamento sin secciones', 'created_at' => now(), 'updated_at' => now()]));
}

/**
 * Cierra el período como el actor y devuelve los errores de validación (vacío si cerró).
 *
 * @return array<string, array<int, string>>
 */
function cierreErrores(object $actor, int $periodo): array
{
    actingAs($actor);
    try {
        app(PeriodoService::class)->cerrar($actor, $periodo);
    } catch (ValidationException $excepcion) {
        return $excepcion->errors();
    }

    return [];
}

it('CP-RF34-07 CP-RF06-03 rechaza el cierre mientras falten departamentos con secciones por autorizar y los lista por nombre', function () {
    $e = escenario();
    cierreAutorizar($e, $e['dia']);

    $errores = cierreErrores($e['administrador'], $e['periodo']);

    expect($errores)->toHaveKey('cierre')
        ->and($errores['cierre'][0])->toStartWith('Faltan departamentos por autorizar:')->toContain('Ciencias Naturales y Matemática')->not->toContain('Ingeniería y Arquitectura')
        ->and(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('active')
        ->and(DB::table('enrollment_results')->count())->toBe(0);

    DbContext::system(fn () => DB::table('department_closures')->delete());
    $todos = cierreErrores($e['administrador'], $e['periodo'])['cierre'][0];

    expect($todos)->toContain('Ciencias Naturales y Matemática')->toContain('Ingeniería y Arquitectura');
});

it('CP-RF34-07 no exige la autorización de un departamento sin secciones en el período', function () {
    $e = escenario();
    cierreDepartamentoVacio();
    cierreAutorizar($e, $e['dia'], $e['dcnm']);

    expect(cierreErrores($e['administrador'], $e['periodo']))->toBe([])
        ->and(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('closed');
});

it('CP-RF34-07 marca todosAutorizados solo sobre los departamentos con secciones y cuenta las secciones de cada uno', function () {
    $e = escenario();
    cierreDepartamentoVacio();
    actingAs($e['administrador']);
    $periodos = app(PeriodoService::class);

    cierreAutorizar($e, $e['dia']);
    expect($periodos->detalle($e['administrador'], $e['periodo'])['todosAutorizados'])->toBeFalse();

    cierreAutorizar($e, $e['dcnm']);
    $detalle = $periodos->detalle($e['administrador'], $e['periodo']);

    expect($detalle['todosAutorizados'])->toBeTrue()
        ->and((int) $detalle['cierres']->firstWhere('code', 'DIA')->secciones)->toBe(1)
        ->and((int) $detalle['cierres']->firstWhere('code', 'DCNM')->secciones)->toBe(1)
        ->and((int) ($detalle['cierres']->firstWhere('code', 'VAC')?->secciones ?? 0))->toBe(0);
});

it('CP-RF34-08 permite cerrar el período al Administrador cuando todos los departamentos autorizaron', function () {
    $e = escenario();
    cierreAutorizar($e, $e['dia'], $e['dcnm']);
    actingAs($e['administrador']);

    app(PeriodoService::class)->cerrar($e['administrador'], $e['periodo']);

    expect(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('closed');
});

it('CP-RF34-08 niega el cierre del período a los roles sin periodos.gestionar', function (string $rol) {
    $e = escenario();
    cierreAutorizar($e, $e['dia'], $e['dcnm']);
    actingAs($e[$rol]);

    expect(fn () => app(PeriodoService::class)->cerrar($e[$rol], $e['periodo']))->toThrow(AuthorizationException::class)
        ->and(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('active')
        ->and(DB::table('enrollment_results')->count())->toBe(0);
})->with(['jefe', 'coordinador', 'docente', 'estudiante', 'soporte']);

it('CP-RF34-08 responde 404 si el período no existe', function () {
    $e = escenario();
    actingAs($e['administrador']);

    expect(fn () => app(PeriodoService::class)->cerrar($e['administrador'], 999999))->toThrow(NotFoundHttpException::class);
});

it('CP-RF34-08 cierra el período, congela una fila por matrícula activa y devuelve cuántas congeló', function () {
    $e = escenario();
    $actividades = cierreActividades($e);
    cierreNotas($e, $actividades, $e['matricula'], [9, 8, 7]);
    $otra = cierreMatricula($e, otraSeccion: true);
    $retirada = cierreMatricula($e, 'withdrawn');
    cierreNotas($e, $actividades, $retirada, [10, 10, 10]);
    cierreAutorizar($e, $e['dia'], $e['dcnm']);
    actingAs($e['administrador']);

    $congeladas = app(PeriodoService::class)->cerrar($e['administrador'], $e['periodo']);

    expect($congeladas)->toBe(2)
        ->and(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('closed')
        ->and(DB::table('enrollment_results')->pluck('enrollment_id')->map(fn ($id) => (int) $id)->sort()->values()->all())->toBe([$e['matricula'], $otra])
        ->and(DB::table('enrollment_results')->where('enrollment_id', $retirada)->exists())->toBeFalse();
});

it('CP-RF17-04 guarda la nota final y el resultado aprobado o reprobado de cada matrícula al cerrar', function () {
    $e = escenario();
    $actividades = cierreActividades($e);
    // Ponderado 30/30/40; la actividad sin nota cuenta 0 y el redondeo es mitad hacia arriba a 1 decimal.
    $casos = [
        'completa aprobada' => [[9, 8, 7], 7.9, 'passed'],
        'actividad sin nota cuenta 0' => [[10, 5, null], 4.5, 'failed'],
        'redondea 5.95 hacia arriba' => [[6.5, 5, 6.25], 6.0, 'passed'],
        'justo en el mínimo' => [[6, 6, 6], 6.0, 'passed'],
        'justo bajo el mínimo' => [[6, 6, 5.5], 5.8, 'failed'],
        'sin ninguna nota' => [[null, null, null], 0.0, 'failed'],
    ];
    $matriculas = [];
    foreach ($casos as $nombre => [$notas]) {
        $matriculas[$nombre] = $nombre === 'completa aprobada' ? $e['matricula'] : cierreMatricula($e);
        cierreNotas($e, $actividades, $matriculas[$nombre], $notas);
    }
    cierreAutorizar($e, $e['dia'], $e['dcnm']);
    actingAs($e['administrador']);

    app(PeriodoService::class)->cerrar($e['administrador'], $e['periodo']);

    foreach ($casos as $nombre => [, $final, $resultado]) {
        $fila = DB::table('enrollment_results')->where('enrollment_id', $matriculas[$nombre])->first();
        expect((float) $fila->final_score)->toEqual($final, $nombre)
            ->and($fila->result)->toBe($resultado, $nombre);
    }
});

it('CP-RF17-04 guarda la política aplicada en policy_snapshot y la fecha de congelamiento', function () {
    $e = escenario();
    $actividades = cierreActividades($e);
    cierreNotas($e, $actividades, $e['matricula'], [9, 8, 7]);
    cierreAutorizar($e, $e['dia'], $e['dcnm']);
    actingAs($e['administrador']);
    $this->travelTo(Carbon::parse('2026-12-12 10:00:00'));

    app(PeriodoService::class)->cerrar($e['administrador'], $e['periodo']);

    $fila = DB::table('enrollment_results')->where('enrollment_id', $e['matricula'])->first();
    $politica = json_decode($fila->policy_snapshot, true);

    expect((float) $politica['passing_score'])->toEqual(6.0)
        ->and($politica['grade_rounding'])->toBe(['decimals' => 1, 'mode' => 'half_up'])
        ->and($politica['missing_grade_policy'])->toBe('zero')
        ->and($politica['confirmed'])->toBeFalse()
        ->and(collect($politica['components'])->map(fn ($c) => [(int) $c['number'], $c['name'], (float) $c['weight_percent']])->all())
        ->toEqual([[1, 'Cómputo 1', 30.0], [2, 'Cómputo 2', 30.0], [3, 'Cómputo 3', 40.0]])
        ->and($fila->frozen_at)->not->toBeNull()
        ->and(abs(Carbon::parse($fila->frozen_at)->diffInSeconds(now())))->toBeLessThan(5);
});

it('CP-RF17-04 el resultado y el policy_snapshot siguen la nota mínima configurada en system_settings', function () {
    $e = escenario();
    $actividades = cierreActividades($e);
    cierreNotas($e, $actividades, $e['matricula'], [9, 8, 7]);
    cierreAutorizar($e, $e['dia'], $e['dcnm']);
    actingAs($e['administrador']);
    DbContext::system(fn () => DB::table('system_settings')->where('key', 'passing_score')->update(['value' => json_encode(8.0), 'is_confirmed' => true]));

    app(PeriodoService::class)->cerrar($e['administrador'], $e['periodo']);

    $fila = DB::table('enrollment_results')->where('enrollment_id', $e['matricula'])->first();
    $politica = json_decode($fila->policy_snapshot, true);

    expect((float) $fila->final_score)->toEqual(7.9)
        ->and($fila->result)->toBe('failed')
        ->and((float) $politica['passing_score'])->toEqual(8.0)
        ->and($politica['confirmed'])->toBeTrue();
});

it('CP-RF34-08 no cierra un período que no está en curso ni lo cierra dos veces', function (string $estado) {
    $e = escenario();
    cierreAutorizar($e, $e['dia'], $e['dcnm']);
    DbContext::system(fn () => DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => $estado]));

    expect(cierreErrores($e['administrador'], $e['periodo']))->toHaveKey('estado')
        ->and(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe($estado)
        ->and(DB::table('enrollment_results')->count())->toBe(0);
})->with(['planned', 'closed']);

it('CP-RF34-08 rechaza un segundo cierre del mismo período sin tocar los resultados ya congelados', function () {
    $e = escenario();
    cierreNotas($e, cierreActividades($e), $e['matricula'], [9, 8, 7]);
    cierreAutorizar($e, $e['dia'], $e['dcnm']);

    expect(cierreErrores($e['administrador'], $e['periodo']))->toBe([]);
    $primero = DB::table('enrollment_results')->first();

    expect(cierreErrores($e['administrador'], $e['periodo']))->toHaveKey('estado')
        ->and(DB::table('enrollment_results')->count())->toBe(1)
        ->and(DB::table('enrollment_results')->first()->frozen_at)->toBe($primero->frozen_at);
});

it('CP-RF17-04 congela los resultados de forma idempotente: una fila por matrícula aunque se repita', function () {
    $e = escenario();
    $actividades = cierreActividades($e);
    cierreNotas($e, $actividades, $e['matricula'], [9, 8, 7]);
    $segunda = cierreMatricula($e);
    cierreNotas($e, $actividades, $segunda, [4, 4, 4]);
    $servicio = app(CalificacionService::class);

    $primera = DbContext::system(fn () => $servicio->congelarResultados($e['periodo']));
    $repetida = DbContext::system(fn () => $servicio->congelarResultados($e['periodo']));

    expect($primera)->toBe(2)->and($repetida)->toBe(2)
        ->and(DB::table('enrollment_results')->count())->toBe(2)
        ->and((float) DB::table('enrollment_results')->where('enrollment_id', $segunda)->value('final_score'))->toEqual(4.0);

    DbContext::system(fn () => DB::table('grades')->where('enrollment_id', $segunda)->update(['score' => 8]));
    DbContext::system(fn () => $servicio->congelarResultados($e['periodo']));

    expect(DB::table('enrollment_results')->count())->toBe(2)
        ->and((float) DB::table('enrollment_results')->where('enrollment_id', $segunda)->value('final_score'))->toEqual(8.0)
        ->and(DB::table('enrollment_results')->where('enrollment_id', $segunda)->value('result'))->toBe('passed');
});

it('CP-RF34-07 muestra el botón Cerrar período solo con el período en curso', function (string $estado, bool $visible) {
    $e = escenario();
    DbContext::system(fn () => DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => $estado]));
    actingAs($e['administrador']);

    $pantalla = Livewire::test(PeriodoDetalle::class, ['periodo' => $e['periodo']]);

    $visible ? $pantalla->assertSee('Cerrar período') : $pantalla->assertDontSee('Cerrar período');
})->with([
    'en curso' => ['active', true],
    'planificado' => ['planned', false],
    'cerrado' => ['closed', false],
]);

it('CP-RF34-07 muestra el error de departamentos pendientes al intentar cerrar desde el detalle del período', function () {
    $e = escenario();
    cierreAutorizar($e, $e['dia']);
    actingAs($e['administrador']);

    Livewire::test(PeriodoDetalle::class, ['periodo' => $e['periodo']])->call('cerrar')
        ->assertDispatched('aviso', fn (string $evento, array $parametros) => ($parametros['tipo'] ?? null) === 'error'
            && str_contains($parametros['mensaje'] ?? '', 'Faltan departamentos por autorizar:')
            && str_contains($parametros['mensaje'], 'Ciencias Naturales y Matemática'));

    expect(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('active');
});

it('CP-RF34-08 cierra el período desde el detalle cuando todos los departamentos autorizaron', function () {
    $e = escenario();
    cierreNotas($e, cierreActividades($e), $e['matricula'], [9, 8, 7]);
    cierreAutorizar($e, $e['dia'], $e['dcnm']);
    actingAs($e['administrador']);

    Livewire::test(PeriodoDetalle::class, ['periodo' => $e['periodo']])->call('cerrar')
        ->assertDispatched('aviso', tipo: 'exito')
        ->assertDontSee('Cerrar período');

    expect(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('closed')
        ->and(DB::table('enrollment_results')->count())->toBe(1);
});

it('CP-RF34-08 no deja entrar al detalle del período a roles sin periodos.gestionar', function (string $rol) {
    $e = escenario();

    actingAs($e[$rol])->get(route('periodos.show', $e['periodo']))->assertForbidden();
})->with(['jefe', 'coordinador', 'docente', 'estudiante']);

it('CP-RF34-08 rechaza una nota nueva en un período cerrado (AC203)', function () {
    $e = escenario();
    $actividades = cierreActividades($e);
    $sinNota = cierreMatricula($e);
    cierreAutorizar($e, $e['dia'], $e['dcnm']);
    actingAs($e['administrador']);
    app(PeriodoService::class)->cerrar($e['administrador'], $e['periodo']);

    expect(fn () => cierreNotas($e, $actividades, $sinNota, [7, null, null]))->toThrow(QueryException::class, 'AC203');
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'Requiere PostgreSQL');

it('CP-RF34-08 audita el cierre del período en academic_periods', function () {
    $e = escenario();
    cierreAutorizar($e, $e['dia'], $e['dcnm']);
    actingAs($e['administrador']);

    app(PeriodoService::class)->cerrar($e['administrador'], $e['periodo']);

    DbContext::auditScope(all: true);
    $auditoria = DB::table('audit_logs')->where('entity_type', 'academic_periods')->where('entity_id', $e['periodo'])->where('action', 'update')->get();

    expect($auditoria)->toHaveCount(1)
        ->and((int) $auditoria->first()->actor_user_id)->toBe($e['administrador']->id)
        ->and(json_decode($auditoria->first()->old_values, true)['status'])->toBe('active')
        ->and(json_decode($auditoria->first()->new_values, true)['status'])->toBe('closed');
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'Requiere PostgreSQL');
