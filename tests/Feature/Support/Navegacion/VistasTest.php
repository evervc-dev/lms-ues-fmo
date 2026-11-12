<?php

use App\Modules\Asistencia\Services\AsistenciaService;
use App\Support\DbContext;
use App\Support\Navegacion\Vistas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Registro de vistas y esqueleto navegable (ADR-035, config/vistas.php).
 */
function seccionDePrueba(): string
{
    return DbContext::system(function (): string {
        $departamento = DB::table('departments')->insertGetId(['code' => 'DIA', 'name' => 'Ingeniería', 'created_at' => now(), 'updated_at' => now()]);
        $periodo = DB::table('academic_periods')->insertGetId(['name' => '2026-II', 'start_date' => '2026-07-27', 'end_date' => '2026-12-11', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $materia = DB::table('subjects')->insertGetId(['code' => 'PRN115', 'name' => 'Programación I', 'credits' => 4, 'department_id' => $departamento, 'created_at' => now(), 'updated_at' => now()]);
        $ulid = (string) str()->ulid();
        DB::table('sections')->insert(['ulid' => $ulid, 'period_id' => $periodo, 'subject_id' => $materia, 'code' => '01', 'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);

        return $ulid;
    });
}

/**
 * @return array<string, string>
 */
function parametrosDe(string $uri, string $seccion): array
{
    preg_match_all('/\{(\w+)\??\}/', $uri, $coincidencias);

    return collect($coincidencias[1])->mapWithKeys(fn (string $parametro) => [
        $parametro => match ($parametro) {
            'seccion' => $seccion,
            'usuario', 'periodo', 'hilo', 'banco', 'lote', 'unidad', 'carrera', 'asignatura' => '999999',
            default => 'x',
        },
    ])->all();
}

it('gives every registered view a named route', function () {
    foreach (array_keys(Vistas::todas()) as $nombre) {
        expect(Route::has($nombre))->toBeTrue("La vista {$nombre} no tiene ruta");
    }
});

it('enforces each view permission for the role', function (string $rol) {
    $e = escenario();
    $usuario = ['estudiante' => $e['estudiante'], 'docente' => $e['docente'], 'coordinador' => $e['coordinador'], 'jefe_departamento' => $e['jefe'],
        'administrador' => $e['administrador'], 'soporte_tecnico' => $e['soporte']][$rol];
    $permisos = Role::findByName($rol)->permissions->pluck('name');

    foreach (Vistas::todas() as $nombre => $vista) {
        $permitido = $vista['permiso'] === null
            || collect(explode('|', $vista['permiso']))->intersect($permisos)->isNotEmpty();

        $estado = $this->actingAs($usuario)->get(route($nombre, parametrosDe($vista['uri'], $e['ulid'])))->status();

        // Una vista real con un id inventado («x») responde 404: lo que importa es que el rol permitido no reciba 403.
        $permitido
            ? expect($estado)->toBeIn([200, 404], "{$rol} en {$nombre}: {$estado}")
            : expect($estado)->toBe(403, "{$rol} en {$nombre}: {$estado}");
    }
})->with(['estudiante', 'docente', 'coordinador', 'jefe_departamento', 'administrador', 'soporte_tecnico']);

it('shows the view card with its requirement, task, team and milestone', function () {
    $this->actingAs(usuarioCon('docente'))
        ->get(route(registrarVistaPendiente()))
        ->assertOk()
        ->assertSeeText(['Vista pendiente', 'Pantalla en construcción', 'RF21', 'OP#455', 'Equipo 5 · H3']);
});

it('shows the section header and only the tabs the role may see', function () {
    $e = escenario();

    $this->actingAs($e['estudiante'])
        ->get(route('secciones.notas', ['seccion' => $e['ulid']]))
        ->assertOk()
        ->assertSeeText(['PRN115', 'Programación I', 'Grupo 01'])
        ->assertSeeTextInOrder(['Contenidos', 'Actividades', 'Notas', 'Asistencia', 'Foro'])
        ->assertDontSeeText(['Estudiantes', 'Analítica']);

    $this->actingAs($e['jefe'])
        ->get(route('secciones.estudiantes', ['seccion' => $e['ulid']]))
        ->assertOk()
        ->assertSeeText('Estudiantes')
        ->assertDontSeeText(['Contenidos', 'Foro']);
});

it('forbids a section outside the scope of the role', function () {
    $e = escenario();

    $this->actingAs($e['jefe'])->get(route('secciones.estudiantes', ['seccion' => $e['otraUlid']]))->assertForbidden();
});

it('returns 404 for a section that does not exist', function () {
    $this->actingAs(usuarioCon('estudiante'))
        ->get(route('secciones.contenidos', ['seccion' => '01JZZZZZZZZZZZZZZZZZZZZZZZ']))
        ->assertNotFound();
});

it('renders the projection view without the application menu', function () {
    $e = escenario();
    $sesion = app(AsistenciaService::class)->abrir($e['docente'], $e['seccion'], 10);

    $this->actingAs($e['docente'])->get(route('secciones.asistencia.proyectar', ['seccion' => $e['ulid'], 'sesion' => $sesion]))
        ->assertOk()->assertSeeText('PRN115')->assertDontSee('Menú principal');
});

it('serves registered mockups outside production and nothing else', function () {
    $usuario = usuarioCon('docente');

    $this->actingAs($usuario)->get(route('dev.maqueta', ['archivo' => '21 Sesión de asistencia']))
        ->assertOk()->assertHeader('content-type', 'image/png');
    $this->actingAs($usuario)->get(route('dev.maqueta', ['archivo' => '../../.env']))->assertNotFound();
});

it('shows the open attendance notice only to enrolled students', function () {
    $ulid = seccionDePrueba();
    $estudiante = usuarioCon('estudiante');
    $docente = usuarioCon('docente');
    DbContext::system(function () use ($ulid, $estudiante, $docente) {
        $seccion = DB::table('sections')->where('ulid', $ulid)->first();
        DB::table('enrollments')->insert(['student_id' => $estudiante->id, 'section_id' => $seccion->id, 'period_id' => $seccion->period_id, 'subject_id' => $seccion->subject_id, 'enrolled_via' => 'key', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('attendance_sessions')->insert(['ulid' => (string) str()->ulid(), 'section_id' => $seccion->id, 'session_date' => now()->toDateString(), 'status' => 'open', 'opened_at' => now(), 'opened_by' => $docente->id, 'created_at' => now(), 'updated_at' => now()]);
    });

    $this->actingAs($estudiante)->get(route('inicio'))->assertOk()->assertSeeText('Asistencia abierta · PRN115');
    $this->actingAs($docente)->get(route('inicio'))->assertOk()->assertDontSeeText('Asistencia abierta');
});
