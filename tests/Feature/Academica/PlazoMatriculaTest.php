<?php

use App\Models\User;
use App\Modules\Academica\Livewire\Inscripcion;
use App\Modules\Academica\Livewire\InscripcionDetalle;
use App\Modules\Academica\Livewire\Matricula;
use App\Modules\Academica\Livewire\PlazosMatricula;
use App\Modules\Academica\Services\MatriculaService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Fecha límite de inscripción con prórroga (RF29, ADR-051). La fecha es por período y departamento, y manda el
| departamento de la carrera del estudiante. Sin fecha, rige el inicio del período + N semanas
| (`enrollment_deadline_weeks_default`, 2). El plazo efectivo es la mayor entre la fecha del departamento, su
| prórroga y la prórroga personal del estudiante, hasta el final de ese día. Vencido, se bloquea toda inscripción
| nueva: con clave y manual, de cualquier rol.
|
| El escenario usa 2026-II (27/07–11/12) y fija la fecha de DIA y DCNM en el 11/12. Hoy es el 26/10/2026.
*/

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-26 10:00'));
});

function plazo(): MatriculaService
{
    return app(MatriculaService::class);
}

/** Un estudiante activo de ISI (carrera de DIA) todavía sin matrículas. */
function plazoEstudiante(array $e): User
{
    $estudiante = usuarioCon('estudiante');
    DbContext::system(fn () => DB::table('student_careers')->insert(['student_id' => $estudiante->id, 'career_id' => $e['isi'], 'study_plan_id' => $e['plan'],
        'current_cycle' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]));

    return $estudiante;
}

/**
 * Una asignatura del ciclo 2 del Plan 25 de ISI, del departamento indicado (DIA por defecto), con un grupo los lunes
 * a las 13:00 y su clave vigente, generada por el Coordinador de ISI.
 *
 * @return array{asignatura: int, seccion: int, clave: string}
 */
function plazoMateria(array $e, string $codigo, ?int $departamento = null): array
{
    $materia = DbContext::system(function () use ($e, $codigo, $departamento) {
        $asignatura = DB::table('subjects')->insertGetId(['code' => $codigo, 'name' => 'Materia '.$codigo, 'credits' => 4, 'department_id' => $departamento ?? $e['dia'],
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('career_subjects')->insert(['study_plan_id' => $e['plan'], 'subject_id' => $asignatura, 'cycle' => 2]);
        $seccion = DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $e['periodo'], 'subject_id' => $asignatura, 'code' => '01',
            'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('section_schedules')->insert(['section_id' => $seccion, 'period_id' => $e['periodo'], 'weekday' => 1, 'start_time' => '13:00', 'end_time' => '14:40',
            'created_at' => now(), 'updated_at' => now()]);

        return ['asignatura' => $asignatura, 'seccion' => $seccion];
    });
    actingAs($e['coordinador']);

    return $materia + ['clave' => plazo()->generarClave($e['coordinador'], $materia['asignatura'])];
}

/** Mueve la fecha límite del departamento en el período del escenario. */
function plazoDelDepartamento(array $e, int $departamento, string $fecha): void
{
    DbContext::system(fn () => DB::table('enrollment_deadlines')->where('period_id', $e['periodo'])->where('department_id', $departamento)
        ->update(['deadline' => $fecha, 'updated_at' => now()]));
}

/** Un Jefe del departamento indicado. */
function plazoJefeDe(int $departamento): User
{
    $jefe = usuarioCon('jefe_departamento');
    DbContext::system(fn () => DB::table('department_heads')->insert(['user_id' => $jefe->id, 'department_id' => $departamento, 'valid_from' => '2025-01-01',
        'created_at' => now(), 'updated_at' => now()]));

    return $jefe;
}

/** El Coordinador de otra carrera (Física, de DCNM). */
function plazoCoordinadorAjeno(array $e): User
{
    $coordinador = usuarioCon('coordinador');
    DbContext::system(function () use ($e, $coordinador): void {
        $carrera = DB::table('careers')->insertGetId(['department_id' => $e['dcnm'], 'code' => 'FIS', 'name' => 'Licenciatura en Física', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('career_coordinators')->insert(['user_id' => $coordinador->id, 'career_id' => $carrera, 'valid_from' => '2025-01-01', 'created_at' => now(), 'updated_at' => now()]);
    });

    return $coordinador;
}

/** Comprueba que la inscripción se rechaza por el plazo, con la clave `plazo` y la fecha en d/m/Y. */
function plazoRechaza(Closure $inscribir, string $fecha): void
{
    expect($inscribir)->toThrow(function (ValidationException $error) use ($fecha) {
        expect($error->errors())->toHaveKey('plazo')
            ->and($error->errors()['plazo'][0])->toBe("El plazo de inscripción terminó el {$fecha}.");
    });
}

function plazoInscrito(int $estudiante, int $seccion): bool
{
    return DB::table('enrollments')->where('student_id', $estudiante)->where('section_id', $seccion)->where('status', 'active')->exists();
}

it('CP-RF29-14 sin fecha del departamento, el plazo es el inicio del período más las semanas del parámetro', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('enrollment_deadlines')->where('department_id', $e['dia'])->delete());

    $plazo = plazo()->plazoDe($e['estudiante']->id);
    expect($plazo['fecha']->toDateTimeString())->toBe('2026-08-10 23:59:59')
        ->and($plazo['origen'])->toBe('defecto')
        ->and($plazo['vencido'])->toBeTrue()
        ->and(plazo()->plazoDe($e['estudiante']->id, $e['periodo'])['fecha']->toDateString())->toBe('2026-08-10');

    DbContext::system(fn () => DB::table('system_settings')->updateOrInsert(['key' => 'enrollment_deadline_weeks_default'], ['value' => json_encode(14), 'updated_at' => now()]));
    $plazo = plazo()->plazoDe($e['estudiante']->id);
    expect($plazo['fecha']->toDateString())->toBe('2026-11-02')->and($plazo['vencido'])->toBeFalse();

    // Sin el parámetro, 2 semanas.
    DbContext::system(fn () => DB::table('system_settings')->where('key', 'enrollment_deadline_weeks_default')->delete());
    expect(plazo()->plazoDe($e['estudiante']->id)['fecha']->toDateString())->toBe('2026-08-10');
});

it('CP-RF29-14 con la fecha del departamento, esa es la que rige', function () {
    $e = escenario();

    $plazo = plazo()->plazoDe($e['estudiante']->id);

    expect($plazo['fecha']->toDateTimeString())->toBe('2099-12-31 23:59:59')
        ->and($plazo['origen'])->toBe('departamento')
        ->and($plazo['vencido'])->toBeFalse();
});

it('CP-RF29-15 el Jefe, el Coordinador y el Administrador fijan la fecha de un departamento de su ámbito', function (string $rol) {
    $e = escenario();
    actingAs($e[$rol]);

    plazo()->fijarPlazo($e[$rol], $e['dia'], '2026-11-30');

    $fila = DB::table('enrollment_deadlines')->where('period_id', $e['periodo'])->where('department_id', $e['dia'])->sole();
    expect(substr((string) $fila->deadline, 0, 10))->toBe('2026-11-30')
        ->and((int) $fila->set_by)->toBe($e[$rol]->id)
        ->and(plazo()->plazoDe($e['estudiante']->id)['fecha']->toDateString())->toBe('2026-11-30');
})->with(['jefe', 'coordinador', 'administrador']);

it('CP-RF29-15 el Jefe y el Coordinador no fijan la fecha de otro departamento; el Administrador sí, aunque no tenga fila', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('enrollment_deadlines')->where('department_id', $e['dcnm'])->delete());

    foreach (['jefe', 'coordinador'] as $rol) {
        actingAs($e[$rol]);
        expect(fn () => plazo()->fijarPlazo($e[$rol], $e['dcnm'], '2026-11-30'))->toThrow(AuthorizationException::class);
    }
    expect(DB::table('enrollment_deadlines')->where('department_id', $e['dcnm'])->exists())->toBeFalse();

    actingAs($e['administrador']);
    plazo()->fijarPlazo($e['administrador'], $e['dcnm'], '2026-11-30');

    expect(substr((string) DB::table('enrollment_deadlines')->where('period_id', $e['periodo'])->where('department_id', $e['dcnm'])->value('deadline'), 0, 10))->toBe('2026-11-30');
});

it('CP-RF29-15 el Jefe y el Coordinador de otro ámbito, el Docente y el Estudiante no fijan la fecha', function (string $rol) {
    $e = escenario();
    $actor = match ($rol) {
        'jefe ajeno' => plazoJefeDe($e['dcnm']),
        'coordinador ajeno' => plazoCoordinadorAjeno($e),
        default => $e[$rol],
    };
    actingAs($actor);

    expect(fn () => plazo()->fijarPlazo($actor, $e['dia'], '2026-11-30'))->toThrow(AuthorizationException::class);

    expect(substr((string) DB::table('enrollment_deadlines')->where('department_id', $e['dia'])->value('deadline'), 0, 10))->toBe('2099-12-31');
})->with(['jefe ajeno', 'coordinador ajeno', 'docente', 'estudiante']);

it('CP-RF29-15 la pantalla de plazos lista solo los departamentos del ámbito y da 403 al docente', function () {
    $e = escenario();
    actingAs($e['jefe']);

    expect(plazo()->plazos($e['jefe'])->pluck('id')->map(fn ($id) => (int) $id)->all())->toBe([$e['dia']])
        ->and(plazo()->plazos($e['administrador'])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all())->toBe(collect([$e['dia'], $e['dcnm']])->sort()->values()->all());

    $this->get(route('matricula.plazos'))->assertOk()->assertSeeText(['Fecha límite por departamento', 'Ingeniería y Arquitectura'])
        ->assertDontSeeText('Ciencias Naturales y Matemática');
    actingAs($e['docente'])->get(route('matricula.plazos'))->assertForbidden();
});

it('CP-RF29-15 la pantalla de plazos cambia la fecha del departamento y exige una fecha válida', function () {
    $e = escenario();
    actingAs($e['jefe']);

    Livewire::test(PlazosMatricula::class)
        ->call('cambiarFecha', $e['dia'])->assertSet('fecha', '2099-12-31')
        ->set('fecha', 'no es fecha')->call('guardarFecha')->assertHasErrors(['fecha' => 'date'])
        ->set('fecha', '2026-11-30')->call('guardarFecha')->assertHasNoErrors()
        ->assertSee('30/11/2026');

    expect(substr((string) DB::table('enrollment_deadlines')->where('department_id', $e['dia'])->value('deadline'), 0, 10))->toBe('2026-11-30');
});

it('CP-RF29-16 vencido el plazo, el estudiante no se inscribe con la clave y la página se lo informa', function () {
    $e = escenario();
    $estudiante = plazoEstudiante($e);
    $bdd = plazoMateria($e, 'BDD115');
    plazoDelDepartamento($e, $e['dia'], '2026-10-20');
    actingAs($estudiante);

    expect(plazo()->plazoDe($estudiante->id))->vencido->toBeTrue()->origen->toBe('departamento');
    plazoRechaza(fn () => plazo()->automatricular($estudiante, $bdd['asignatura'], $bdd['clave'], $bdd['seccion']), '20/10/2026');

    $aviso = 'El plazo de inscripción terminó el 20/10/2026. Si necesita inscribirse, pida una prórroga a su coordinador.';
    Livewire::test(Inscripcion::class)->assertSee($aviso);
    Livewire::test(InscripcionDetalle::class, ['asignatura' => $bdd['asignatura']])
        ->assertSee($aviso)
        ->set('seccionElegida', $bdd['seccion'])->set('clave', $bdd['clave'])->call('inscribirme')
        ->assertHasErrors()->assertNoRedirect();

    expect(plazoInscrito($estudiante->id, $bdd['seccion']))->toBeFalse();
});

it('CP-RF29-16 el plazo vale hasta el final del día de la fecha límite', function () {
    $e = escenario();
    $estudiante = plazoEstudiante($e);
    $bdd = plazoMateria($e, 'BDD115');
    plazoDelDepartamento($e, $e['dia'], '2026-10-26');
    actingAs($estudiante);

    $this->travelTo(Carbon::parse('2026-10-26 23:59:00'));
    expect(plazo()->plazoDe($estudiante->id)['vencido'])->toBeFalse();
    Livewire::test(Inscripcion::class)->assertSee('Inscripción abierta hasta el 26/10/2026.');

    $this->travelTo(Carbon::parse('2026-10-27 00:00:01'));
    expect(plazo()->plazoDe($estudiante->id)['vencido'])->toBeTrue();
    plazoRechaza(fn () => plazo()->automatricular($estudiante, $bdd['asignatura'], $bdd['clave'], $bdd['seccion']), '26/10/2026');
});

it('CP-RF29-17 vencido el plazo, tampoco se inscribe manualmente', function (string $rol) {
    $e = escenario();
    $estudiante = plazoEstudiante($e);
    plazoDelDepartamento($e, $e['dia'], '2026-10-20');
    actingAs($e[$rol]);

    plazoRechaza(fn () => plazo()->inscribir($e[$rol], $estudiante->id, $e['seccion']), '20/10/2026');
    expect(plazoInscrito($estudiante->id, $e['seccion']))->toBeFalse();

    // Con el plazo abierto, la misma inscripción sí procede.
    plazoDelDepartamento($e, $e['dia'], '2026-12-11');
    plazo()->inscribir($e[$rol], $estudiante->id, $e['seccion']);
    expect(plazoInscrito($estudiante->id, $e['seccion']))->toBeTrue();
})->with(['docente', 'coordinador', 'jefe', 'soporte', 'administrador']);

it('CP-RF29-17 la pantalla de matrícula muestra el error del plazo', function () {
    $e = escenario();
    $estudiante = plazoEstudiante($e);
    plazoDelDepartamento($e, $e['dia'], '2026-10-20');
    actingAs($e['coordinador']);

    Livewire::test(Matricula::class)
        ->set('estudianteId', $estudiante->id)->set('seccionId', $e['seccion'])->call('inscribir')
        ->assertHasErrors(['plazo'])->assertSee('El plazo de inscripción terminó el 20/10/2026.');

    expect(plazoInscrito($estudiante->id, $e['seccion']))->toBeFalse();
});

it('CP-RF29-18 la prórroga del Jefe con motivo reabre la inscripción del departamento y conserva la fecha original', function () {
    $e = escenario();
    $estudiante = plazoEstudiante($e);
    $bdd = plazoMateria($e, 'BDD115');
    plazoDelDepartamento($e, $e['dia'], '2026-10-20');
    actingAs($e['jefe']);

    plazo()->prorrogarDepartamento($e['jefe'], $e['dia'], '2026-11-06', 'Paro de transporte durante la semana de inscripción.');

    $fila = DB::table('enrollment_deadlines')->where('period_id', $e['periodo'])->where('department_id', $e['dia'])->sole();
    expect(substr((string) $fila->deadline, 0, 10))->toBe('2026-10-20')
        ->and(substr((string) $fila->extended_until, 0, 10))->toBe('2026-11-06')
        ->and($fila->extension_reason)->toBe('Paro de transporte durante la semana de inscripción.')
        ->and((int) $fila->extended_by)->toBe($e['jefe']->id)
        ->and(plazo()->plazoDe($estudiante->id))->origen->toBe('prorroga-departamento')->vencido->toBeFalse();
    expect(plazo()->plazoDe($estudiante->id)['fecha']->toDateString())->toBe('2026-11-06');

    actingAs($estudiante);
    plazo()->automatricular($estudiante, $bdd['asignatura'], $bdd['clave'], $bdd['seccion']);

    expect(plazoInscrito($estudiante->id, $bdd['seccion']))->toBeTrue();
});

it('CP-RF29-18 sin fila del departamento, la prórroga la crea con la fecha por defecto como fecha original', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('enrollment_deadlines')->where('department_id', $e['dia'])->delete());
    actingAs($e['jefe']);

    plazo()->prorrogarDepartamento($e['jefe'], $e['dia'], '2026-11-06', 'Paro de transporte durante la semana de inscripción.');

    $fila = DB::table('enrollment_deadlines')->where('period_id', $e['periodo'])->where('department_id', $e['dia'])->sole();
    expect(substr((string) $fila->deadline, 0, 10))->toBe('2026-08-10')
        ->and(substr((string) $fila->extended_until, 0, 10))->toBe('2026-11-06');
});

it('CP-RF29-18 rechaza la prórroga sin motivo o que no pasa de la fecha que rige', function (string $hasta, string $motivo) {
    $e = escenario();
    plazoDelDepartamento($e, $e['dia'], '2026-10-20');
    actingAs($e['jefe']);

    expect(fn () => plazo()->prorrogarDepartamento($e['jefe'], $e['dia'], $hasta, $motivo))->toThrow(ValidationException::class);

    expect(DB::table('enrollment_deadlines')->where('department_id', $e['dia'])->value('extended_until'))->toBeNull();
})->with([
    'sin motivo' => ['2026-11-06', '   '],
    'antes de la fecha' => ['2026-10-15', 'Paro de transporte durante la semana de inscripción.'],
    'el mismo día' => ['2026-10-20', 'Paro de transporte durante la semana de inscripción.'],
]);

it('CP-RF29-18 el Coordinador y el Jefe de otro departamento no prorrogan el departamento', function (string $rol) {
    $e = escenario();
    plazoDelDepartamento($e, $e['dia'], '2026-10-20');
    $actor = $rol === 'jefe ajeno' ? plazoJefeDe($e['dcnm']) : $e[$rol];
    actingAs($actor);

    expect(fn () => plazo()->prorrogarDepartamento($actor, $e['dia'], '2026-11-06', 'Paro de transporte durante la semana de inscripción.'))
        ->toThrow(AuthorizationException::class);

    expect(DB::table('enrollment_deadlines')->where('department_id', $e['dia'])->value('extended_until'))->toBeNull();
})->with(['coordinador', 'jefe ajeno']);

it('CP-RF29-18 la pantalla de plazos prorroga el departamento con motivo; el Coordinador no ve la acción', function () {
    $e = escenario();
    plazoDelDepartamento($e, $e['dia'], '2026-10-20');
    actingAs($e['jefe']);

    Livewire::test(PlazosMatricula::class)
        ->assertSee('Vencida')
        ->call('prorrogar', $e['dia'])
        ->set('hasta', '2026-11-06')->set('motivo', 'Paro')->call('guardarProrroga')->assertHasErrors(['motivo' => 'min'])
        ->set('motivo', 'Paro de transporte durante la semana de inscripción.')->call('guardarProrroga')->assertHasNoErrors()
        ->assertSee(['Prorrogada hasta el 06/11/2026', 'Fecha original: 20/10/2026', 'Abierta']);

    actingAs($e['coordinador']);
    Livewire::test(PlazosMatricula::class)->assertSee('Cambiar fecha')->assertDontSeeHtml('wire:click="prorrogar(');
});

it('CP-RF29-19 la prórroga por estudiante permite inscribir solo a ese estudiante, con clave y manualmente', function (string $rol) {
    $e = escenario();
    $favorecido = plazoEstudiante($e);
    $otro = plazoEstudiante($e);
    $bdd = plazoMateria($e, 'BDD115');
    plazoDelDepartamento($e, $e['dia'], '2026-10-20');
    actingAs($e[$rol]);

    plazo()->prorrogarEstudiante($e[$rol], $favorecido->id, '2026-10-30', 'Estuvo hospitalizado durante la inscripción.');

    expect(plazo()->plazoDe($favorecido->id))->origen->toBe('prorroga-estudiante')->vencido->toBeFalse()
        ->and(plazo()->plazoDe($favorecido->id)['fecha']->toDateString())->toBe('2026-10-30')
        ->and(plazo()->plazoDe($otro->id)['vencido'])->toBeTrue()
        ->and(plazo()->prorrogas($e[$rol])->count())->toBe(1);

    actingAs($favorecido);
    plazo()->automatricular($favorecido, $bdd['asignatura'], $bdd['clave'], $bdd['seccion']);
    actingAs($otro);
    plazoRechaza(fn () => plazo()->automatricular($otro, $bdd['asignatura'], $bdd['clave'], $bdd['seccion']), '20/10/2026');

    actingAs($e[$rol]);
    plazo()->inscribir($e[$rol], $favorecido->id, $e['seccion']);
    plazoRechaza(fn () => plazo()->inscribir($e[$rol], $otro->id, $e['seccion']), '20/10/2026');

    expect(plazoInscrito($favorecido->id, $bdd['seccion']))->toBeTrue()
        ->and(plazoInscrito($favorecido->id, $e['seccion']))->toBeTrue()
        ->and(DB::table('enrollments')->where('student_id', $otro->id)->exists())->toBeFalse();
})->with(['coordinador', 'jefe']);

it('CP-RF29-19 hay una sola prórroga por período y estudiante: otorgarla de nuevo la actualiza', function () {
    $e = escenario();
    actingAs($e['coordinador']);

    plazo()->prorrogarEstudiante($e['coordinador'], $e['estudiante']->id, '2026-10-30', 'Estuvo hospitalizado durante la inscripción.');
    plazo()->prorrogarEstudiante($e['coordinador'], $e['estudiante']->id, '2026-11-04', 'Se extendió la incapacidad médica.');

    $prorroga = DB::table('enrollment_extensions')->where('period_id', $e['periodo'])->where('student_id', $e['estudiante']->id)->sole();
    expect(substr((string) $prorroga->until, 0, 10))->toBe('2026-11-04')
        ->and($prorroga->reason)->toBe('Se extendió la incapacidad médica.')
        ->and((int) $prorroga->granted_by)->toBe($e['coordinador']->id);
});

it('CP-RF29-19 rechaza la prórroga por estudiante sin motivo o con fecha pasada', function (string $hasta, string $motivo) {
    $e = escenario();
    actingAs($e['jefe']);

    expect(fn () => plazo()->prorrogarEstudiante($e['jefe'], $e['estudiante']->id, $hasta, $motivo))->toThrow(ValidationException::class);

    expect(DB::table('enrollment_extensions')->exists())->toBeFalse();
})->with([
    'sin motivo' => ['2026-10-30', ''],
    'fecha pasada' => ['2026-10-25', 'Estuvo hospitalizado durante la inscripción.'],
]);

it('CP-RF29-19 el docente y el Jefe de otro departamento no otorgan prórrogas', function (string $rol) {
    $e = escenario();
    $actor = $rol === 'jefe ajeno' ? plazoJefeDe($e['dcnm']) : $e[$rol];
    actingAs($actor);

    expect(fn () => plazo()->prorrogarEstudiante($actor, $e['estudiante']->id, '2026-10-30', 'Estuvo hospitalizado durante la inscripción.'))
        ->toThrow(AuthorizationException::class);

    expect(DB::table('enrollment_extensions')->exists())->toBeFalse();
})->with(['docente', 'jefe ajeno']);

it('CP-RF29-19 la pantalla de plazos busca al estudiante de su ámbito y le otorga la prórroga', function () {
    $e = escenario();
    actingAs($e['coordinador']);

    Livewire::test(PlazosMatricula::class)
        ->set('estudianteBuscar', 'gs21004')->assertSee($e['estudiante']->first_name)
        ->set('estudianteId', $e['estudiante']->id)->set('hastaEstudiante', '2026-10-25')->set('motivoEstudiante', 'Estuvo hospitalizado durante la inscripción.')
        ->call('otorgarProrroga')->assertHasErrors(['hastaEstudiante'])
        ->set('hastaEstudiante', '2026-10-30')->call('otorgarProrroga')->assertHasNoErrors()
        ->assertSee(['30/10/2026', 'Estuvo hospitalizado durante la inscripción.']);

    expect(substr((string) DB::table('enrollment_extensions')->where('student_id', $e['estudiante']->id)->value('until'), 0, 10))->toBe('2026-10-30');
});

it('CP-RF29-20 manda la fecha del departamento de la carrera del estudiante, no la del que ofrece la materia', function () {
    $e = escenario();
    $estudiante = plazoEstudiante($e);
    // Una materia del plan de ISI que ofrece DCNM: el estudiante de ISI depende de DIA.
    $mat = plazoMateria($e, 'MAT215', $e['dcnm']);
    plazoDelDepartamento($e, $e['dia'], '2026-10-20');
    actingAs($estudiante);

    expect(plazo()->plazoDe($estudiante->id)['fecha']->toDateString())->toBe('2026-10-20');
    plazoRechaza(fn () => plazo()->automatricular($estudiante, $mat['asignatura'], $mat['clave'], $mat['seccion']), '20/10/2026');

    plazoDelDepartamento($e, $e['dia'], '2026-12-11');
    plazoDelDepartamento($e, $e['dcnm'], '2026-10-20');
    plazo()->automatricular($estudiante, $mat['asignatura'], $mat['clave'], $mat['seccion']);

    expect(plazoInscrito($estudiante->id, $mat['seccion']))->toBeTrue();
});
