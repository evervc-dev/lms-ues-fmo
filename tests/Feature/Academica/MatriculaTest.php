<?php

use App\Models\User;
use App\Modules\Academica\Events\MatriculaCambiada;
use App\Modules\Academica\Livewire\ClavesMatricula;
use App\Modules\Academica\Livewire\Matricula;
use App\Modules\Academica\Services\MatriculaService;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Calificaciones\Services\ComputoService;
use App\Modules\Comunicacion\Notifications\Aviso;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Matrícula (RF09) y auto-matrícula con clave (RF29). Se validan el choque de horario, el cupo, el plan de la
| carrera y los prerrequisitos (ADR-052), y cada rol inscribe solo en su ámbito (ADR-037, Matriz RBAC).
*/

function matricula(): MatriculaService
{
    return app(MatriculaService::class);
}

/** Un estudiante activo de ISI (Plan 25) todavía sin matrículas. */
function matriculaEstudianteNuevo(array $e): int
{
    $estudiante = usuarioCon('estudiante');
    DbContext::system(fn () => DB::table('student_careers')->insert(['student_id' => $estudiante->id, 'career_id' => $e['isi'], 'study_plan_id' => $e['plan'],
        'current_cycle' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]));

    return $estudiante->id;
}

/**
 * Una asignatura de ISI (Plan 25) con una sección abierta los lunes a la hora indicada. Va en el ciclo 2 del plan
 * por defecto: el período del escenario (2026-II) es ciclo II, y solo se inscriben las de ciclo par (ADR-050).
 *
 * @return array{asignatura: int, seccion: int}
 */
function matriculaOtraMateria(array $e, string $codigo, string $inicio, string $fin, int $cupo = 40, int $ciclo = 2): array
{
    return DbContext::system(function () use ($e, $codigo, $inicio, $fin, $cupo, $ciclo) {
        $asignatura = DB::table('subjects')->insertGetId(['code' => $codigo, 'name' => 'Materia '.$codigo, 'credits' => 4, 'department_id' => $e['dia'], 'created_at' => now(), 'updated_at' => now()]);
        DB::table('career_subjects')->insert(['study_plan_id' => $e['plan'], 'subject_id' => $asignatura, 'cycle' => $ciclo]);
        $seccion = DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $e['periodo'], 'subject_id' => $asignatura, 'code' => '01',
            'max_capacity' => $cupo, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('section_schedules')->insert(['section_id' => $seccion, 'period_id' => $e['periodo'], 'weekday' => 1, 'start_time' => $inicio, 'end_time' => $fin,
            'created_at' => now(), 'updated_at' => now()]);

        return ['asignatura' => $asignatura, 'seccion' => $seccion];
    });
}

/** Clave vigente de una asignatura, generada por el Coordinador (o por quien se indique). */
function matriculaClave(array $e, int $asignatura, string $rol = 'coordinador'): string
{
    actingAs($e[$rol]);

    return matricula()->generarClave($e[$rol], $asignatura);
}

/** Un Jefe del departamento indicado (p. ej. DCNM, ajeno a las asignaturas de ISI). */
function matriculaJefeDe(int $departamento): User
{
    $jefe = usuarioCon('jefe_departamento');
    DbContext::system(fn () => DB::table('department_heads')->insert(['user_id' => $jefe->id, 'department_id' => $departamento, 'valid_from' => '2025-01-01',
        'created_at' => now(), 'updated_at' => now()]));

    return $jefe;
}

it('CP-RF09-02 rechaza la sección que choca en horario con otra matrícula activa del estudiante', function () {
    $e = escenario();
    $choca = matriculaOtraMateria($e, 'BDD115', '08:00', '09:40');
    actingAs($e['coordinador']);

    expect(fn () => matricula()->inscribir($e['coordinador'], $e['estudiante']->id, $choca['seccion']))
        ->toThrow(ValidationException::class, 'Choca con el horario de PRN115 · 01');

    expect(DB::table('enrollments')->where('section_id', $choca['seccion'])->exists())->toBeFalse();
});

it('CP-RF09-13 rechaza matricular sin el prerrequisito aprobado, nombrándolo, también a Soporte', function () {
    $e = escenario();
    $materia = matriculaOtraMateria($e, 'BDD115', '13:00', '14:40');
    DbContext::system(fn () => DB::table('subject_prerequisites')->insert(['subject_id' => $materia['asignatura'], 'prerequisite_id' => $e['mat']]));

    foreach (['coordinador', 'soporte'] as $rol) {
        actingAs($e[$rol]);
        expect(fn () => matricula()->inscribir($e[$rol], $e['estudiante']->id, $materia['seccion']))
            ->toThrow(ValidationException::class, 'Le falta aprobar MAT115 · Matemática I.');
    }

    expect(DB::table('enrollments')->where('section_id', $materia['seccion'])->exists())->toBeFalse();
});

it('CP-RF09-13 matricula cuando el prerrequisito está aprobado en un período cerrado', function () {
    $e = escenario();
    $materia = matriculaOtraMateria($e, 'BDD115', '13:00', '14:40');
    DbContext::system(function () use ($e, $materia) {
        DB::table('subject_prerequisites')->insert(['subject_id' => $materia['asignatura'], 'prerequisite_id' => $e['mat']]);
        // La matrícula previa se hizo con el período abierto y luego se cerró: el trigger fn_block_closed_period
        // (PostgreSQL) rechaza insertarla en un período que ya está cerrado.
        $cerrado = DB::table('academic_periods')->insertGetId(['name' => '2026-I', 'start_date' => '2026-02-02', 'end_date' => '2026-06-19', 'status' => 'planned', 'created_at' => now(), 'updated_at' => now()]);
        $seccion = DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $cerrado, 'subject_id' => $e['mat'], 'code' => '01', 'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
        $previa = DB::table('enrollments')->insertGetId(['student_id' => $e['estudiante']->id, 'section_id' => $seccion, 'period_id' => $cerrado, 'subject_id' => $e['mat'],
            'status' => 'active', 'enrolled_via' => 'admin', 'enrolled_by' => $e['administrador']->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('enrollment_results')->insert(['enrollment_id' => $previa, 'final_score' => 7.5, 'result' => 'passed', 'policy_snapshot' => json_encode([])]);
        DB::table('academic_periods')->where('id', $cerrado)->update(['status' => 'closed']);
    });
    actingAs($e['coordinador']);

    matricula()->inscribir($e['coordinador'], $e['estudiante']->id, $materia['seccion']);

    expect(DB::table('enrollments')->where('student_id', $e['estudiante']->id)->where('section_id', $materia['seccion'])->value('status'))->toBe('active');
});

it('CP-RF09-14 rechaza una asignatura fuera del plan de la carrera y no la ofrece en el formulario', function () {
    $e = escenario();
    $ajena = DbContext::system(function () use ($e) {
        $asignatura = DB::table('subjects')->insertGetId(['code' => 'ADM115', 'name' => 'Administración I', 'credits' => 4, 'department_id' => $e['dia'], 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $e['periodo'], 'subject_id' => $asignatura, 'code' => '01',
            'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
    });
    $estudiante = matriculaEstudianteNuevo($e);
    actingAs($e['administrador']);

    expect(fn () => matricula()->inscribir($e['administrador'], $estudiante, $ajena))
        ->toThrow(ValidationException::class, 'La asignatura no pertenece al plan de estudios de la carrera del estudiante.');

    $secciones = Livewire::test(Matricula::class)->set('estudianteId', $estudiante)->viewData('secciones')->pluck('id')->all();
    expect($secciones)->toContain($e['seccion'])->not->toContain($ajena)
        ->and(DB::table('enrollments')->where('section_id', $ajena)->exists())->toBeFalse();
});

it('CP-RF09-04 el docente y el jefe inscriben solo en su ámbito', function (string $rol) {
    $e = escenario();
    $estudiante = matriculaEstudianteNuevo($e);
    actingAs($e[$rol]);

    matricula()->inscribir($e[$rol], $estudiante, $e['seccion']);

    expect(DB::table('enrollments')->where('student_id', $estudiante)->where('section_id', $e['seccion'])->value('enrolled_by'))->toBe($e[$rol]->id)
        ->and(fn () => matricula()->inscribir($e[$rol], $estudiante, $e['otra']))->toThrow(AuthorizationException::class);

    expect(DB::table('enrollments')->where('student_id', $estudiante)->where('section_id', $e['otra'])->exists())->toBeFalse();
})->with(['docente', 'jefe']);

it('CP-RF09-04 el estudiante no inscribe a otros', function () {
    $e = escenario();
    $estudiante = matriculaEstudianteNuevo($e);

    expect(fn () => matricula()->inscribir($e['estudiante'], $estudiante, $e['seccion']))->toThrow(AuthorizationException::class);
});

it('CP-RF29-02 informa que ninguna sección tiene cupo o que chocan, y no matricula', function () {
    $e = escenario();
    $llena = matriculaOtraMateria($e, 'BDD115', '13:00', '14:40', cupo: 1);
    $estudiante = matriculaEstudianteNuevo($e);
    $choca = DbContext::system(function () use ($e, $llena, $estudiante) {
        DB::table('enrollments')->insert(['student_id' => $estudiante, 'section_id' => $llena['seccion'], 'period_id' => $e['periodo'], 'subject_id' => $llena['asignatura'],
            'status' => 'active', 'enrolled_via' => 'admin', 'enrolled_by' => $e['coordinador']->id, 'created_at' => now(), 'updated_at' => now()]);
        $seccion = DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $e['periodo'], 'subject_id' => $llena['asignatura'], 'code' => '02',
            'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('section_schedules')->insert(['section_id' => $seccion, 'period_id' => $e['periodo'], 'weekday' => 1, 'start_time' => '07:30', 'end_time' => '09:00',
            'created_at' => now(), 'updated_at' => now()]);

        return $seccion;
    });
    $clave = matriculaClave($e, $llena['asignatura']);
    actingAs($e['estudiante']);

    $opciones = matricula()->detalleMateria($e['estudiante'], $llena['asignatura'])['secciones']->keyBy('id');

    expect($opciones[$llena['seccion']]->llena)->toBeTrue()
        ->and($opciones[$choca]->choque)->toBe('PRN115 · 01')
        ->and(fn () => matricula()->automatricular($e['estudiante'], $llena['asignatura'], $clave, $llena['seccion']))->toThrow(ValidationException::class, 'La sección está llena.')
        ->and(fn () => matricula()->automatricular($e['estudiante'], $llena['asignatura'], $clave, $choca))->toThrow(ValidationException::class, 'Choca con el horario');

    expect(DB::table('enrollments')->where('student_id', $e['estudiante']->id)->where('subject_id', $llena['asignatura'])->exists())->toBeFalse();
});

it('CP-RF29-04 rechaza la sección llena aunque la clave sea correcta', function () {
    $e = escenario();
    $materia = matriculaOtraMateria($e, 'BDD115', '13:00', '14:40', cupo: 1);
    $ocupante = matriculaEstudianteNuevo($e);
    DbContext::system(fn () => DB::table('enrollments')->insert(['student_id' => $ocupante, 'section_id' => $materia['seccion'], 'period_id' => $e['periodo'],
        'subject_id' => $materia['asignatura'], 'status' => 'active', 'enrolled_via' => 'admin', 'enrolled_by' => $e['coordinador']->id, 'created_at' => now(), 'updated_at' => now()]));
    $clave = matriculaClave($e, $materia['asignatura']);
    actingAs($e['estudiante']);

    expect(fn () => matricula()->automatricular($e['estudiante'], $materia['asignatura'], $clave, $materia['seccion']))->toThrow(ValidationException::class, 'La sección está llena.');

    expect(DB::table('enrollments')->where('section_id', $materia['seccion'])->count())->toBe(1);
});

it('CP-RF29-06 la clave anterior deja de funcionar cuando el Coordinador o el Jefe la cambia o la desactiva', function (string $rol) {
    $e = escenario();
    $materia = matriculaOtraMateria($e, 'BDD115', '13:00', '14:40');
    $anterior = matriculaClave($e, $materia['asignatura'], $rol);
    $nueva = matriculaClave($e, $materia['asignatura'], $rol);
    actingAs($e['estudiante']);

    // Con una clave válida y un grupo de otra materia, la clave se acepta y solo se rechaza el grupo: no matricula.
    expect(fn () => matricula()->automatricular($e['estudiante'], $materia['asignatura'], $anterior, $materia['seccion']))->toThrow(ValidationException::class, 'La clave no es válida o venció.')
        ->and(fn () => matricula()->automatricular($e['estudiante'], $materia['asignatura'], $nueva, $e['otra']))->toThrow(ValidationException::class, 'Elija una de las secciones de la materia.');

    actingAs($e[$rol]);
    matricula()->desactivarClave($e[$rol], $materia['asignatura']);
    actingAs($e['estudiante']);

    expect(fn () => matricula()->automatricular($e['estudiante'], $materia['asignatura'], $nueva, $materia['seccion']))->toThrow(ValidationException::class, 'La clave no es válida o venció.');

    expect(DB::table('enrollments')->where('subject_id', $materia['asignatura'])->exists())->toBeFalse();
})->with(['coordinador', 'jefe']);

it('CP-RF29-06 el Docente, el Estudiante y el Jefe de otro departamento no gestionan las claves', function (string $rol) {
    $e = escenario();
    $materia = matriculaOtraMateria($e, 'BDD115', '13:00', '14:40');
    $actor = $rol === 'jefe ajeno' ? matriculaJefeDe($e['dcnm']) : $e[$rol];
    actingAs($actor);

    expect(fn () => matricula()->generarClave($actor, $materia['asignatura']))->toThrow(AuthorizationException::class)
        ->and(fn () => matricula()->desactivarClave($actor, $materia['asignatura']))->toThrow(AuthorizationException::class);

    expect(DB::table('enrollment_keys')->count())->toBe(0);
})->with(['docente', 'estudiante', 'jefe ajeno']);

/**
 * Comprueba que al estudiante le llegó el aviso enrollment.changed con ese texto y que a nadie más del escenario.
 */
function matriculaAvisoA(array $e, int $estudiante, string $titulo, string $cuerpo): void
{
    Notification::assertSentTo(User::find($estudiante), Aviso::class, fn (Aviso $aviso) => $aviso->evento === 'enrollment.changed'
        && str_contains($aviso->titulo, $titulo) && str_contains($aviso->cuerpo, $cuerpo) && $aviso->queue === 'notificaciones');
    foreach ([$e['docente'], $e['coordinador'], $e['jefe']] as $otro) {
        Notification::assertNotSentTo($otro, Aviso::class);
    }
}

it('CP-RF09-10 avisa al estudiante cuando lo inscriben y cuando lo retiran', function () {
    Notification::fake();
    $e = escenario();
    $estudiante = matriculaEstudianteNuevo($e);
    actingAs($e['coordinador']);

    $id = matricula()->inscribir($e['coordinador'], $estudiante, $e['seccion']);
    matriculaAvisoA($e, $estudiante, 'Matrícula: PRN115-01', 'quedó registrada');

    matricula()->retirar($e['coordinador'], $id, 'Retiro voluntario.');
    matriculaAvisoA($e, $estudiante, 'Retiro de matrícula: PRN115-01', 'Motivo: Retiro voluntario.');
});

it('CP-RF09-10 avisa al estudiante cuando se aprueba o se rechaza su cambio de grupo', function (bool $aprobar) {
    Notification::fake();
    $e = escenario();
    $destino = DbContext::system(fn () => DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
        'code' => '02', 'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]));
    $solicitud = DbContext::system(fn () => DB::table('section_change_requests')->insertGetId(['enrollment_id' => $e['matricula'], 'target_section_id' => $destino,
        'justification' => 'Trabajo por la mañana', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
    actingAs($e['coordinador']);

    $aprobar
        ? matricula()->aprobarCambio($e['coordinador'], $solicitud)
        : matricula()->rechazarCambio($e['coordinador'], $solicitud, 'No hay cupo en ese horario.');

    $aprobar
        ? matriculaAvisoA($e, $e['estudiante']->id, 'Cambio de grupo: PRN115-02', 'ahora está en el grupo 02')
        : matriculaAvisoA($e, $e['estudiante']->id, 'Cambio de grupo rechazado: PRN115-01', 'Motivo: No hay cupo en ese horario.');
})->with(['aprobado' => true, 'rechazado' => false]);

it('CP-RF09-10 no avisa cuando la inscripción se rechaza', function () {
    Event::fake([MatriculaCambiada::class]);
    $e = escenario();
    actingAs($e['coordinador']);

    expect(fn () => matricula()->inscribir($e['coordinador'], $e['estudiante']->id, $e['seccion']))->toThrow(ValidationException::class);

    Event::assertNotDispatched(MatriculaCambiada::class);
});

it('CP-RF29-01 con la clave ve las secciones con cupo y sin choque, elige una y queda matriculado con aviso', function () {
    Notification::fake();
    $e = escenario();
    $materia = matriculaOtraMateria($e, 'BDD115', '13:00', '14:40');
    $clave = matriculaClave($e, $materia['asignatura']);
    actingAs($e['estudiante']);

    $opcion = matricula()->detalleMateria($e['estudiante'], $materia['asignatura'])['secciones']->sole();
    expect($opcion->id)->toBe($materia['seccion'])->and($opcion->llena)->toBeFalse()->and($opcion->choque)->toBeNull();

    matricula()->automatricular($e['estudiante'], $materia['asignatura'], $clave, $materia['seccion']);

    expect(DB::table('enrollments')->where('student_id', $e['estudiante']->id)->where('section_id', $materia['seccion'])->value('enrolled_via'))->toBe('key');
    matriculaAvisoA($e, $e['estudiante']->id, 'Matrícula: BDD115-01', 'quedó registrada');
});

it('CP-RF09-09 bloquea la sección antes de contar el cupo, para que dos inscripciones simultáneas no tomen el último', function () {
    $e = escenario();
    $estudiante = matriculaEstudianteNuevo($e);
    actingAs($e['coordinador']);
    $consultas = [];
    DB::listen(function ($consulta) use (&$consultas) {
        $consultas[] = mb_strtolower($consulta->sql);
    });

    matricula()->inscribir($e['coordinador'], $estudiante, $e['seccion']);

    $bloqueo = collect($consultas)->search(fn (string $sql) => str_contains($sql, 'from "sections"') && str_contains($sql, 'for update'));
    $cuenta = collect($consultas)->search(fn (string $sql) => str_contains($sql, 'count(*)') && str_contains($sql, 'from "enrollments"'));
    $alta = collect($consultas)->search(fn (string $sql) => str_starts_with($sql, 'insert into "enrollments"'));
    // En PostgreSQL, SELECT … FOR UPDATE hace esperar a la segunda inscripción hasta que la primera confirma.
    expect($bloqueo)->not->toBeFalse()
        ->and($bloqueo)->toBeLessThan($cuenta)
        ->and($cuenta)->toBeLessThan($alta);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'SQLite no tiene SELECT … FOR UPDATE; el bloqueo solo existe en PostgreSQL.');

it('CP-RF09-12 deja la fila como Retirada en la misma pantalla, sin recargar', function () {
    $e = escenario();
    actingAs($e['coordinador']);
    $matricula = DB::table('enrollments')->where('student_id', $e['estudiante']->id)->where('status', 'active')->value('id');

    Livewire::test(Matricula::class)
        ->assertSee('Activa')
        ->call('confirmarRetiro', $matricula)
        ->set('motivo', 'Cambio de carrera')
        ->call('retirar')
        ->assertHasNoErrors()
        ->assertSee('Retirada');
});

/* ------------------------------------------------------------------ CP-RF09-08 (cambio de grupo aprobado) */

/** Otra sección de PRN115 (grupo 02) con horario y con el docente del escenario: el destino del cambio de grupo. */
function matriculaGrupoDos(array $e, string $inicio = '13:00', string $fin = '14:40'): int
{
    return DbContext::system(function () use ($e, $inicio, $fin) {
        $id = DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $e['periodo'], 'subject_id' => $e['prn'], 'code' => '02',
            'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('section_schedules')->insert(['section_id' => $id, 'period_id' => $e['periodo'], 'weekday' => 1, 'start_time' => $inicio, 'end_time' => $fin,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('teaching_assignments')->insert(['section_id' => $id, 'teacher_id' => $e['docente']->id, 'is_responsible' => true, 'started_at' => '2026-07-01',
            'created_at' => now(), 'updated_at' => now()]);

        return $id;
    });
}

/**
 * Los dos cómputos de 50 % del período copiados a la sección, cada uno con una actividad manual del 100 % sin
 * publicar. Los dos grupos comparten el `number` del cómputo porque copian la misma plantilla ([[ADR-062]]).
 *
 * @param  array<string, mixed>  $e
 * @return array<int, int> number del cómputo => grade_item_id
 */
function matriculaActividades(array $e, int $seccionId): array
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
 * El estudiante pide el cambio al grupo indicado y el Coordinador lo aprueba; devuelve la matrícula nueva.
 *
 * @param  array<string, mixed>  $e
 */
function matriculaCambioAprobado(array $e, int $matriculaId, int $destino): int
{
    actingAs($e['estudiante']);
    matricula()->solicitarCambio($e['estudiante'], $matriculaId, $destino, 'Trabajo por las mañanas');
    actingAs($e['coordinador']);
    matricula()->aprobarCambio($e['coordinador'], (int) DB::table('section_change_requests')
        ->where('enrollment_id', $matriculaId)->where('status', 'pending')->value('id'));

    return (int) DB::table('enrollments')->where('id', $matriculaId)->value('transferred_to_id');
}

it('CP-RF09-08 el cambio aprobado hereda el cómputo publicado, deja el historial y no arrastra la asistencia', function () {
    $e = escenario();
    // Dentro del plazo de cambio de grupo: las 3 primeras semanas de 2026-II (hasta el 17/08/2026).
    $this->travelTo('2026-08-10 10:00');
    $dos = matriculaGrupoDos($e);
    $uno = matriculaActividades($e, $e['seccion']);
    matriculaActividades($e, $dos);
    actingAs($e['docente']);
    app(CalificacionService::class)->calificar($e['docente'], $uno[1], $e['matricula'], 8);
    app(CalificacionService::class)->publicar($e['docente'], $uno[1]);
    $sesion = app(AsistenciaService::class)->abrir($e['docente'], $e['seccion'], 10);
    app(AsistenciaService::class)->marcar($e['docente'], $e['seccion'], $sesion, $e['matricula'], 'present');

    $nueva = matriculaCambioAprobado($e, $e['matricula'], $dos);

    // 1. La boleta del grupo 02 trae el cómputo 1 heredado, con su grupo de origen (ADR-062).
    $boleta = app(CalificacionService::class)->boleta($e['estudiante'], $dos);
    expect($boleta['heredadas']->get(1))->nota->toEqual(8.0)->grupo->toBe('01')->seccion_id->toBe($e['seccion'])->matricula_id->toBe($e['matricula'])
        ->and(app(CalificacionService::class)->notaComputo($boleta['computos']->firstWhere('number', 1), $boleta['notas'], true, $boleta['heredadas']))->toEqual(8.0)
        ->and($boleta['final'])->toEqual(4.0);

    // 2. El historial: la matrícula vieja queda trasladada y enlazada con la nueva.
    $vieja = DB::table('enrollments')->where('id', $e['matricula'])->first();
    expect(DB::table('enrollment_changes')->where('enrollment_id', $e['matricula'])->sole())
        ->change_type->toBe('transferred')->reason->toBe('Trabajo por las mañanas')
        ->and($vieja->status)->toBe('transferred')
        ->and((int) $vieja->transferred_to_id)->toBe($nueva)
        ->and(DB::table('enrollments')->where('id', $nueva)->first())
        ->status->toBe('active')->enrolled_via->toBe('group_change');

    // 4. La asistencia no se encadena: la del grupo 01 se queda en su matrícula (ADR-062, punto 5).
    $mia = app(AsistenciaService::class)->miAsistencia($e['estudiante'])->sole();
    expect((int) $mia->id)->toBe($nueva)
        ->and($mia->grupo)->toBe('02')
        ->and($mia->porcentaje)->toBeNull()
        ->and((int) $mia->clases)->toBe(0)
        ->and($mia->nivel)->toBe('sin_datos')
        ->and(DB::table('attendance_records')->where('enrollment_id', $e['matricula'])->value('status'))->toBe('present')
        ->and(DB::table('attendance_records')->where('enrollment_id', $nueva)->exists())->toBeFalse();
});

it('CP-RF09-08 registra el cambio de grupo en la auditoría, con el Coordinador como actor', function () {
    $e = escenario();
    $this->travelTo('2026-08-10 10:00');
    $dos = matriculaGrupoDos($e);

    $nueva = matriculaCambioAprobado($e, $e['matricula'], $dos);

    DbContext::auditScope(all: true);
    $auditoria = DB::table('audit_logs')->where('entity_type', 'enrollments')->where('event_type', 'enrollment.group_changed')->orderBy('id')->get();
    $traslado = $auditoria->firstWhere('action', 'update');

    expect($auditoria->where('action', 'insert')->pluck('entity_id')->map(fn ($id) => (int) $id)->all())->toBe([$nueva])
        ->and($auditoria->where('action', 'update')->pluck('entity_id')->unique()->map(fn ($id) => (int) $id)->all())->toBe([$e['matricula']])
        ->and($auditoria->pluck('actor_user_id')->unique()->map(fn ($id) => (int) $id)->all())->toBe([$e['coordinador']->id])
        ->and(json_decode((string) $traslado->old_values, true)['status'])->toBe('active')
        ->and(json_decode((string) $traslado->new_values, true)['status'])->toBe('transferred');
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La auditoría por triggers solo existe en PostgreSQL.');

it('CP-RF29-07 busca una asignatura en las claves y limpia el filtro', function () {
    $e = escenario();
    actingAs($e['coordinador']);
    $asignatura = DB::table('subjects')->where('id', $e['prn'])->first();

    Livewire::test(ClavesMatricula::class)
        ->assertSee($asignatura->code)
        ->set('buscar', 'zzz-no-existe')
        ->assertDontSee($asignatura->code)
        ->assertSee('Ninguna asignatura de su ámbito coincide con esa búsqueda.')
        ->set('buscar', $asignatura->code)
        ->assertSee($asignatura->code)
        ->call('limpiar')
        ->assertSet('buscar', '');
});
