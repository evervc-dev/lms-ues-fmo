<?php

use App\Modules\Academica\Livewire\CargaAcademica;
use App\Modules\Academica\Livewire\Catalogo;
use App\Modules\Academica\Livewire\ClavesMatricula;
use App\Modules\Academica\Livewire\InscripcionDetalle;
use App\Modules\Academica\Livewire\Matricula;
use App\Modules\Academica\Livewire\MisMaterias;
use App\Modules\Academica\Livewire\PeriodoDetalle;
use App\Modules\Academica\Livewire\Periodos;
use App\Modules\Academica\Livewire\SolicitudesCambio;
use App\Modules\Academica\Services\MatriculaService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/** Otra sección de PRN115 (grupo 02) con horario, para cambios de grupo y choques. */
function grupoDos(array $e, string $inicio = '13:00', string $fin = '14:40'): int
{
    return DbContext::system(function () use ($e, $inicio, $fin) {
        $id = DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $e['periodo'], 'subject_id' => $e['prn'], 'code' => '02', 'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('section_schedules')->insert(['section_id' => $id, 'period_id' => $e['periodo'], 'weekday' => 1, 'start_time' => $inicio, 'end_time' => $fin, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    });
}

/**
 * PRN115 pasa al ciclo 2 del plan y el estudiante del escenario queda retirado de ella: así la puede inscribir con clave
 * en 2026-II (ciclo II, solo ciclos pares; ADR-050).
 */
function prnInscribible(array $e): void
{
    DbContext::system(function () use ($e): void {
        DB::table('career_subjects')->where('study_plan_id', $e['plan'])->where('subject_id', $e['prn'])->update(['cycle' => 2]);
        DB::table('enrollments')->where('id', $e['matricula'])->update(['status' => 'withdrawn']);
    });
}

describe('períodos (RF06)', function () {
    it('creates a period in planning with three grading components', function () {
        $e = escenario();
        actingAs($e['administrador']);

        Livewire::test(Periodos::class)->set('nombre', '2027-I')->set('inicio', '2027-02-01')->set('fin', '2027-06-18')->call('crear')->assertHasNoErrors();

        $periodo = DB::table('academic_periods')->where('name', '2027-I')->first();
        expect($periodo->status)->toBe('planned')
            ->and(DB::table('grading_component_templates')->where('period_id', $periodo->id)->sum('weight_percent'))->toEqual(100);
    });

    it('shows the whole weights of the grading components without cutting their zeros', function () {
        $e = escenario();
        actingAs($e['administrador']);
        Livewire::test(Periodos::class)->set('nombre', '2027-I')->set('inicio', '2027-02-01')->set('fin', '2027-06-18')->call('crear');

        $this->get(route('periodos.show', ['periodo' => DB::table('academic_periods')->where('name', '2027-I')->value('id')]))
            ->assertOk()->assertSeeText(['30 %', '40 %']);
    });

    it('CP-RF06-01 refuses to activate a second period while one is in course', function () {
        $e = escenario();
        $id = DbContext::system(fn () => DB::table('academic_periods')->insertGetId(['name' => '2027-I', 'start_date' => '2027-02-01', 'end_date' => '2027-06-18', 'status' => 'planned', 'preparation_stage' => 'ready', 'created_at' => now(), 'updated_at' => now()]));
        actingAs($e['administrador']);

        Livewire::test(PeriodoDetalle::class, ['periodo' => $id])->call('activar')->assertDispatched('aviso', tipo: 'error');

        expect(DB::table('academic_periods')->where('id', $id)->value('status'))->toBe('planned');
    });
});

describe('catálogo (RF07)', function () {
    it('CP-RF07-01 rejects a duplicated subject code', function () {
        $e = escenario();
        actingAs($e['administrador']);

        Livewire::test(Catalogo::class)->call('nueva')->set('codigo', 'prn115')->set('nombre', 'Otra')->set('departamentoId', $e['dia'])->call('guardar')->assertHasErrors(['codigo']);
    });

    it('CP-RF07-03 rejects a prerequisite cycle', function () {
        $e = escenario();
        DbContext::system(fn () => DB::table('subject_prerequisites')->insert(['subject_id' => $e['prn'], 'prerequisite_id' => $e['mat']]));
        actingAs($e['administrador']);

        Livewire::test(Catalogo::class)->call('editar', $e['mat'])->set('prerrequisitos', [$e['prn']])->call('guardar')->assertHasErrors(['prerrequisitos']);
    });
});

describe('carga académica (RF08)', function () {
    it('CP-RF08-01 rejects a classroom clash naming the section that holds the room', function () {
        $e = escenario();
        $otra = grupoDos($e);
        actingAs($e['coordinador']);

        Livewire::test(CargaAcademica::class)->set('seccionId', $otra)->set('dia', 1)->set('inicio', '07:30')->set('fin', '09:00')->set('aulaId', $e['aula'])
            ->call('agregarHorario')->assertHasErrors(['aula' => __('El aula ya está ocupada en ese horario por :seccion.', ['seccion' => 'PRN115 · 01'])]);

        expect(DB::table('section_schedules')->where('section_id', $otra)->count())->toBe(1);
    });

    it('CP-RF08-02 lets a section have a responsible teacher and a co-teacher', function () {
        $e = escenario();
        $coDocente = usuarioCon('docente');
        DbContext::system(fn () => DB::table('teacher_profiles')->insert(['user_id' => $coDocente->id, 'department_id' => $e['dia'], 'created_at' => now(), 'updated_at' => now()]));
        actingAs($e['coordinador']);

        Livewire::test(CargaAcademica::class)->set('seccionId', $e['seccion'])->set('docenteId', $coDocente->id)->set('responsable', false)
            ->call('asignarDocente')->assertHasNoErrors()->assertSee('co-docente');

        $vigentes = DB::table('teaching_assignments')->where('section_id', $e['seccion'])->whereNull('ended_at')->orderByDesc('is_responsible')->get();
        expect($vigentes->pluck('teacher_id')->map(fn ($id) => (int) $id)->all())->toBe([$e['docente']->id, $coDocente->id])
            ->and((bool) $vigentes[0]->is_responsible)->toBeTrue()
            ->and((bool) $vigentes[1]->is_responsible)->toBeFalse();
    });

    it('CP-RF08-01 rejects a teacher who would have two sections at the same time', function () {
        $e = escenario();
        $otra = grupoDos($e, '07:00', '08:40');
        actingAs($e['coordinador']);

        Livewire::test(CargaAcademica::class)->set('seccionId', $otra)->set('docenteId', $e['docente']->id)->call('asignarDocente')->assertHasErrors(['docente']);
    });

    it('only lists the sections of the coordinator career', function () {
        $e = escenario();
        actingAs($e['coordinador']);

        Livewire::test(CargaAcademica::class)->assertSee('PRN115')->assertDontSee('MAT115');
    });
});

describe('matrícula (RF09, RF29)', function () {
    it('CP-RF09-06 rejects enrolling the same student twice in a subject', function () {
        $e = escenario();
        $otra = grupoDos($e);
        actingAs($e['coordinador']);

        Livewire::test(Matricula::class)->set('estudianteId', $e['estudiante']->id)->set('seccionId', $otra)->call('inscribir')->assertHasErrors(['seccion']);
    });

    it('CP-RF09-01 rejects a full section', function () {
        $e = escenario();
        DbContext::system(fn () => DB::table('sections')->where('id', $e['seccion'])->update(['max_capacity' => 1]));
        $otro = usuarioCon('estudiante');
        DbContext::system(fn () => DB::table('student_careers')->insert(['student_id' => $otro->id, 'career_id' => $e['isi'], 'study_plan_id' => $e['plan'], 'current_cycle' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]));

        expect(fn () => app(MatriculaService::class)->inscribir($e['coordinador'], $otro->id, $e['seccion']))
            ->toThrow(ValidationException::class, 'La sección está llena.');
    });

    it('withdraws with a reason but never by a teacher', function () {
        $e = escenario();

        actingAs($e['coordinador']);
        Livewire::test(Matricula::class)->call('confirmarRetiro', $e['matricula'])->set('motivo', 'Retiro voluntario.')->call('retirar')->assertHasNoErrors();
        expect(DB::table('enrollments')->where('id', $e['matricula'])->value('status'))->toBe('withdrawn');

        expect(fn () => app(MatriculaService::class)->retirar($e['docente'], $e['matricula'], 'x'))->toThrow(AuthorizationException::class);
    });

    it('lets a student enroll with the subject key', function () {
        $e = escenario();
        prnInscribible($e);
        actingAs($e['coordinador']);
        $clave = Livewire::test(ClavesMatricula::class)->call('generar', $e['prn'])->get('claveNueva');

        actingAs($e['estudiante']);
        Livewire::test(InscripcionDetalle::class, ['asignatura' => $e['prn']])
            ->set('seccionElegida', $e['seccion'])->set('clave', 'CLAVE-FALSA')->call('inscribirme')->assertHasErrors(['clave'])
            ->set('clave', $clave)->call('inscribirme')->assertHasNoErrors();

        expect(DB::table('enrollments')->where('student_id', $e['estudiante']->id)->where('status', 'active')->value('enrolled_via'))->toBe('key');
    });

    it('CP-RF29-05 bloquea la auto-matrícula 15 minutos tras 5 claves fallidas', function () {
        $e = escenario();
        prnInscribible($e);
        actingAs($e['coordinador']);
        $clave = Livewire::test(ClavesMatricula::class)->call('generar', $e['prn'])->get('claveNueva');
        actingAs($e['estudiante']);
        $matricula = app(MatriculaService::class);

        foreach (range(1, 5) as $intento) {
            expect(fn () => $matricula->automatricular($e['estudiante'], $e['prn'], 'CLAVE-FALSA', $e['seccion']))->toThrow(ValidationException::class, 'no es válida');
        }
        expect(fn () => $matricula->automatricular($e['estudiante'], $e['prn'], $clave, $e['seccion']))->toThrow(ValidationException::class, 'Demasiados intentos');

        $this->travel(16)->minutes();
        $matricula->automatricular($e['estudiante'], $e['prn'], $clave, $e['seccion']);

        expect(DB::table('enrollments')->where('student_id', $e['estudiante']->id)->where('section_id', $e['seccion'])->where('status', 'active')->value('enrolled_via'))->toBe('key');
    });

    it('CP-RF09-07 recibe la solicitud de cambio de grupo dentro del plazo y la rechaza fuera de él', function () {
        $e = escenario();
        $dos = grupoDos($e);
        actingAs($e['estudiante']);

        $this->travelTo('2026-08-18 10:00');
        Livewire::test(MisMaterias::class)->call('abrirCambio', $e['matricula'])->assertSee('terminó el 17/08/2026');
        expect(fn () => app(MatriculaService::class)->solicitarCambio($e['estudiante'], $e['matricula'], $dos, 'Trabajo por las mañanas'))
            ->toThrow(ValidationException::class, 'Fuera de plazo');

        $this->travelTo('2026-08-10 10:00');
        Livewire::test(MisMaterias::class)->call('abrirCambio', $e['matricula'])->assertSee('Grupo 02')
            ->set('destino', $dos)->set('justificacion', 'Trabajo por las mañanas')->call('solicitarCambio')->assertHasNoErrors();

        expect(DB::table('section_change_requests')->where('enrollment_id', $e['matricula'])->first())
            ->status->toBe('pending')->target_section_id->toBe($dos);
    });

    it('approves a group change and transfers the enrollment', function () {
        $e = escenario();
        $destino = grupoDos($e);
        DbContext::system(fn () => DB::table('section_change_requests')->insert(['enrollment_id' => $e['matricula'], 'target_section_id' => $destino, 'justification' => 'Trabajo por la mañana', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
        actingAs($e['coordinador']);

        Livewire::test(SolicitudesCambio::class)->assertSee('Trabajo por la mañana')->call('aprobar', DB::table('section_change_requests')->value('id'));

        expect(DB::table('enrollments')->where('id', $e['matricula'])->value('status'))->toBe('transferred')
            ->and(DB::table('enrollments')->where('student_id', $e['estudiante']->id)->where('status', 'active')->value('section_id'))->toBe($destino);
    });
});

it('renders the academic screens for their roles', function () {
    $e = escenario();

    actingAs($e['administrador'])->get(route('periodos'))->assertOk()->assertSeeText('2026-II');
    actingAs($e['administrador'])->get(route('catalogo.estructura'))->assertOk()->assertSeeText(['DIA', 'ISI', 'A-101']);
    actingAs($e['docente'])->get(route('secciones'))->assertOk()->assertSeeText(['Mis secciones', 'PRN115']);
    actingAs($e['coordinador'])->get(route('oferta'))->assertOk()->assertSeeText(['Estadísticas de oferta', 'PRN115'])->assertDontSeeText('MAT115');
    actingAs($e['estudiante'])->get(route('materias'))->assertOk()->assertSeeText(['Mis materias', 'Programación I']);
    actingAs($e['docente'])->get(route('secciones.estudiantes', ['seccion' => $e['ulid']]))->assertOk()->assertSeeText(['GS21004'])->assertDontSee('confirmarRetiro');
});
