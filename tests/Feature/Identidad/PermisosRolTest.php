<?php

use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Identidad\Services\RolService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Roles y permisos (RF03): el cambio de un permiso aplica en la siguiente petición y se audita (CP-RF03-01),
| el alcance por sección decide quién edita una asignatura (CP-RF03-02), el rol Administrador no pierde
| `roles.gestionar` (CP-RF03-03), no se crean permisos nuevos desde la pantalla (CP-RF03-07) y solo el rol
| con permiso de corrección corrige notas o asistencia, que quedan en la auditoría con su valor anterior y
| el nuevo (CP-RF03-04 y 05). La otra mitad de CP-RF03-03, «ni el rol», está en AsignacionesUsuarioTest.
| Lo que depende de los triggers de auditoría se prueba solo en PostgreSQL (ADR-033).
*/

/** Una nota publicada de 5 en un período ya cerrado: la que corrige el Coordinador (RF17). */
function permisosNotaEnPeriodoCerrado(array $e): int
{
    return DbContext::system(function () use ($e) {
        $componente = DB::table('grading_components')->insertGetId(['section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now()]);
        $item = DB::table('grade_items')->insertGetId(['component_id' => $componente, 'section_id' => $e['seccion'], 'item_type' => 'manual', 'title' => 'Parcial', 'weight_percent' => 100, 'is_published' => true, 'created_at' => now(), 'updated_at' => now()]);
        $nota = DB::table('grades')->insertGetId(['grade_item_id' => $item, 'enrollment_id' => $e['matricula'], 'section_id' => $e['seccion'], 'score' => 5, 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => 'closed']);

        return $nota;
    });
}

/**
 * Sesión de asistencia cerrada: el estudiante queda ausente.
 *
 * @return array{sesion: string, registro: int}
 */
function permisosAsistenciaCerrada(array $e): array
{
    actingAs($e['docente']);
    $asistencia = app(AsistenciaService::class);
    $sesion = $asistencia->abrir($e['docente'], $e['seccion'], 10);
    $asistencia->cerrar($e['docente'], $e['seccion'], $sesion);

    return ['sesion' => $sesion, 'registro' => (int) DB::table('attendance_records')->where('enrollment_id', $e['matricula'])->value('id')];
}

function soloPostgres(): bool
{
    return DB::connection()->getDriverName() !== 'pgsql';
}

it('CP-RF03-01 el permiso que el Administrador quita o devuelve a un rol aplica en la siguiente petición', function () {
    $e = escenario();
    $roles = app(RolService::class);
    actingAs($e['administrador']);

    expect($roles->alternar($e['administrador'], 'docente', 'reportes.ver'))->toBeFalse();
    // fresh(): el usuario se vuelve a cargar, como en una petición nueva.
    actingAs($e['docente']->fresh())->get(route('reportes'))->assertForbidden();

    actingAs($e['administrador']);
    expect($roles->alternar($e['administrador'], 'docente', 'reportes.ver'))->toBeTrue();
    actingAs($e['docente']->fresh())->get(route('reportes'))->assertOk();
});

it('CP-RF03-01 solo quien gestiona roles cambia los permisos de un rol', function (string $rol) {
    $e = escenario();

    expect(fn () => app(RolService::class)->alternar($e[$rol], 'docente', 'reportes.ver'))->toThrow(AuthorizationException::class);
    // `roles.gestionar` es solo del Administrador, con alcance G (Matriz RBAC).
})->with(['coordinador', 'jefe', 'soporte', 'docente', 'estudiante']);

it('CP-RF03-01 solo el rol con roles.gestionar abre la pantalla de roles y permisos', function () {
    $e = escenario();

    actingAs($e['administrador'])->get(route('roles'))->assertOk()->assertSeeText('roles.gestionar');
    actingAs($e['soporte'])->get(route('roles'))->assertForbidden();
});

it('CP-RF03-01 el cambio de permiso queda en la auditoría con quien lo hizo', function () {
    $e = escenario();
    actingAs($e['administrador']);

    app(RolService::class)->alternar($e['administrador'], 'docente', 'reportes.ver');

    DbContext::auditScope(all: true);
    $evento = DB::table('audit_logs')->where('entity_type', 'role_has_permissions')->where('event_type', 'role.permission.changed')->sole();
    expect((int) $evento->actor_user_id)->toBe($e['administrador']->id)
        ->and($evento->action)->toBe('delete')
        ->and($evento->old_values)->not->toBeNull();
})->skip(fn () => soloPostgres(), 'La auditoría la escriben los triggers, solo en PostgreSQL (ADR-033).');

it('CP-RF03-02 el docente asignado edita el contenido de su asignatura y el no asignado recibe 403', function () {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    $crear = route('secciones.recursos.crear', ['seccion' => $e['ulid'], 'unidad' => $unidad]);

    actingAs($e['docente'])->get($crear)->assertOk();
    contenidos()->agregarRecurso($e['docente'], $unidad, ['tipo' => 'link', 'titulo' => 'Guía', 'url' => 'https://www.ues.edu.sv/']);

    $ajeno = docenteAjeno($e);
    actingAs($ajeno)->get($crear)->assertForbidden();
    expect(fn () => contenidos()->agregarRecurso($ajeno, $unidad, ['tipo' => 'link', 'titulo' => 'Intrusa', 'url' => 'https://www.ues.edu.sv/']))
        ->toThrow(AuthorizationException::class);

    expect(DB::table('resources')->where('unit_id', $unidad)->pluck('title')->all())->toBe(['Guía']);
});

it('CP-RF03-03 rechaza quitarle roles.gestionar al rol Administrador', function () {
    $e = escenario();
    actingAs($e['administrador']);

    expect(fn () => app(RolService::class)->alternar($e['administrador'], 'administrador', 'roles.gestionar'))
        ->toThrow(ValidationException::class, 'El rol Administrador no puede perder el control de roles y permisos.')
        ->and(Role::findByName('administrador', 'web')->hasPermissionTo('roles.gestionar'))->toBeTrue()
        ->and($e['administrador']->fresh()->can('roles.gestionar'))->toBeTrue();

    // El resto de los permisos del rol sí se pueden quitar y devolver.
    expect(app(RolService::class)->alternar($e['administrador'], 'administrador', 'reportes.ver'))->toBeFalse();
});

it('CP-RF03-07 no crea permisos nuevos desde la pantalla: solo alterna los del seeder', function () {
    $e = escenario();
    actingAs($e['administrador']);

    expect(fn () => app(RolService::class)->alternar($e['administrador'], 'docente', 'inventado.permiso'))
        ->toThrow(PermissionDoesNotExist::class)
        ->and(Permission::query()->where('name', 'inventado.permiso')->exists())->toBeFalse()
        ->and(Role::findByName('docente', 'web')->hasPermissionTo('reportes.ver'))->toBeTrue();
});

it('CP-RF03-04 rechaza corregir una nota a quien no tiene el permiso de corrección', function (string $quien) {
    $e = escenario();
    $nota = permisosNotaEnPeriodoCerrado($e);
    $actor = match ($quien) {
        'estudiante' => $e['estudiante'],
        'jefe' => $e['jefe'],
        'docente ajeno' => docenteAjeno($e),
    };

    expect(fn () => app(CalificacionService::class)->corregir($actor, $nota, 9, 'Sin permiso.'))->toThrow(AuthorizationException::class);

    expect((float) DB::table('grades')->where('id', $nota)->value('score'))->toEqual(5.0);
})->with(['estudiante', 'jefe', 'docente ajeno']);

it('CP-RF03-04 rechaza corregir la asistencia a quien no tiene el permiso de corrección', function (string $quien) {
    $e = escenario();
    ['sesion' => $sesion, 'registro' => $registro] = permisosAsistenciaCerrada($e);
    $actor = match ($quien) {
        'estudiante' => $e['estudiante'],
        'coordinador' => $e['coordinador'],
        'docente ajeno' => docenteAjeno($e),
    };

    expect(fn () => app(AsistenciaService::class)->marcar($actor, $e['seccion'], $sesion, $e['matricula'], 'present', 'Sin permiso.'))
        ->toThrow(AuthorizationException::class);

    expect(DB::table('attendance_records')->where('id', $registro)->value('status'))->toBe('absent');
})->with(['estudiante', 'coordinador', 'docente ajeno']);

it('CP-RF03-05 CP-RF17-06 la corrección de una nota queda en la auditoría con el valor anterior, el nuevo y el motivo', function () {
    $e = escenario();
    $nota = permisosNotaEnPeriodoCerrado($e);
    actingAs($e['coordinador']);

    app(CalificacionService::class)->corregir($e['coordinador'], $nota, 6.5, 'Revisión de examen.');

    DbContext::auditScope(all: true);
    $evento = DB::table('audit_logs')->where('entity_type', 'grades')->where('entity_id', $nota)->where('event_type', 'grade.corrected')->sole();
    expect((int) $evento->actor_user_id)->toBe($e['coordinador']->id)
        ->and($evento->reason)->toBe('Revisión de examen.')
        ->and((float) json_decode($evento->old_values, true)['score'])->toEqual(5.0)
        ->and((float) json_decode($evento->new_values, true)['score'])->toEqual(6.5);
})->skip(fn () => soloPostgres(), 'La auditoría la escriben los triggers, solo en PostgreSQL (ADR-033).');

it('CP-RF03-05 CP-RF19-04 la corrección de una asistencia queda en la auditoría con el valor anterior, el nuevo y el motivo', function () {
    $e = escenario();
    ['sesion' => $sesion, 'registro' => $registro] = permisosAsistenciaCerrada($e);

    app(AsistenciaService::class)->marcar($e['docente'], $e['seccion'], $sesion, $e['matricula'], 'present', 'Llegó con el docente.');

    DbContext::auditScope(all: true);
    $evento = DB::table('audit_logs')->where('entity_type', 'attendance_records')->where('entity_id', $registro)->where('event_type', 'attendance.corrected')->sole();
    expect((int) $evento->actor_user_id)->toBe($e['docente']->id)
        ->and($evento->reason)->toBe('Llegó con el docente.')
        ->and(json_decode($evento->old_values, true)['status'])->toBe('absent')
        ->and(json_decode($evento->new_values, true)['status'])->toBe('present');
})->skip(fn () => soloPostgres(), 'La auditoría la escriben los triggers, solo en PostgreSQL (ADR-033).');
