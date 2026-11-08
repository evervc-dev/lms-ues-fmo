<?php

use App\Models\User;
use App\Modules\Academica\Livewire\CarreraDetalle;
use App\Modules\Academica\Livewire\Carreras;
use App\Modules\Academica\Services\CarreraService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Carreras visibles del Jefe de Departamento (alcance D) y del Coordinador de Carrera (alcance C),
 * con el permiso `carreras.ver` (ADR-047): la lista con el coordinador vigente y la oferta del
 * período en curso, y el detalle con docentes, materias, grupos y horarios.
 */
function carrerasServicio(): CarreraService
{
    return app(CarreraService::class);
}

function carreraNombreDe(User $usuario): string
{
    return $usuario->first_name.' '.$usuario->last_name;
}

/** Código HTTP del abort() que lanza el Service, o 200 si no aborta. */
function carrerasEstadoHttp(Closure $accion): int
{
    try {
        $accion();
    } catch (HttpException $excepcion) {
        return $excepcion->getStatusCode();
    }

    return 200;
}

/**
 * Añade al escenario otra carrera del mismo departamento (ICV · DIA, sin plan ni coordinador) y una
 * de otro departamento (LMA · DCNM, con MAT115 en su plan), para separar el alcance D del C.
 *
 * @param  array<string, mixed>  $e
 * @return array{icv: int, lma: int, planLma: int}
 */
function carrerasAdicionales(array $e): array
{
    return DbContext::system(function () use ($e) {
        $t = fn (array $fila) => $fila + ['created_at' => now(), 'updated_at' => now()];
        $icv = DB::table('careers')->insertGetId($t(['department_id' => $e['dia'], 'code' => 'ICV', 'name' => 'Ingeniería Civil']));
        $lma = DB::table('careers')->insertGetId($t(['department_id' => $e['dcnm'], 'code' => 'LMA', 'name' => 'Licenciatura en Matemática']));
        $planLma = DB::table('study_plans')->insertGetId($t(['career_id' => $lma, 'name' => 'Plan 20']));
        DB::table('career_subjects')->insert(['study_plan_id' => $planLma, 'subject_id' => $e['mat'], 'cycle' => 1]);

        return compact('icv', 'lma', 'planLma');
    });
}

/**
 * Otro grupo (02) de PRN115 en el período en curso, con el docente indicado y un horario sin aula.
 *
 * @param  array<string, mixed>  $e
 */
function carrerasGrupoDos(array $e, int $docenteId): int
{
    return DbContext::system(function () use ($e, $docenteId) {
        $t = fn (array $fila) => $fila + ['created_at' => now(), 'updated_at' => now()];
        $seccion = DB::table('sections')->insertGetId($t(['ulid' => (string) str()->ulid(), 'period_id' => $e['periodo'], 'subject_id' => $e['prn'], 'code' => '02', 'max_capacity' => 30]));
        DB::table('teaching_assignments')->insert($t(['section_id' => $seccion, 'teacher_id' => $docenteId, 'is_responsible' => true, 'started_at' => '2026-07-01']));
        DB::table('section_schedules')->insert($t(['section_id' => $seccion, 'period_id' => $e['periodo'], 'classroom_id' => null, 'weekday' => 2, 'start_time' => '09:00', 'end_time' => '10:40']));

        return $seccion;
    });
}

describe('lista de carreras', function () {
    it('muestra al jefe de departamento las carreras de su departamento y ninguna de otro', function () {
        $e = escenario();
        $otras = carrerasAdicionales($e);

        $carreras = carrerasServicio()->listar($e['jefe']);

        expect($carreras->pluck('code')->all())->toBe(['ICV', 'ISI'])
            ->and($carreras->pluck('id')->all())->toBe([$otras['icv'], $e['isi']])
            ->and($carreras->pluck('code'))->not->toContain('LMA')
            ->and($carreras->firstWhere('code', 'ISI')->departamento)->toBe('Ingeniería y Arquitectura');
    });

    it('muestra al coordinador solo la carrera que coordina', function () {
        $e = escenario();
        carrerasAdicionales($e);

        $carreras = carrerasServicio()->listar($e['coordinador']);

        expect($carreras->pluck('code')->all())->toBe(['ISI'])
            ->and($carreras->first()->id)->toBe($e['isi']);
    });

    it('trae en cada carrera su coordinador vigente y los conteos de materias, grupos y docentes del período en curso', function () {
        $e = escenario();
        $otras = carrerasAdicionales($e);

        $carreras = carrerasServicio()->listar($e['jefe'])->keyBy('code');

        expect($carreras['ISI']->coordinador)->toBe(carreraNombreDe($e['coordinador']))
            ->and($carreras['ISI']->materias)->toBe(1)
            ->and($carreras['ISI']->grupos)->toBe(1)
            ->and($carreras['ISI']->docentes)->toBe(1)
            ->and($carreras['ICV']->coordinador)->toBeNull()
            ->and($carreras['ICV']->materias)->toBe(0)
            ->and($carreras['ICV']->grupos)->toBe(0)
            ->and($carreras['ICV']->docentes)->toBe(0)
            ->and($otras['icv'])->toBe($carreras['ICV']->id);
    });

    it('no repite al docente que imparte varios grupos de la carrera', function () {
        $e = escenario();
        carrerasGrupoDos($e, $e['docente']->id);

        $isi = carrerasServicio()->listar($e['jefe'])->firstWhere('code', 'ISI');
        $docentes = carrerasServicio()->detalle($e['jefe'], $e['isi'])['docentes'];

        expect($isi->grupos)->toBe(2)
            ->and($isi->docentes)->toBe(1)
            ->and($docentes)->toHaveCount(1)
            ->and($docentes->first()->nombre)->toBe(carreraNombreDe($e['docente']))
            ->and($docentes->first()->correo)->toBe($e['docente']->institutional_email)
            ->and($docentes->first()->grupos)->toBe(2);
    });

    it('sin período en curso no cuenta grupos ni docentes y conserva las materias del plan', function () {
        $e = escenario();
        DbContext::system(fn () => DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => 'closed']));

        $isi = carrerasServicio()->listar($e['jefe'])->firstWhere('code', 'ISI');
        $detalle = carrerasServicio()->detalle($e['jefe'], $e['isi']);

        expect($isi->materias)->toBe(1)
            ->and($isi->grupos)->toBe(0)
            ->and($isi->docentes)->toBe(0)
            ->and($detalle['periodo'])->toBeNull()
            ->and($detalle['docentes'])->toBeEmpty()
            ->and($detalle['materias']->pluck('code')->all())->toBe(['PRN115'])
            ->and($detalle['materias']->first()->grupos)->toBeEmpty();
    });
});

describe('detalle de una carrera', function () {
    it('lista las materias del plan con su grupo, docente, matriculados activos y horario formateado', function () {
        $e = escenario();
        $retirado = usuarioCon('estudiante');
        DbContext::system(fn () => DB::table('enrollments')->insert(['student_id' => $retirado->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'],
            'subject_id' => $e['prn'], 'status' => 'withdrawn', 'enrolled_via' => 'key', 'created_at' => now(), 'updated_at' => now()]));

        $detalle = carrerasServicio()->detalle($e['coordinador'], $e['isi']);
        $materia = $detalle['materias']->firstWhere('code', 'PRN115');
        $grupo = $materia->grupos->first();

        expect($detalle['carrera']->code)->toBe('ISI')
            ->and($detalle['periodo'])->toBe('2026-II')
            ->and($detalle['planes'])->toBe(['Plan 25'])
            ->and($materia->ciclo)->toEqual(1)
            ->and($materia->grupos)->toHaveCount(1)
            ->and($grupo->grupo)->toBe('01')
            ->and($grupo->max_capacity)->toEqual(40)
            ->and($grupo->inscritos)->toEqual(1)
            ->and($grupo->docentes)->toBe([carreraNombreDe($e['docente'])])
            ->and($grupo->horario)->toBe(['Lun 07:00–08:40 · A-101']);
    });

    it('formatea el horario sin aula cuando el grupo no la tiene asignada', function () {
        $e = escenario();
        carrerasGrupoDos($e, $e['docente']->id);

        $grupos = carrerasServicio()->detalle($e['jefe'], $e['isi'])['materias']->firstWhere('code', 'PRN115')->grupos->keyBy('grupo');

        expect($grupos['02']->horario)->toBe(['Mar 09:00–10:40'])
            ->and($grupos['02']->inscritos)->toEqual(0);
    });

    it('muestra la materia del plan sin grupos cuando no hay oferta en el período en curso', function () {
        $e = escenario();
        DbContext::system(fn () => DB::table('career_subjects')->insert(['study_plan_id' => $e['plan'], 'subject_id' => $e['mat'], 'cycle' => 2]));

        $detalle = carrerasServicio()->detalle($e['jefe'], $e['isi']);
        $materias = $detalle['materias']->keyBy('code');

        expect($detalle['materias']->pluck('code')->all())->toBe(['PRN115', 'MAT115'])
            ->and($materias['MAT115']->grupos)->toHaveCount(1)
            ->and($materias['MAT115']->grupos->first()->docentes)->toBe([]);
    });

    it('toma el coordinador vigente y descarta el nombramiento ya cerrado', function () {
        $e = escenario();
        $nuevo = usuarioCon('coordinador');
        DbContext::system(fn () => DB::table('career_coordinators')->where('career_id', $e['isi'])->update(['valid_to' => '2026-07-01']));

        expect(carrerasServicio()->detalle($e['jefe'], $e['isi'])['coordinador'])->toBeNull();

        DbContext::system(fn () => DB::table('career_coordinators')->insert(['user_id' => $nuevo->id, 'career_id' => $e['isi'],
            'valid_from' => '2026-07-02', 'created_at' => now(), 'updated_at' => now()]));

        $coordinador = carrerasServicio()->detalle($e['jefe'], $e['isi'])['coordinador'];

        expect($coordinador->nombre)->toBe(carreraNombreDe($nuevo))
            ->and($coordinador->id)->toEqual($nuevo->id)
            ->and(carrerasServicio()->listar($e['jefe'])->firstWhere('code', 'ISI')->coordinador)->toBe(carreraNombreDe($nuevo));
    });

    it('prohíbe el detalle de una carrera fuera del alcance del actor', function () {
        $e = escenario();
        $otras = carrerasAdicionales($e);

        expect(carrerasEstadoHttp(fn () => carrerasServicio()->detalle($e['jefe'], $otras['lma'])))->toBe(403)
            ->and(carrerasEstadoHttp(fn () => carrerasServicio()->detalle($e['coordinador'], $otras['icv'])))->toBe(403);

        actingAs($e['coordinador'])->get(route('carreras.show', ['carrera' => $otras['icv']]))->assertForbidden();
        actingAs($e['jefe'])->get(route('carreras.show', ['carrera' => $otras['lma']]))->assertForbidden();
    });

    it('responde 404 en el detalle de una carrera que no existe', function () {
        $e = escenario();

        expect(fn () => carrerasServicio()->detalle($e['jefe'], 999999))->toThrow(NotFoundHttpException::class);

        actingAs($e['jefe'])->get(route('carreras.show', ['carrera' => 999999]))->assertNotFound();
    });
});

describe('permiso carreras.ver', function () {
    it('niega las carreras al rol sin el permiso', function (string $rol) {
        $e = escenario();
        $usuario = $e[$rol];

        expect(fn () => carrerasServicio()->listar($usuario))->toThrow(AuthorizationException::class)
            ->and(fn () => carrerasServicio()->detalle($usuario, $e['isi']))->toThrow(AuthorizationException::class);

        actingAs($usuario)->get(route('carreras'))->assertForbidden();
        actingAs($usuario)->get(route('carreras.show', ['carrera' => $e['isi']]))->assertForbidden();
    })->with(['docente', 'estudiante', 'soporte', 'administrador']);
});

describe('pantallas', function () {
    it('rinde la lista de carreras del jefe con enlace al detalle y la del coordinador', function () {
        $e = escenario();
        $otras = carrerasAdicionales($e);

        actingAs($e['jefe'])->get(route('carreras'))->assertOk()
            ->assertSeeText(['ISI', 'Ingeniería de Sistemas Informáticos', 'Ingeniería Civil', carreraNombreDe($e['coordinador']), 'Sin coordinador'])
            ->assertDontSeeText('Licenciatura en Matemática')
            ->assertSee(route('carreras.show', ['carrera' => $e['isi']]), false)
            ->assertSee(route('carreras.show', ['carrera' => $otras['icv']]), false);

        actingAs($e['coordinador'])->get(route('carreras'))->assertOk()
            ->assertSeeText(['ISI', 'Ingeniería de Sistemas Informáticos'])
            ->assertDontSeeText('Ingeniería Civil');
    });

    it('rinde el detalle de la carrera para el jefe y para el coordinador', function (string $rol) {
        $e = escenario();
        $actor = $rol === 'jefe' ? $e['jefe'] : $e['coordinador'];

        actingAs($actor);
        Livewire::test(CarreraDetalle::class, ['carrera' => $e['isi']])
            ->assertSeeText(['Ingeniería de Sistemas Informáticos', 'Plan 25', '2026-II', carreraNombreDe($e['coordinador']),
                'PRN115', 'Programación I', 'Grupo 01', carreraNombreDe($e['docente']), 'Lun 07:00–08:40 · A-101', '1 de 40']);
    })->with(['jefe', 'coordinador']);

    it('avisa al jefe sin carreras que no tiene ninguna en su ámbito', function () {
        $e = escenario();
        $sinCarreras = usuarioCon('jefe_departamento');
        DbContext::system(fn () => DB::table('department_heads')->insert(['user_id' => $sinCarreras->id, 'department_id' => $e['dcnm'],
            'valid_from' => '2025-01-01', 'created_at' => now(), 'updated_at' => now()]));

        actingAs($sinCarreras);
        Livewire::test(Carreras::class)->assertSeeText('No hay carreras')->assertDontSeeText('Ingeniería de Sistemas Informáticos');
    });
});
