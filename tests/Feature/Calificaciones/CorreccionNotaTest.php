<?php

use App\Models\User;
use App\Modules\Calificaciones\Events\NotaCorregida;
use App\Modules\Calificaciones\Livewire\NotasSeccion;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Comunicacion\Notifications\Aviso;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Corrección de notas con el período cerrado (RF17, CP-RF17-07; Flujo Cálculo de nota final): solo el Coordinador de
| la carrera corrige, con motivo de al menos 5 caracteres; con el período abierto la nota la cambia el docente. La
| corrección recalcula el resultado congelado (`enrollment_results`) sin tocar su política ni su fecha, avisa al
| estudiante y queda en la auditoría (los triggers, solo en PostgreSQL, ADR-033).
*/

const CORRECCION_FROZEN_AT = '2026-12-12 10:00:00';

/**
 * Una actividad del 100 % en un cómputo del 100 % con la nota publicada de la matrícula del escenario (5.0). Con
 * `$cerrar`, congela el resultado (reprobado, con la política de 6.0) y cierra el período, como el cierre de RF34.
 *
 * @param  array<string, mixed>  $e
 */
function correccionNota(array $e, bool $cerrar = true, string $estado = 'published'): int
{
    return DbContext::system(function () use ($e, $cerrar, $estado) {
        $computo = DB::table('grading_components')->insertGetId(['section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27',
            'ends_on' => '2026-12-11', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now()]);
        $item = DB::table('grade_items')->insertGetId(['component_id' => $computo, 'section_id' => $e['seccion'], 'item_type' => 'manual', 'title' => 'Parcial 1',
            'weight_percent' => 100, 'is_published' => $estado === 'published', 'created_at' => now(), 'updated_at' => now()]);
        $nota = DB::table('grades')->insertGetId(['grade_item_id' => $item, 'enrollment_id' => $e['matricula'], 'section_id' => $e['seccion'], 'score' => 5,
            'status' => $estado, 'created_at' => now(), 'updated_at' => now()]);
        if ($cerrar) {
            DB::table('enrollment_results')->insert(['enrollment_id' => $e['matricula'], 'final_score' => 5, 'result' => 'failed', 'frozen_at' => CORRECCION_FROZEN_AT,
                'policy_snapshot' => json_encode(['passing_score' => 6.0, 'grade_rounding' => ['decimals' => 1, 'mode' => 'half_up'], 'missing_grade_policy' => 'zero', 'confirmed' => false])]);
            DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => 'closed']);
        }

        return $nota;
    });
}

/**
 * Actor por nombre: los del escenario, el coordinador de otra carrera (LIC, que solo tiene MAT115) o el docente
 * que imparte solo la otra sección.
 *
 * @param  array<string, mixed>  $e
 */
function correccionActor(array $e, string $quien): User
{
    if ($quien === 'docente ajeno') {
        return docenteAjeno($e);
    }
    if ($quien === 'coordinador ajeno') {
        $coordinador = usuarioCon('coordinador');
        DbContext::system(function () use ($e, $coordinador) {
            $carrera = DB::table('careers')->insertGetId(['department_id' => $e['dcnm'], 'code' => 'LIC', 'name' => 'Licenciatura en Matemática', 'created_at' => now(), 'updated_at' => now()]);
            $plan = DB::table('study_plans')->insertGetId(['career_id' => $carrera, 'name' => 'Plan LIC', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('career_subjects')->insert(['study_plan_id' => $plan, 'subject_id' => $e['mat'], 'cycle' => 1]);
            DB::table('career_coordinators')->insert(['user_id' => $coordinador->id, 'career_id' => $carrera, 'valid_from' => '2025-01-01', 'created_at' => now(), 'updated_at' => now()]);
        });

        return $coordinador;
    }

    return $e[$quien];
}

function correccionPuntaje(int $nota): float
{
    return (float) DB::table('grades')->where('id', $nota)->value('score');
}

/**
 * @return array<string, array<int, string>>
 */
function correccionErrores(Closure $accion): array
{
    try {
        $accion();
    } catch (ValidationException $excepcion) {
        return $excepcion->errors();
    }

    return [];
}

/* ------------------------------------------------------------------ Regla de negocio */

it('CP-RF17-07 con el período abierto el Coordinador no corrige: la nota la cambia el docente', function () {
    $e = escenario();
    $nota = correccionNota($e, cerrar: false);
    actingAs($e['coordinador']);

    $errores = correccionErrores(fn () => app(CalificacionService::class)->corregir($e['coordinador'], $nota, 8, 'Revisión de examen.'));

    expect($errores['nota'][0])->toBe('El período está abierto: la nota la cambia el docente de la sección.')
        ->and(correccionPuntaje($nota))->toEqual(5.0);
});

it('CP-RF17-07 exige un motivo de al menos 5 caracteres', function (string $motivo) {
    $e = escenario();
    $nota = correccionNota($e);
    actingAs($e['coordinador']);

    $errores = correccionErrores(fn () => app(CalificacionService::class)->corregir($e['coordinador'], $nota, 8, $motivo));

    expect($errores['motivo'][0])->toBe('Escriba el motivo de la corrección (al menos 5 caracteres).')
        ->and(correccionPuntaje($nota))->toEqual(5.0);
})->with(['vacío' => [''], '4 caracteres' => ['Nota'], 'solo espacios' => ['   ab   ']]);

it('CP-RF17-07 rechaza una nota fuera de 0 a 10', function (float $nueva) {
    $e = escenario();
    $nota = correccionNota($e);
    actingAs($e['coordinador']);

    $errores = correccionErrores(fn () => app(CalificacionService::class)->corregir($e['coordinador'], $nota, $nueva, 'Revisión de examen.'));

    expect($errores['nota'][0])->toBe('La nota debe estar entre 0 y 10.')->and(correccionPuntaje($nota))->toEqual(5.0);
})->with([-1, 10.5]);

it('CP-RF17-07 con el período cerrado el docente no cambia la nota y debe pedir la corrección al Coordinador', function () {
    $e = escenario();
    $nota = correccionNota($e);
    actingAs($e['docente']);

    $errores = correccionErrores(fn () => app(CalificacionService::class)->calificar($e['docente'], (int) DB::table('grades')->where('id', $nota)->value('grade_item_id'), $e['matricula'], 9));

    expect($errores['nota'][0])->toBe('El período está cerrado: pida la corrección al Coordinador.')->and(correccionPuntaje($nota))->toEqual(5.0);
});

/* ------------------------------------------------------------------ Permiso y alcance */

it('CP-RF17-07 solo el Coordinador de la carrera corrige: docente, docente ajeno, estudiante, jefe y coordinador ajeno reciben AuthorizationException', function (string $quien) {
    Event::fake([NotaCorregida::class]);
    $e = escenario();
    $nota = correccionNota($e);
    $actor = correccionActor($e, $quien);
    actingAs($actor);

    expect(fn () => app(CalificacionService::class)->corregir($actor, $nota, 9, 'Revisión de examen.'))->toThrow(AuthorizationException::class)
        ->and(correccionPuntaje($nota))->toEqual(5.0)
        ->and((float) DB::table('enrollment_results')->where('enrollment_id', $e['matricula'])->value('final_score'))->toEqual(5.0);
    Event::assertNotDispatched(NotaCorregida::class);
})->with(['docente', 'docente ajeno', 'estudiante', 'jefe', 'coordinador ajeno']);

/* ------------------------------------------------------------------ Corrección válida */

it('CP-RF17-07 el Coordinador corrige con el período cerrado y recalcula el resultado sin tocar la política ni la fecha de congelado', function () {
    $e = escenario();
    $nota = correccionNota($e);
    $antes = DB::table('enrollment_results')->where('enrollment_id', $e['matricula'])->first();
    actingAs($e['coordinador']);

    app(CalificacionService::class)->corregir($e['coordinador'], $nota, 6.5, '  Revisión de examen.  ');

    $resultado = DB::table('enrollment_results')->where('enrollment_id', $e['matricula'])->first();
    expect(correccionPuntaje($nota))->toEqual(6.5)
        ->and((float) $resultado->final_score)->toEqual(6.5)
        ->and($resultado->result)->toBe('passed')
        ->and($resultado->policy_snapshot)->toBe($antes->policy_snapshot)
        ->and((string) $resultado->frozen_at)->toBe((string) $antes->frozen_at);
});

it('CP-RF17-07 el resultado recalculado usa la nota mínima de su policy_snapshot, no la configuración actual', function () {
    $e = escenario();
    $nota = correccionNota($e);
    // La mínima cambió después del cierre: el resultado congelado se juzga con la de su política (6.0).
    DbContext::system(fn () => DB::table('system_settings')->where('key', 'passing_score')->update(['value' => json_encode(7.0)]));
    actingAs($e['coordinador']);

    app(CalificacionService::class)->corregir($e['coordinador'], $nota, 6.5, 'Revisión de examen.');

    expect(DB::table('enrollment_results')->where('enrollment_id', $e['matricula'])->value('result'))->toBe('passed');
});

it('CP-RF17-07 una corrección que sigue bajo la mínima con el período cerrado no abre alerta de riesgo ni avisa «Riesgo de reprobar»', function () {
    Notification::fake();
    $e = escenario();
    $nota = correccionNota($e);
    actingAs($e['coordinador']);

    app(CalificacionService::class)->corregir($e['coordinador'], $nota, 5.5, 'Revisión de examen.');

    expect(DB::table('academic_alerts')->where('alert_type', 'grade_risk')->count())->toBe(0);
    Notification::assertSentTo($e['estudiante'], Aviso::class, fn (Aviso $aviso) => $aviso->evento === 'grade.published');
    Notification::assertNotSentTo($e['estudiante'], Aviso::class, fn (Aviso $aviso) => $aviso->evento === 'academic_alert.opened');
});

it('CP-RF17-07 sin resultado congelado la corrección cambia la nota y no crea un resultado', function () {
    $e = escenario();
    $nota = correccionNota($e);
    DbContext::system(fn () => DB::table('enrollment_results')->delete());
    actingAs($e['coordinador']);

    app(CalificacionService::class)->corregir($e['coordinador'], $nota, 6.5, 'Revisión de examen.');

    expect(correccionPuntaje($nota))->toEqual(6.5)->and(DB::table('enrollment_results')->count())->toBe(0);
});

it('CP-RF17-07 el estudiante recibe el aviso de la nota corregida con el valor anterior, el nuevo y el motivo', function () {
    Notification::fake();
    $e = escenario();
    $nota = correccionNota($e);
    actingAs($e['coordinador']);

    app(CalificacionService::class)->corregir($e['coordinador'], $nota, 6.5, 'Revisión de examen.');

    Notification::assertSentTo($e['estudiante'], Aviso::class, fn (Aviso $aviso) => $aviso->evento === 'grade.published'
        && $aviso->titulo === 'Nota corregida: Parcial 1'
        && $aviso->cuerpo === 'Su nota cambió de 5.0 a 6.5. Motivo: Revisión de examen.'
        && $aviso->queue === 'notificaciones');
    Notification::assertNotSentTo($e['coordinador'], Aviso::class);
});

it('CP-RF17-07 una nota en borrador corregida no avisa al estudiante', function () {
    Event::fake([NotaCorregida::class]);
    $e = escenario();
    $nota = correccionNota($e, estado: 'draft');
    actingAs($e['coordinador']);

    app(CalificacionService::class)->corregir($e['coordinador'], $nota, 6.5, 'Revisión de examen.');

    expect(correccionPuntaje($nota))->toEqual(6.5);
    Event::assertNotDispatched(NotaCorregida::class);
});

it('CP-RF17-07 la pantalla de notas corrige con motivo y muestra el error del motivo corto en su campo', function () {
    $e = escenario();
    $nota = correccionNota($e);
    actingAs($e['coordinador']);

    Livewire::test(NotasSeccion::class, ['seccion' => $e['ulid']])
        ->call('abrirCorreccion', $nota)->set('nuevaNota', 6.5)->set('motivo', 'no')->call('corregir')->assertHasErrors(['motivo'])
        ->set('motivo', 'Revisión de examen.')->call('corregir')->assertHasNoErrors()->assertDispatched('aviso', tipo: 'exito');

    expect(correccionPuntaje($nota))->toEqual(6.5);
});

/* ------------------------------------------------------------------ Auditoría (PostgreSQL) */

it('CP-RF17-07 la corrección queda auditada con el motivo y el trigger rechaza escribir notas del período cerrado sin ella', function () {
    $e = escenario();
    $nota = correccionNota($e);
    actingAs($e['coordinador']);

    app(CalificacionService::class)->corregir($e['coordinador'], $nota, 6.5, 'Revisión de examen.');

    DbContext::auditScope(all: true);
    $evento = DB::table('audit_logs')->where('entity_type', 'grades')->where('entity_id', $nota)->where('event_type', 'grade.corrected')->sole();
    expect((int) $evento->actor_user_id)->toBe($e['coordinador']->id)
        ->and($evento->reason)->toBe('Revisión de examen.')
        ->and((float) json_decode($evento->new_values, true)['score'])->toEqual(6.5)
        ->and(fn () => DbContext::run(fn () => DB::table('grades')->where('id', $nota)->update(['score' => 9]), 'grade.saved'))
        ->toThrow(QueryException::class)
        ->and(correccionPuntaje($nota))->toEqual(6.5);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La auditoría y el bloqueo del período cerrado son triggers de PostgreSQL (ADR-033).');
