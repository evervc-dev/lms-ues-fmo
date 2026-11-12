<?php

use App\Models\User;
use App\Modules\Contenidos\Services\ContenidoService;
use App\Support\DbContext;
use App\Support\Navegacion\Vistas;
use Database\Seeders\BaseSystemSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\BrowserTestCase;
use Tests\Support\PaginaLenta;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

// Las pruebas de navegador cargan el build real de Vite (tests/BrowserTestCase.php).
pest()->extend(BrowserTestCase::class)->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Usuario con los roles indicados de la Matriz RBAC. Siembra roles y permisos si faltan.
 * En PostgreSQL las escrituras auditadas exigen contexto (DbContext::system).
 */
function usuarioCon(string ...$roles): User
{
    return DbContext::system(function () use ($roles) {
        if (Role::query()->doesntExist()) {
            (new BaseSystemSeeder)->run();
        }

        return User::factory()->create()->assignRole($roles);
    });
}

/**
 * Escenario académico mínimo y coherente para probar vistas con alcance:
 * DIA → ISI (Plan 25) → PRN115 en 2026-II activo → sección 01 con docente, coordinador de ISI,
 * jefe de DIA y un estudiante matriculado. Otra sección (MAT115 · DCNM) queda fuera del alcance de todos.
 * DIA y DCNM tienen la fecha límite de inscripción (RF29) en el fin del período, el 11/12/2026.
 *
 * @return array<string, mixed>
 */
function escenario(): array
{
    $docente = usuarioCon('docente');
    $coordinador = usuarioCon('coordinador');
    $jefe = usuarioCon('jefe_departamento');
    $estudiante = usuarioCon('estudiante');
    $administrador = usuarioCon('administrador');
    $soporte = usuarioCon('soporte_tecnico');

    return DbContext::system(function () use ($docente, $coordinador, $jefe, $estudiante, $administrador, $soporte) {
        $ahora = now();
        $t = fn (array $fila) => $fila + ['created_at' => $ahora, 'updated_at' => $ahora];
        $db = DB::class;

        $dia = $db::table('departments')->insertGetId($t(['code' => 'DIA', 'name' => 'Ingeniería y Arquitectura']));
        $dcnm = $db::table('departments')->insertGetId($t(['code' => 'DCNM', 'name' => 'Ciencias Naturales y Matemática']));
        $isi = $db::table('careers')->insertGetId($t(['department_id' => $dia, 'code' => 'ISI', 'name' => 'Ingeniería de Sistemas Informáticos']));
        $plan = $db::table('study_plans')->insertGetId($t(['career_id' => $isi, 'name' => 'Plan 25']));
        $periodo = $db::table('academic_periods')->insertGetId($t(['name' => '2026-II', 'start_date' => '2026-07-27', 'end_date' => '2026-12-11', 'status' => 'active']));
        $prn = $db::table('subjects')->insertGetId($t(['code' => 'PRN115', 'name' => 'Programación I', 'credits' => 4, 'department_id' => $dia]));
        $mat = $db::table('subjects')->insertGetId($t(['code' => 'MAT115', 'name' => 'Matemática I', 'credits' => 4, 'department_id' => $dcnm]));
        $db::table('career_subjects')->insert(['study_plan_id' => $plan, 'subject_id' => $prn, 'cycle' => 1]);
        $aula = $db::table('classrooms')->insertGetId($t(['code' => 'A-101', 'capacity' => 45]));

        $ulid = (string) str()->ulid();
        $seccion = $db::table('sections')->insertGetId($t(['ulid' => $ulid, 'period_id' => $periodo, 'subject_id' => $prn, 'code' => '01', 'max_capacity' => 40]));
        $otraUlid = (string) str()->ulid();
        $otra = $db::table('sections')->insertGetId($t(['ulid' => $otraUlid, 'period_id' => $periodo, 'subject_id' => $mat, 'code' => '01', 'max_capacity' => 40]));
        $db::table('section_schedules')->insert($t(['section_id' => $seccion, 'period_id' => $periodo, 'classroom_id' => $aula, 'weekday' => 1, 'start_time' => '07:00', 'end_time' => '08:40']));
        $db::table('teaching_assignments')->insert($t(['section_id' => $seccion, 'teacher_id' => $docente->id, 'is_responsible' => true, 'started_at' => '2026-07-01']));
        $db::table('teacher_profiles')->insert($t(['user_id' => $docente->id, 'department_id' => $dia]));
        $db::table('career_coordinators')->insert($t(['user_id' => $coordinador->id, 'career_id' => $isi, 'valid_from' => '2025-01-01']));
        $db::table('department_heads')->insert($t(['user_id' => $jefe->id, 'department_id' => $dia, 'valid_from' => '2025-01-01']));
        $db::table('student_profiles')->insert($t(['user_id' => $estudiante->id, 'student_code' => 'GS21004']));
        $db::table('student_careers')->insert($t(['student_id' => $estudiante->id, 'career_id' => $isi, 'study_plan_id' => $plan, 'current_cycle' => 1, 'status' => 'active']));
        $matricula = $db::table('enrollments')->insertGetId($t(['student_id' => $estudiante->id, 'section_id' => $seccion, 'period_id' => $periodo, 'subject_id' => $prn, 'status' => 'active', 'enrolled_via' => 'key', 'enrolled_by' => $estudiante->id]));
        // RF29 (ADR-051): el plazo por defecto (inicio + 2 semanas) ya venció; se fija una fecha lejana para que las pruebas no caduquen.
        foreach ([$dia, $dcnm] as $departamento) {
            $db::table('enrollment_deadlines')->insert($t(['period_id' => $periodo, 'department_id' => $departamento, 'deadline' => '2099-12-31', 'set_by' => $administrador->id]));
        }

        return compact('docente', 'coordinador', 'jefe', 'estudiante', 'administrador', 'soporte', 'dia', 'dcnm', 'isi', 'plan', 'periodo', 'prn', 'mat', 'aula', 'seccion', 'ulid', 'otra', 'otraUlid', 'matricula');
    });
}

/**
 * Contenidos (RF11–RF14): el servicio de contenidos. Compartido porque lo usan varios archivos de
 * pruebas y, con `pest --parallel`, cada archivo corre en un proceso aparte.
 */
function contenidos(): ContenidoService
{
    return app(ContenidoService::class);
}

/** Una unidad vacía en la sección indicada. */
function unidadDeContenidos(int $seccionId, string $titulo = 'Unidad 1 · Fundamentos', int $posicion = 1): int
{
    return DbContext::system(fn () => DB::table('units')->insertGetId([
        'section_id' => $seccionId, 'title' => $titulo, 'position' => $posicion, 'created_at' => now(), 'updated_at' => now(),
    ]));
}

/**
 * Un recurso de la unidad, con los campos que se indiquen.
 *
 * @param  array<string, mixed>  $datos
 * @return array{id: int, ulid: string}
 */
function recursoEnUnidad(int $unidadId, array $datos = []): array
{
    return DbContext::system(function () use ($unidadId, $datos) {
        $ulid = (string) str()->ulid();
        $id = DB::table('resources')->insertGetId($datos + [
            'ulid' => $ulid, 'unit_id' => $unidadId, 'resource_type' => 'page', 'title' => 'Lectura guiada',
            'completion_mode' => 'manual', 'position' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['id' => $id, 'ulid' => $ulid];
    });
}

/**
 * Un docente responsable de la otra sección del escenario: tiene el permiso, pero no el alcance.
 *
 * @param  array<string, mixed>  $e
 */
function docenteAjeno(array $e): User
{
    $docente = usuarioCon('docente');
    DbContext::system(function () use ($docente, $e): void {
        DB::table('teacher_profiles')->insert(['user_id' => $docente->id, 'department_id' => $e['dcnm'], 'created_at' => now(), 'updated_at' => now()]);
        DB::table('teaching_assignments')->insert(['section_id' => $e['otra'], 'teacher_id' => $docente->id, 'is_responsible' => true, 'started_at' => '2026-07-01', 'created_at' => now(), 'updated_at' => now()]);
    });

    return $docente;
}

/**
 * Pruebas de navegador: visita la URL. Con VELOCIDAD_NAVEGADOR (segundos; la fija `--speed` de
 * scripts/navegador.php) espera ese tiempo antes de cada paso para verlo en pantalla.
 */
function navegar(string $url): mixed
{
    $pagina = visit($url);
    $segundos = (float) getenv('VELOCIDAD_NAVEGADOR');

    return $segundos > 0 ? new PaginaLenta($pagina, $segundos) : $pagina;
}

/**
 * Pruebas de navegador: acepta los wire:confirm de la página actual. Playwright rechaza los diálogos nativos por
 * defecto; se vuelve a llamar después de cada navegación completa.
 */
function aceptarConfirmaciones(mixed $pagina): mixed
{
    $pagina->script('window.confirm = () => true');

    return $pagina;
}

/**
 * ¿Es imposible verificar la RLS con la conexión actual?
 *
 * La RLS solo existe en PostgreSQL (ADR-033), y además PostgreSQL deja que un rol
 * `SUPERUSER` o con `BYPASSRLS` se salte las políticas incluso con `FORCE ROW LEVEL
 * SECURITY`. El contenedor de desarrollo y la CI se conectan con `lms`, que es
 * superusuario, así que la segunda barrera de ADR-029 no se puede comprobar con él:
 * hace falta el rol `app_rw` de `docs/06 Arquitectura/Seguridad.md:40` y una conexión
 * restringida. Mientras no exista, la prueba se salta diciendo por qué, en vez de
 * fallar o de dar por verificada una política que no se ejerce.
 */
function rlsNoVerificable(): bool
{
    if (DB::connection()->getDriverName() !== 'pgsql') {
        return true;
    }

    $rol = DB::selectOne('select rolsuper, rolbypassrls from pg_roles where rolname = current_user');

    return (bool) ($rol->rolsuper || $rol->rolbypassrls);
}

/**
 * Una vista del registro sin componente, para probar la ficha «En construcción» ahora que todas las del
 * registro tienen pantalla real.
 */
function registrarVistaPendiente(): string
{
    config(['vistas.vista-pendiente' => ['uri' => '/vista-pendiente', 'permiso' => 'videoconferencias.gestionar', 'titulo' => 'Vista pendiente',
        'modulo' => 'Asistencia', 'rf' => ['RF21'], 'op' => 455, 'migas' => ['perfil'], 'maqueta' => '52 Mi perfil · Google Calendar']]);
    Vistas::registrarPendientes();

    return 'vista-pendiente';
}
