<?php

use App\Models\User;
use App\Modules\Academica\Services\MatriculaService;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Calificaciones\Services\ComputoService;
use App\Modules\Calificaciones\Services\RiesgoService;
use App\Modules\Evaluaciones\Services\ActividadService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Lectura por cadena de un cambio de grupo (RF09 · CP-RF09-08, RF17, RF34; ADR-062): no se mueve ninguna fila, la
| matrícula nueva lee las notas de la anterior caminando `enrollments.transferred_to_id` hacia atrás. Se hereda **por
| cómputo** (`grading_components.number`), solo con notas **publicadas** y solo cuando el grupo nuevo no tiene ninguna
| nota en ese cómputo. La asistencia no se encadena (ADR-062, punto 5).
*/

// El plazo de cambio de grupo son las 3 primeras semanas del período (2026-07-27 → 2026-08-17).
beforeEach(fn () => $this->travelTo('2026-08-10 10:00'));

function cadenaServicio(): CalificacionService
{
    return app(CalificacionService::class);
}

/**
 * Otro grupo de PRN115 en el período del escenario, con horario y con el docente del escenario como responsable
 * (así puede calificar en los dos grupos).
 *
 * @param  array<string, mixed>  $e
 */
function cadenaGrupo(array $e, string $codigo, string $inicio, string $fin): int
{
    return DbContext::system(function () use ($e, $codigo, $inicio, $fin) {
        $id = DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $e['periodo'], 'subject_id' => $e['prn'], 'code' => $codigo,
            'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('section_schedules')->insert(['section_id' => $id, 'period_id' => $e['periodo'], 'weekday' => 1, 'start_time' => $inicio, 'end_time' => $fin,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('teaching_assignments')->insert(['section_id' => $id, 'teacher_id' => $e['docente']->id, 'is_responsible' => true, 'started_at' => '2026-07-01',
            'created_at' => now(), 'updated_at' => now()]);

        return $id;
    });
}

/**
 * Los dos cómputos de 50 % del período copiados a la sección (ComputoService::copiarPlantilla()), cada uno con una
 * actividad manual del 100 % sin publicar. Todos los grupos comparten el `number` del cómputo porque copian la
 * misma plantilla: es lo que hace comparable la cadena (ADR-062).
 *
 * @param  array<string, mixed>  $e
 * @return array<int, int> number del cómputo => grade_item_id
 */
function cadenaActividades(array $e, int $seccionId): array
{
    return DbContext::system(function () use ($e, $seccionId) {
        foreach ([1, 2] as $numero) {
            DB::table('grading_component_templates')->insertOrIgnore([
                'period_id' => $e['periodo'], 'number' => $numero, 'name' => "Cómputo {$numero}", 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11',
                'weight_percent' => 50, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        app(ComputoService::class)->copiarPlantilla($seccionId);

        return DB::table('grading_components')->where('section_id', $seccionId)->orderBy('number')->get()
            ->mapWithKeys(fn (object $c) => [(int) $c->number => DB::table('grade_items')->insertGetId([
                'component_id' => $c->id, 'section_id' => $seccionId, 'item_type' => 'manual', 'title' => "Parcial {$c->number}",
                'weight_percent' => 100, 'is_published' => false, 'created_at' => now(), 'updated_at' => now(),
            ])])->all();
    });
}

/**
 * El docente califica la matrícula en la actividad y, si se le pide, publica la actividad (la nota queda `published`).
 *
 * @param  array<string, mixed>  $e
 */
function cadenaNota(array $e, int $actividad, int $matricula, float $nota, bool $publicar = true): void
{
    actingAs($e['docente']);
    cadenaServicio()->calificar($e['docente'], $actividad, $matricula, $nota);
    if ($publicar) {
        cadenaServicio()->publicar($e['docente'], $actividad);
    }
}

/**
 * El camino real del cambio de grupo (RF09): el estudiante lo pide y el Coordinador lo aprueba. Devuelve la
 * matrícula nueva.
 *
 * @param  array<string, mixed>  $e
 */
function cadenaTraslado(array $e, int $matriculaId, int $destino): int
{
    actingAs($e['estudiante']);
    app(MatriculaService::class)->solicitarCambio($e['estudiante'], $matriculaId, $destino, 'Trabajo por las mañanas');
    actingAs($e['coordinador']);
    app(MatriculaService::class)->aprobarCambio($e['coordinador'], (int) DB::table('section_change_requests')
        ->where('enrollment_id', $matriculaId)->where('status', 'pending')->value('id'));

    return (int) DB::table('enrollments')->where('id', $matriculaId)->value('transferred_to_id');
}

/**
 * Un compañero matriculado en la sección desde el principio, que nunca cambió de grupo: el contraste de la cadena.
 *
 * @param  array<string, mixed>  $e
 * @return array{estudiante: User, matricula: int}
 */
function cadenaCompanero(array $e, int $seccionId): array
{
    $estudiante = usuarioCon('estudiante');
    $matricula = DbContext::system(fn () => DB::table('enrollments')->insertGetId([
        'student_id' => $estudiante->id, 'section_id' => $seccionId, 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
        'status' => 'active', 'enrolled_via' => 'admin', 'enrolled_by' => $e['coordinador']->id, 'created_at' => now(), 'updated_at' => now(),
    ]));

    return ['estudiante' => $estudiante, 'matricula' => (int) $matricula];
}

it('CP-RF09-08 la nota del grupo nuevo manda sobre la heredada en cuanto el docente la califica', function () {
    $e = escenario();
    $dos = cadenaGrupo($e, '02', '13:00', '14:40');
    $uno = cadenaActividades($e, $e['seccion']);
    $nuevas = cadenaActividades($e, $dos);
    cadenaNota($e, $uno[1], $e['matricula'], 8);
    $nueva = cadenaTraslado($e, $e['matricula'], $dos);

    cadenaNota($e, $nuevas[1], $nueva, 5);

    $boleta = cadenaServicio()->boleta($e['estudiante'], $dos);
    $computo1 = $boleta['computos']->firstWhere('number', 1);
    expect($boleta['heredadas']->has(1))->toBeFalse()
        ->and(cadenaServicio()->notaComputo($computo1, $boleta['notas'], true, $boleta['heredadas']))->toEqual(5.0)
        // Con la heredada (8.0) la final sería 4.0; manda la del grupo nuevo: (50 × 5 + 50 × 0) / 100.
        ->and($boleta['final'])->toEqual(2.5)
        // La cadena cruda sigue trayendo el cómputo: lo que desaparece es la herencia efectiva.
        ->and(cadenaServicio()->notasDeCadena($nueva)->get(1)->nota)->toEqual(8.0);

    actingAs($e['estudiante']);
    $misComputos = cadenaServicio()->misNotas($e['estudiante'])['materias']->sole()->computos;
    expect($misComputos[0]->nota)->toEqual(5.0)->and($misComputos[0]->heredadoDe)->toBeNull();
});

it('CP-RF09-08 una nota en borrador del grupo anterior no se hereda; publicarla la trae', function () {
    $e = escenario();
    $dos = cadenaGrupo($e, '02', '13:00', '14:40');
    $uno = cadenaActividades($e, $e['seccion']);
    cadenaActividades($e, $dos);
    cadenaNota($e, $uno[1], $e['matricula'], 8, publicar: false);
    $nueva = cadenaTraslado($e, $e['matricula'], $dos);

    expect(DB::table('grades')->where('enrollment_id', $e['matricula'])->value('status'))->toBe('draft')
        ->and(cadenaServicio()->notasDeCadena($nueva))->toBeEmpty()
        ->and(cadenaServicio()->boleta($e['estudiante'], $dos)['final'])->toEqual(0.0);

    actingAs($e['docente']);
    cadenaServicio()->publicar($e['docente'], $uno[1]);

    $boleta = cadenaServicio()->boleta($e['estudiante'], $dos);
    expect($boleta['heredadas']->get(1)->nota)->toEqual(8.0)
        ->and($boleta['heredadas']->get(1)->grupo)->toBe('01')
        ->and($boleta['final'])->toEqual(4.0);
});

it('CP-RF09-08 sin cambio de grupo la cadena está vacía y la nota final es la de siempre', function () {
    $e = escenario();
    $uno = cadenaActividades($e, $e['seccion']);
    cadenaNota($e, $uno[1], $e['matricula'], 8);
    cadenaNota($e, $uno[2], $e['matricula'], 6);

    $boleta = cadenaServicio()->boleta($e['estudiante'], $e['seccion']);

    expect(cadenaServicio()->cadena($e['matricula']))->toBeEmpty()
        ->and(cadenaServicio()->notasDeCadena($e['matricula']))->toBeEmpty()
        ->and(cadenaServicio()->cadenasDeSeccion([$e['matricula']])->has($e['matricula']))->toBeFalse()
        ->and($boleta['heredadas'])->toBeEmpty()
        ->and($boleta['final'])->toEqual(7.0)
        ->and($boleta['final'])->toEqual(cadenaServicio()->notaFinal($boleta['computos'], $boleta['notas'], soloPublicadas: true));
});

it('CP-RF09-08 con dos cambios de grupo hereda del grupo más reciente que tenga la nota del cómputo', function () {
    $e = escenario();
    $dos = cadenaGrupo($e, '02', '13:00', '14:40');
    $tres = cadenaGrupo($e, '03', '15:00', '16:40');
    $uno = cadenaActividades($e, $e['seccion']);
    $delDos = cadenaActividades($e, $dos);
    cadenaActividades($e, $tres);
    cadenaNota($e, $uno[1], $e['matricula'], 8);
    $enDos = cadenaTraslado($e, $e['matricula'], $dos);
    $enTres = cadenaTraslado($e, $enDos, $tres);

    expect(cadenaServicio()->cadena($enTres)->pluck('grupo')->all())->toBe(['02', '01'])
        ->and(cadenaServicio()->boleta($e['estudiante'], $tres)['heredadas']->get(1))
        ->nota->toEqual(8.0)->grupo->toBe('01')
        ->and(cadenaServicio()->boleta($e['estudiante'], $tres)['final'])->toEqual(4.0);

    // El docente del grupo 02 califica el cómputo 1 que allí había quedado sin nota: pasa a ser el más reciente.
    cadenaNota($e, $delDos[1], $enDos, 4);

    $boleta = cadenaServicio()->boleta($e['estudiante'], $tres);
    expect($boleta['heredadas']->get(1))->nota->toEqual(4.0)->grupo->toBe('02')
        ->and($boleta['final'])->toEqual(2.0);
});

it('CP-RF09-08 CP-RF34-08 congela la nota final con la herencia y anota en el policy_snapshot de qué grupo viene', function () {
    $e = escenario();
    $dos = cadenaGrupo($e, '02', '13:00', '14:40');
    $uno = cadenaActividades($e, $e['seccion']);
    $delDos = cadenaActividades($e, $dos);
    $companero = cadenaCompanero($e, $e['seccion']);
    // Las dos notas del cómputo 1 quedan publicadas con la misma actividad, como en la pantalla del docente.
    cadenaNota($e, $uno[1], $e['matricula'], 8, publicar: false);
    cadenaNota($e, $uno[1], $companero['matricula'], 5);
    $nueva = cadenaTraslado($e, $e['matricula'], $dos);
    cadenaNota($e, $delDos[2], $nueva, 6);

    DbContext::system(fn () => cadenaServicio()->congelarResultados($e['periodo']));

    $trasladado = DB::table('enrollment_results')->where('enrollment_id', $nueva)->first();
    $politica = json_decode((string) $trasladado->policy_snapshot, true);
    expect((float) $trasladado->final_score)->toEqual(7.0)
        ->and($trasladado->result)->toBe('passed')
        // `score` viaja en el JSON como número, así que se compara con toEqual (8 == 8.0).
        ->and($politica['inherited_from'])->toEqual([[
            'component_number' => 1, 'group' => '01', 'section_id' => $e['seccion'], 'enrollment_id' => $e['matricula'], 'score' => 8.0,
        ]]);

    // Quien no se trasladó conserva el formato de siempre: su snapshot no habla de herencias.
    $suyo = DB::table('enrollment_results')->where('enrollment_id', $companero['matricula'])->first();
    expect((float) $suyo->final_score)->toEqual(2.5)
        ->and(json_decode((string) $suyo->policy_snapshot, true))->not->toHaveKey('inherited_from')
        ->and(DB::table('enrollment_results')->where('enrollment_id', $e['matricula'])->exists())->toBeFalse();
});

it('CP-RF09-08 CP-RF17-05 el cómputo heredado no marca en riesgo a quien cambió de grupo', function () {
    Notification::fake();
    $e = escenario();
    $dos = cadenaGrupo($e, '02', '13:00', '14:40');
    $uno = cadenaActividades($e, $e['seccion']);
    $delDos = cadenaActividades($e, $dos);
    $companero = cadenaCompanero($e, $dos);
    cadenaNota($e, $uno[1], $e['matricula'], 8);
    $nueva = cadenaTraslado($e, $e['matricula'], $dos);
    // En el grupo 02 el cómputo 1 está publicado y todavía sin notas: contado como 0 daría una alerta falsa.
    actingAs($e['docente']);
    cadenaServicio()->publicar($e['docente'], $delDos[1]);

    app(RiesgoService::class)->evaluarSeccion($dos);

    $computos = cadenaServicio()->estructura($dos);
    $cadena = cadenaServicio()->notasDeCadena($nueva);
    $abiertas = fn (int $matricula) => DB::table('academic_alerts')->where('enrollment_id', $matricula)
        ->where('alert_type', 'grade_risk')->whereNull('closed_at')->count();

    expect(app(RiesgoService::class)->notaProyectada($computos, collect(), $cadena))->toEqual(8.0)
        ->and(app(RiesgoService::class)->notaProyectada($computos, collect()))->toEqual(0.0)
        ->and($abiertas($nueva))->toBe(0)
        ->and($abiertas($companero['matricula']))->toBe(1);
});

/**
 * Una entrega de la matrícula indicada en una tarea de su sección (ADR-062, punto 4). Devuelve el id de la entrega.
 *
 * @param  array<string, mixed>  $e
 */
function cadenaEntrega(array $e, int $seccionId, int $matriculaId, int $actividadId, string $titulo = 'Informe'): int
{
    return DbContext::system(function () use ($seccionId, $matriculaId, $actividadId, $titulo) {
        $tarea = DB::table('assessments')->insertGetId([
            'ulid' => (string) str()->ulid(), 'section_id' => $seccionId, 'grade_item_id' => $actividadId, 'assessment_type' => 'assignment',
            'title' => $titulo, 'opens_at' => now()->subWeek(), 'closes_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('submissions')->insertGetId([
            'ulid' => (string) str()->ulid(), 'assessment_id' => $tarea, 'section_id' => $seccionId, 'enrollment_id' => $matriculaId,
            'status' => 'submitted', 'submitted_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    });
}

it('CP-RF09-08 el docente del grupo nuevo ve las entregas del grupo anterior, de solo lectura y con su grupo', function () {
    $e = escenario();
    $dos = cadenaGrupo($e, '02', '13:00', '14:40');
    $uno = cadenaActividades($e, $e['seccion']);
    cadenaActividades($e, $dos);
    $entrega = cadenaEntrega($e, $e['seccion'], (int) $e['matricula'], $uno[1], 'Informe del grupo 01');
    cadenaNota($e, $uno[1], (int) $e['matricula'], 8.0);
    $this->travelTo('2026-08-10 10:00');
    $nueva = cadenaTraslado($e, (int) $e['matricula'], $dos);

    actingAs($e['docente']);
    $entregas = app(ActividadService::class)->entregasDeCadena($e['docente'], $dos, $nueva);

    expect($entregas)->toHaveCount(1);
    expect($entregas->first())
        ->id->toBe($entrega)
        ->actividad->toBe('Informe del grupo 01')
        ->grupo->toBe('01')
        ->enrollment_id->toEqual($e['matricula'])
        ->section_id->toEqual($e['seccion'])
        ->and((float) $entregas->first()->score)->toBe(8.0);
});

it('CP-RF09-08 sin cambio de grupo no hay entregas anteriores que mostrar', function () {
    $e = escenario();
    $uno = cadenaActividades($e, $e['seccion']);
    cadenaEntrega($e, $e['seccion'], (int) $e['matricula'], $uno[1]);
    actingAs($e['docente']);

    expect(app(ActividadService::class)->entregasDeCadena($e['docente'], $e['seccion'], (int) $e['matricula']))->toBeEmpty();
});

it('CP-RF09-08 resolver la cadena no abre la sección anterior a quien no la imparte', function () {
    $e = escenario();
    $dos = cadenaGrupo($e, '02', '13:00', '14:40');
    cadenaActividades($e, $e['seccion']);
    cadenaActividades($e, $dos);
    $this->travelTo('2026-08-10 10:00');
    $nueva = cadenaTraslado($e, (int) $e['matricula'], $dos);
    $ajeno = docenteAjeno($e);

    // Sin alcance sobre la sección: 403, aunque la matrícula exista.
    actingAs($ajeno);
    expect(fn () => app(ActividadService::class)->entregasDeCadena($ajeno, $dos, $nueva))
        ->toThrow(HttpException::class);

    // Con alcance, pero pidiendo una matrícula que no es de esa sección: 404.
    $companero = cadenaCompanero($e, $e['seccion']);
    actingAs($e['docente']);
    expect(fn () => app(ActividadService::class)->entregasDeCadena($e['docente'], $dos, $companero['matricula']))
        ->toThrow(NotFoundHttpException::class);
});
