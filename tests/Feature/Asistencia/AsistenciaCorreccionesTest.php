<?php

use App\Modules\Asistencia\Livewire\AsistenciaSeccion;
use App\Modules\Asistencia\Livewire\EscanearQr;
use App\Modules\Asistencia\Livewire\SesionAsistencia;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Correcciones de la revisión de flujos del 21/11/2026 (Asistencia): período no activo, una sesión por día,
 * sesión virtual solo por Meet y validaciones del código temporal.
 */
beforeEach(function () {
    $this->e = escenario();
    $this->asistencia = app(AsistenciaService::class);
    actingAs($this->e['docente']);
});

function cerrarPeriodoDelEscenario(array $e): void
{
    DbContext::system(fn () => DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => 'closed']));
}

it('CP-RF19-08 no abre ni corrige asistencia con el período del grupo cerrado', function () {
    $sesion = $this->asistencia->abrir($this->e['docente'], $this->e['seccion'], 10);
    cerrarPeriodoDelEscenario($this->e);

    expect(fn () => $this->asistencia->marcar($this->e['docente'], $this->e['seccion'], $sesion, $this->e['matricula'], 'present'))
        ->toThrow(ValidationException::class, 'no está activo');

    $this->travel(1)->days();
    expect(fn () => $this->asistencia->abrir($this->e['docente'], $this->e['seccion'], 10))->toThrow(ValidationException::class, 'no está activo');
    Livewire::test(AsistenciaSeccion::class, ['seccion' => $this->e['ulid']])->assertSee(__('Período no activo'))->assertDontSee(__('Abrir asistencia'));
});

it('CP-RF19-08 la sesión que vence con el período ya cerrado se cierra sin escribir ausentes y no frena a las demás', function () {
    $sesion = $this->asistencia->abrir($this->e['docente'], $this->e['seccion'], 10);
    cerrarPeriodoDelEscenario($this->e);
    $this->travel(11)->minutes();

    expect($this->asistencia->cerrarVencidas())->toBe(1)
        ->and(DB::table('attendance_sessions')->where('ulid', $sesion)->value('status'))->toBe('closed')
        ->and(DB::table('attendance_records')->count())->toBe(0);
});

it('CP-RF19-09 una sola sesión por grupo y día: la segunda se rechaza y al día siguiente se abre', function () {
    $sesion = $this->asistencia->abrir($this->e['docente'], $this->e['seccion'], 10);
    $this->asistencia->cerrar($this->e['docente'], $this->e['seccion'], $sesion);

    expect(fn () => $this->asistencia->abrir($this->e['docente'], $this->e['seccion'], 10))->toThrow(ValidationException::class, 'Ya se tomó la asistencia de hoy');

    $this->travel(1)->days();
    expect($this->asistencia->abrir($this->e['docente'], $this->e['seccion'], 10))->toBeString();
});

it('CP-RF21-09 una sesión abierta a mano es presencial: la virtual va por el reporte de Meet', function () {
    expect(fn () => $this->asistencia->abrir($this->e['docente'], $this->e['seccion'], 10, 'virtual'))->toThrow(ValidationException::class, 'reporte de Meet');
    expect(DB::table('attendance_sessions')->count())->toBe(0);
});

it('CP-RF28-05 no emite código para una matrícula de otro grupo', function () {
    $ajena = DbContext::system(fn () => DB::table('enrollments')->insertGetId(['student_id' => $this->e['estudiante']->id, 'section_id' => $this->e['otra'],
        'period_id' => $this->e['periodo'], 'subject_id' => $this->e['mat'], 'status' => 'active', 'enrolled_via' => 'key', 'created_at' => now(), 'updated_at' => now()]));
    $sesion = $this->asistencia->abrir($this->e['docente'], $this->e['seccion'], 10);

    expect(fn () => $this->asistencia->generarCodigo($this->e['docente'], $this->e['seccion'], $sesion, $ajena))->toThrow(HttpException::class);
});

it('CP-RF28-05 usar el código cuando ya lo marcaron a mano avisa en vez de fallar', function () {
    $sesion = $this->asistencia->abrir($this->e['docente'], $this->e['seccion'], 10);
    $codigo = $this->asistencia->generarCodigo($this->e['docente'], $this->e['seccion'], $sesion, $this->e['matricula']);
    $this->asistencia->marcar($this->e['docente'], $this->e['seccion'], $sesion, $this->e['matricula'], 'present');
    actingAs($this->e['estudiante']);

    expect(fn () => $this->asistencia->usarCodigo($this->e['estudiante'], $codigo))->toThrow(ValidationException::class, 'Ya tiene registrada');
});

it('CP-RF32-04 la cancelación de una carrera alcanza al grupo entero de una materia compartida (ADR-080)', function () {
    $e = $this->e;
    [$industrial, $grupoIndustrial] = DbContext::system(function () use ($e) {
        $ind = DB::table('careers')->insertGetId(['department_id' => $e['dia'], 'code' => 'IND', 'name' => 'Ingeniería Industrial', 'created_at' => now(), 'updated_at' => now()]);
        $plan = DB::table('study_plans')->insertGetId(['career_id' => $ind, 'name' => 'Plan 26', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('career_subjects')->insert(['study_plan_id' => $plan, 'subject_id' => $e['prn'], 'cycle' => 2]);
        // Solicitado por Industrial, pero lo comparten ISI e Industrial.
        $grupo = DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $e['periodo'], 'subject_id' => $e['prn'], 'career_id' => $ind,
            'code' => '02', 'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);

        return [$ind, $grupo];
    });

    $deIsi = AsistenciaService::seccionesDeCancelacion((object) ['scope' => 'career', 'career_id' => $e['isi'], 'section_id' => null]);
    $deIndustrial = AsistenciaService::seccionesDeCancelacion((object) ['scope' => 'career', 'career_id' => $industrial, 'section_id' => null]);

    expect($deIsi->all())->toContain($e['seccion'], $grupoIndustrial)->not->toContain($e['otra'])
        ->and($deIndustrial->all())->toContain($e['seccion'], $grupoIndustrial)
        ->and(AsistenciaService::carrerasDeSeccion($grupoIndustrial)->all())->toEqualCanonicalizing([$e['isi'], $industrial]);

    // Una clase cancelada por Industrial no cuenta para el estudiante de ISI del grupo compartido.
    $sesion = $this->asistencia->abrir($e['docente'], $e['seccion'], 10);
    $this->asistencia->cerrar($e['docente'], $e['seccion'], $sesion);
    DbContext::system(fn () => DB::table('class_cancellations')->insert(['scope' => 'career', 'career_id' => $industrial, 'date' => now()->toDateString(), 'kind' => 'cancellation',
        'reason' => 'Actividad de la carrera.', 'status' => 'approved', 'requested_by' => $e['coordinador']->id, 'created_at' => now(), 'updated_at' => now()]));
    expect(DB::table('attendance_records as r')->where('r.enrollment_id', $e['matricula'])->whereRaw(AsistenciaService::cuentaSql())->count())->toBe(0);
});

it('CP-RF32-05 el docente pide el cambio de modalidad en los dos sentidos', function () {
    $sesion = $this->asistencia->abrir($this->e['docente'], $this->e['seccion'], 10);

    Livewire::test(SesionAsistencia::class, ['seccion' => $this->e['ulid'], 'sesion' => $sesion])
        ->set('fechaCancelacion', now()->addDay()->toDateString())->set('tipoCancelacion', 'in_person')->set('motivoCancelacion', 'Vuelve el aula.')
        ->call('solicitarCancelacion')->assertHasNoErrors();

    expect(DB::table('class_cancellations')->first(['kind', 'new_modality']))->toEqual((object) ['kind' => 'modality_change', 'new_modality' => 'in_person']);
});

it('CP-RF20-14 marcar en una materia no oculta el lector de otra con sesión abierta', function () {
    $e = $this->e;
    DbContext::system(function () use ($e) {
        DB::table('enrollments')->insert(['student_id' => $e['estudiante']->id, 'section_id' => $e['otra'], 'period_id' => $e['periodo'], 'subject_id' => $e['mat'],
            'status' => 'active', 'enrolled_via' => 'key', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('attendance_sessions')->insert(['ulid' => (string) str()->ulid(), 'section_id' => $e['otra'], 'session_date' => now()->toDateString(), 'status' => 'open',
            'opened_at' => now(), 'qr_validity_minutes' => 10, 'opened_by' => $e['docente']->id, 'created_at' => now(), 'updated_at' => now()]);
    });
    $sesion = $this->asistencia->abrir($e['docente'], $e['seccion'], 10);
    $this->asistencia->marcar($e['docente'], $e['seccion'], $sesion, $e['matricula'], 'present');
    actingAs($e['estudiante']);

    Livewire::test(EscanearQr::class)->assertDontSee(__('Su asistencia de esta clase ya quedó registrada.'));
});
