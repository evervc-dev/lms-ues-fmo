<?php

use App\Models\User;
use App\Modules\Academica\Services\CargaAcademicaService;
use App\Modules\Identidad\Livewire\CrearUsuario;
use App\Modules\Identidad\Livewire\RolesUsuario;
use App\Modules\Identidad\Livewire\UsuarioDetalle;
use App\Modules\Identidad\Services\AsignacionService;
use App\Modules\Identidad\Services\RolService;
use App\Modules\Identidad\Services\UsuarioService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Edición de usuarios y roles atados a su asignación (RF04, ADR-036).
 */

/**
 * Claves de error de la ValidationException que lanza la acción; vacío si no la lanza.
 *
 * @return array<int, string>
 */
function clavesDeErrorDeAsignacion(callable $accion): array
{
    try {
        $accion();
    } catch (ValidationException $excepcion) {
        return array_keys($excepcion->errors());
    }

    return [];
}

/**
 * ¿La acción se rechazó por permiso (403) o por regla de negocio (422)?
 */
function accionRechazadaDeAsignacion(callable $accion): bool
{
    try {
        $accion();
    } catch (AuthorizationException|ValidationException) {
        return true;
    }

    return false;
}

/**
 * Los campos `date` vuelven como 'Y-m-d' en PostgreSQL y pueden traer hora en SQLite.
 */
function soloFechaDeAsignacion(mixed $valor): ?string
{
    return $valor === null ? null : substr((string) $valor, 0, 10);
}

/**
 * Carrera con su plan fuera de DIA (LMA · DCNM), para cambios de carrera y coordinaciones sin relevo.
 *
 * @return array{carrera: int, plan: int}
 */
function carreraAdicionalDeAsignacion(array $e): array
{
    return DbContext::system(function () use ($e) {
        $carrera = DB::table('careers')->insertGetId(['department_id' => $e['dcnm'], 'code' => 'LMA', 'name' => 'Licenciatura en Matemática', 'created_at' => now(), 'updated_at' => now()]);
        $plan = DB::table('study_plans')->insertGetId(['career_id' => $carrera, 'name' => 'Plan 20', 'created_at' => now(), 'updated_at' => now()]);

        return ['carrera' => $carrera, 'plan' => $plan];
    });
}

it('CP-RF04-07 guarda los nombres y apellidos que edita el Administrador sin cambiar el correo', function () {
    $e = escenario();
    $correo = $e['docente']->institutional_email;

    actingAs($e['administrador'])->get(route('usuarios.show', $e['docente']->id))->assertOk()->assertSee('guardarDatos', false);
    Livewire::test(UsuarioDetalle::class, ['usuario' => $e['docente']->id])
        ->set('nombres', 'María José')->set('apellidos', 'Hernández Ruiz')
        ->call('guardarDatos')
        ->assertHasNoErrors();
    app(UsuarioService::class)->actualizarDatos($e['administrador'], $e['docente']->id, ['nombres' => 'María José', 'apellidos' => 'Hernández Ruiz', 'correo' => 'otro.correo@ues.edu.sv']);

    $docente = $e['docente']->fresh();
    expect($docente->first_name)->toBe('María José')
        ->and($docente->last_name)->toBe('Hernández Ruiz')
        ->and($docente->institutional_email)->toBe($correo);

    if (DB::connection()->getDriverName() === 'pgsql') {
        DbContext::auditScope(all: true);
        expect(DB::table('audit_logs')->where('entity_type', 'users')->where('entity_id', $docente->id)
            ->where('action', 'update')->where('actor_user_id', $e['administrador']->id)->exists())->toBeTrue();
    }
});

it('CP-RF04-08 conserva todos los roles que el Administrador asigna a un usuario', function () {
    $e = escenario();
    $otra = carreraAdicionalDeAsignacion($e);
    actingAs($e['administrador']);

    Livewire::test(RolesUsuario::class, ['usuario' => $e['docente']->id])
        ->set('roles', ['docente', 'coordinador'])->set('carreraCoordina', $otra['carrera'])
        ->call('guardar')
        ->assertHasNoErrors();

    expect($e['docente']->fresh()->getRoleNames()->sort()->values()->all())->toBe(['coordinador', 'docente']);
});

it('CP-RF04-08 rechaza dejar sin roles y editar los roles de la cuenta de emergencia', function () {
    $e = escenario();
    $emergencia = DbContext::system(fn () => User::factory()->create(['is_emergency' => true])->assignRole('administrador'));
    $usuarios = app(UsuarioService::class);
    actingAs($e['administrador']);

    expect(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $e['docente']->id, [])))->toContain('roles')
        ->and(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $emergencia->id, ['administrador', 'soporte_tecnico'])))->toContain('roles')
        ->and($e['docente']->fresh()->getRoleNames()->all())->toBe(['docente'])
        ->and($emergencia->fresh()->getRoleNames()->all())->toBe(['administrador']);
});

it('CP-RF04-08 rechaza que un usuario edite sus propios roles, para quitarse o para añadirse Administrador', function () {
    $e = escenario();
    usuarioCon('administrador');
    $usuarios = app(UsuarioService::class);
    actingAs($e['administrador']);

    expect(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $e['administrador']->id, ['soporte_tecnico'])))->toContain('roles')
        ->and(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $e['administrador']->id, ['administrador', 'soporte_tecnico'])))->toContain('roles')
        ->and($e['administrador']->fresh()->getRoleNames()->all())->toBe(['administrador']);

    app(RolService::class)->alternar($e['administrador'], 'soporte_tecnico', 'usuarios.gestionar');
    $soporte = $e['soporte']->fresh();
    actingAs($soporte);

    expect(accionRechazadaDeAsignacion(fn () => $usuarios->cambiarRoles($soporte, $soporte->id, ['soporte_tecnico', 'administrador'])))->toBeTrue()
        ->and($soporte->fresh()->getRoleNames()->all())->toBe(['soporte_tecnico']);
});

it('CP-RF04-08 permite quitar el rol Administrador a otro mientras quede un Administrador activo', function () {
    $e = escenario();
    $otro = usuarioCon('administrador');
    actingAs($e['administrador']);

    app(UsuarioService::class)->cambiarRoles($e['administrador'], $otro->id, ['soporte_tecnico']);

    expect($otro->fresh()->getRoleNames()->all())->toBe(['soporte_tecnico']);
});

it('CP-RF04-08 CP-RF03-03 no quita el rol Administrador al último Administrador activo', function () {
    // Sin auto-edición y con alcance G, el actor válido siempre es otro Administrador activo, así que
    // la regla solo se alcanza forzando un actor inactivo (defensa en profundidad del Service).
    $actor = usuarioCon('administrador');
    $ultimo = usuarioCon('administrador');
    DbContext::system(fn () => $actor->update(['is_active' => false]));
    actingAs($actor);

    expect(clavesDeErrorDeAsignacion(fn () => app(UsuarioService::class)->cambiarRoles($actor->fresh(), $ultimo->id, ['soporte_tecnico'])))->toContain('roles')
        ->and($ultimo->fresh()->hasRole('administrador'))->toBeTrue();
});

it('CP-RF04-09 exige carrera y el rol al Coordinador, releva al vigente desde hoy y cierra la coordinación al quitar el rol', function () {
    $this->travelTo(now()->setTime(18, 0));
    $e = escenario();
    DbContext::system(fn () => $e['coordinador']->assignRole('docente'));
    $usuarios = app(UsuarioService::class);
    $hoy = now()->toDateString();
    actingAs($e['administrador']);

    expect(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $e['docente']->id, ['docente', 'coordinador'])))->toContain('asignaciones.coordinador')
        ->and(clavesDeErrorDeAsignacion(fn () => app(AsignacionService::class)->asignarCoordinacion($e['administrador'], $e['docente']->id, $e['isi'])))->toContain('roles')
        ->and($e['docente']->fresh()->hasRole('coordinador'))->toBeFalse()
        ->and(DB::table('career_coordinators')->where('user_id', $e['docente']->id)->exists())->toBeFalse();

    $usuarios->cambiarRoles($e['administrador'], $e['docente']->id, ['docente', 'coordinador'], ['coordinador' => ['carrera' => $e['isi']]]);

    $anterior = DB::table('career_coordinators')->where('user_id', $e['coordinador']->id)->where('career_id', $e['isi'])->first();
    $nuevo = DB::table('career_coordinators')->where('user_id', $e['docente']->id)->where('career_id', $e['isi'])->first();
    expect(soloFechaDeAsignacion($anterior->valid_to))->toBe($hoy)
        ->and(soloFechaDeAsignacion($nuevo->valid_from))->toBe($hoy)
        ->and($nuevo->valid_to)->toBeNull()
        ->and($e['coordinador']->fresh()->getRoleNames()->all())->toBe(['docente']);

    $usuarios->cambiarRoles($e['administrador'], $e['docente']->id, ['docente']);

    expect(soloFechaDeAsignacion(DB::table('career_coordinators')->where('user_id', $e['docente']->id)->value('valid_to')))->toBe($hoy)
        ->and(DB::table('career_coordinators')->where('career_id', $e['isi'])->whereNull('valid_to')->exists())->toBeFalse();
});

it('CP-RF04-09 no releva al propio actor ni a la cuenta de emergencia', function () {
    $e = escenario();
    $lma = carreraAdicionalDeAsignacion($e);
    $otroAdministrador = usuarioCon('administrador');
    $emergencia = DbContext::system(fn () => User::factory()->create(['is_emergency' => true])->assignRole(['administrador', 'coordinador']));
    DbContext::system(fn () => DB::table('career_coordinators')->insert(['user_id' => $emergencia->id, 'career_id' => $lma['carrera'], 'valid_from' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]));
    $usuarios = app(UsuarioService::class);
    actingAs($otroAdministrador);
    $usuarios->cambiarRoles($otroAdministrador, $e['administrador']->id, ['administrador', 'coordinador'], ['coordinador' => ['carrera' => $e['isi']]]);
    actingAs($e['administrador']);

    expect(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $e['docente']->id, ['docente', 'coordinador'], ['coordinador' => ['carrera' => $e['isi']]])))->toContain('asignaciones.coordinador.carrera')
        ->and(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $e['docente']->id, ['docente', 'coordinador'], ['coordinador' => ['carrera' => $lma['carrera']]])))->toContain('asignaciones.coordinador.carrera')
        ->and($e['administrador']->fresh()->hasRole('coordinador'))->toBeTrue()
        ->and($emergencia->fresh()->hasRole('coordinador'))->toBeTrue()
        ->and($e['docente']->fresh()->hasRole('coordinador'))->toBeFalse();
});

it('CP-RF04-09 deja entrar al relevado que se queda sin roles, con un inicio vacío', function () {
    $e = escenario();
    $usuarios = app(UsuarioService::class);
    actingAs($e['administrador']);

    $usuarios->cambiarRoles($e['administrador'], $e['docente']->id, ['docente', 'coordinador'], ['coordinador' => ['carrera' => $e['isi']]]);

    expect($e['coordinador']->fresh()->getRoleNames()->all())->toBe([]);
    actingAs($e['coordinador']->fresh())->get(route('inicio'))->assertOk();
});

it('CP-RF04-10 exige departamento al Jefe, releva al vigente desde hoy y cierra la jefatura al quitar el rol', function () {
    $this->travelTo(now()->setTime(18, 0));
    $e = escenario();
    DbContext::system(fn () => $e['jefe']->assignRole('docente'));
    $usuarios = app(UsuarioService::class);
    $hoy = now()->toDateString();
    actingAs($e['administrador']);

    expect(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $e['docente']->id, ['docente', 'jefe_departamento'])))->toContain('asignaciones.jefe_departamento')
        ->and($e['docente']->fresh()->hasRole('jefe_departamento'))->toBeFalse();

    $usuarios->cambiarRoles($e['administrador'], $e['docente']->id, ['docente', 'jefe_departamento'], ['jefe_departamento' => ['departamento' => $e['dia']]]);

    $anterior = DB::table('department_heads')->where('user_id', $e['jefe']->id)->where('department_id', $e['dia'])->first();
    $nuevo = DB::table('department_heads')->where('user_id', $e['docente']->id)->where('department_id', $e['dia'])->first();
    expect(soloFechaDeAsignacion($anterior->valid_to))->toBe($hoy)
        ->and(soloFechaDeAsignacion($nuevo->valid_from))->toBe($hoy)
        ->and($nuevo->valid_to)->toBeNull()
        ->and($e['jefe']->fresh()->getRoleNames()->all())->toBe(['docente']);

    $usuarios->cambiarRoles($e['administrador'], $e['docente']->id, ['docente']);

    expect(soloFechaDeAsignacion(DB::table('department_heads')->where('user_id', $e['docente']->id)->value('valid_to')))->toBe($hoy)
        ->and(DB::table('department_heads')->where('department_id', $e['dia'])->whereNull('valid_to')->exists())->toBeFalse();
});

it('CP-RF04-11 exige departamento al Docente y lo ofrece en Asignar docente solo mientras tiene el rol y está activo', function () {
    $e = escenario();
    $nuevo = usuarioCon('soporte_tecnico');
    $inactivo = usuarioCon('docente');
    DbContext::system(function () use ($inactivo, $e) {
        DB::table('teacher_profiles')->insert(['user_id' => $inactivo->id, 'department_id' => $e['dia'], 'created_at' => now(), 'updated_at' => now()]);
        $inactivo->update(['is_active' => false]);
    });
    $usuarios = app(UsuarioService::class);
    $carga = app(CargaAcademicaService::class);
    actingAs($e['administrador']);

    expect(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $nuevo->id, ['soporte_tecnico', 'docente'])))->toContain('asignaciones.docente')
        ->and(clavesDeErrorDeAsignacion(fn () => $usuarios->crear($e['administrador'], ['correo' => 'dn26001@ues.edu.sv', 'nombres' => 'Diana', 'apellidos' => 'Núñez', 'rol' => 'docente'])))->toContain('asignacion')
        ->and(User::query()->where('institutional_email', 'dn26001@ues.edu.sv')->exists())->toBeFalse();

    $usuarios->cambiarRoles($e['administrador'], $nuevo->id, ['soporte_tecnico', 'docente'], ['docente' => ['departamento' => $e['dia']]]);

    expect(DB::table('teacher_profiles')->where('user_id', $nuevo->id)->value('department_id'))->toBe($e['dia'])
        ->and($carga->docentesDisponibles($e['periodo'])->pluck('id')->all())->toContain($nuevo->id);

    $usuarios->cambiarRoles($e['administrador'], $nuevo->id, ['soporte_tecnico']);

    expect($carga->docentesDisponibles($e['periodo'])->pluck('id')->all())->not->toContain($nuevo->id)
        ->and(DB::table('teacher_profiles')->where('user_id', $nuevo->id)->exists())->toBeTrue()
        ->and(clavesDeErrorDeAsignacion(fn () => $carga->asignarDocente($e['administrador'], $e['otra'], $nuevo->id)))->toContain('docente')
        ->and(clavesDeErrorDeAsignacion(fn () => $carga->asignarDocente($e['administrador'], $e['otra'], $inactivo->id)))->toContain('docente')
        ->and(DB::table('teaching_assignments')->where('section_id', $e['otra'])->exists())->toBeFalse();
});

it('CP-RF04-11 no quita el rol Docente a quien tiene secciones abiertas en un período no cerrado', function () {
    $e = escenario();
    actingAs($e['administrador']);

    expect(clavesDeErrorDeAsignacion(fn () => app(UsuarioService::class)->cambiarRoles($e['administrador'], $e['docente']->id, ['soporte_tecnico'])))->toContain('roles')
        ->and($e['docente']->fresh()->getRoleNames()->all())->toBe(['docente']);
});

it('CP-RF04-12 exige carrera, plan de esa carrera, ciclo de 1 a 12 y carné único al conceder Estudiante', function () {
    $e = escenario();
    $otra = carreraAdicionalDeAsignacion($e);
    // Una cuenta sin rol (ADR-038): Soporte Técnico y Estudiante ya no coexisten (ADR-053).
    $nuevo = DbContext::system(fn () => User::factory()->create());
    $usuarios = app(UsuarioService::class);
    $roles = ['estudiante'];
    $datos = ['carrera' => $e['isi'], 'plan' => $e['plan'], 'ciclo' => 12];
    actingAs($e['administrador']);

    expect(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $nuevo->id, $roles)))->toContain('asignaciones.estudiante')
        ->and(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $nuevo->id, $roles, ['estudiante' => ['carrera' => $e['isi']]])))->not->toBeEmpty()
        ->and(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $nuevo->id, $roles, ['estudiante' => ['plan' => $otra['plan']] + $datos])))->toContain('asignaciones.estudiante.plan')
        ->and(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $nuevo->id, $roles, ['estudiante' => ['ciclo' => 13] + $datos])))->toContain('asignaciones.estudiante.ciclo')
        ->and(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $nuevo->id, $roles, ['estudiante' => ['carne' => 'GS21004'] + $datos])))->toContain('asignaciones.estudiante.carne')
        ->and(clavesDeErrorDeAsignacion(fn () => $usuarios->crear($e['administrador'], ['correo' => 'mc26002@ues.edu.sv', 'nombres' => 'Marta', 'apellidos' => 'Cruz', 'rol' => 'estudiante', 'asignacion' => ['carne' => 'GS21004'] + $datos])))->toContain('asignacion.carne')
        ->and($nuevo->fresh()->hasRole('estudiante'))->toBeFalse()
        ->and(DB::table('student_careers')->where('student_id', $nuevo->id)->exists())->toBeFalse();

    $usuarios->cambiarRoles($e['administrador'], $nuevo->id, $roles, ['estudiante' => $datos]);

    $carrera = DB::table('student_careers')->where('student_id', $nuevo->id)->where('status', 'active')->first();
    expect($nuevo->fresh()->hasRole('estudiante'))->toBeTrue()
        ->and(DB::table('student_profiles')->where('user_id', $nuevo->id)->value('student_code'))->toBe(AsignacionService::carneDeCorreo($nuevo->institutional_email))
        ->and([(int) $carrera->career_id, (int) $carrera->study_plan_id, (int) $carrera->current_cycle])->toBe([$e['isi'], $e['plan'], 12]);
});

it('CP-RF04-12 avisa a quién reemplaza antes de dar una jefatura o coordinación que ya tiene titular', function () {
    $e = escenario();
    $nuevo = DbContext::system(fn () => User::factory()->create(['institutional_email' => 'jr26001@ues.edu.sv']));
    actingAs($e['administrador']);

    $roles = Livewire::test(RolesUsuario::class, ['usuario' => $nuevo->id])->set('roles', ['jefe_departamento', 'coordinador'])->call('siguiente')
        ->set('departamentoJefatura', $e['dia'])
        ->assertSee(__('Se reemplazará a :nombre', ['nombre' => $e['jefe']->fullName()]))->assertSee($e['jefe']->institutional_email)
        ->set('carreraCoordina', $e['isi'])
        ->assertSee(__('Se reemplazará a :nombre', ['nombre' => $e['coordinador']->fullName()]));

    expect(app(AsignacionService::class)->titularARelevar('jefe_departamento', $e['dia'], $e['jefe']->id))->toBeNull()
        ->and(app(AsignacionService::class)->titularARelevar('docente', $e['dia'], $nuevo->id))->toBeNull();

    $roles->call('siguiente')->assertSet('paso', 3)->assertSee(__('Se reemplazará a :nombre', ['nombre' => $e['jefe']->fullName()]))
        ->call('guardar')->assertHasNoErrors();

    expect($e['jefe']->fresh()->hasRole('jefe_departamento'))->toBeFalse()
        ->and(DB::table('department_heads')->where('department_id', $e['dia'])->whereNull('valid_to')->value('user_id'))->toBe($nuevo->id);

    Livewire::test(CrearUsuario::class)->set('correo', 'mr26004')->set('nombres', 'Marta')->set('apellidos', 'Ríos')->call('siguiente')
        ->set('rolNuevo', 'coordinador')->set('carreraCoordina', $e['isi'])
        ->assertSee(__('Se reemplazará a :nombre', ['nombre' => $nuevo->fresh()->fullName()]));
});

it('CP-RF04-12 toma el carné del correo institucional y solo pide escribirlo si el correo no da uno', function () {
    $e = escenario();
    $usuarios = app(UsuarioService::class);
    $datos = ['carrera' => $e['isi'], 'plan' => $e['plan'], 'ciclo' => 1];
    $conCarne = DbContext::system(fn () => User::factory()->create(['institutional_email' => 'LL22017@ues.edu.sv']));
    $largo = DbContext::system(fn () => User::factory()->create(['institutional_email' => 'nombre.apellido.muy.largo@ues.edu.sv']));
    actingAs($e['administrador']);

    expect(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $conCarne->id, ['estudiante'], ['estudiante' => ['carne' => 'XX99999'] + $datos])))->toContain('asignaciones.estudiante.carne')
        ->and(clavesDeErrorDeAsignacion(fn () => $usuarios->cambiarRoles($e['administrador'], $largo->id, ['estudiante'], ['estudiante' => $datos])))->toContain('asignaciones.estudiante.carne');

    $usuarios->cambiarRoles($e['administrador'], $conCarne->id, ['estudiante'], ['estudiante' => $datos]);
    $usuarios->cambiarRoles($e['administrador'], $largo->id, ['estudiante'], ['estudiante' => ['carne' => 'NA26001'] + $datos]);

    expect(DB::table('student_profiles')->where('user_id', $conCarne->id)->value('student_code'))->toBe('ll22017')
        ->and(DB::table('student_profiles')->where('user_id', $largo->id)->value('student_code'))->toBe('na26001')
        ->and(AsignacionService::carneDeCorreo('gs21004@ues.edu.sv'))->toBe('gs21004');

    Livewire::test(RolesUsuario::class, ['usuario' => DbContext::system(fn () => User::factory()->create(['institutional_email' => 'mc26003@ues.edu.sv']))->id])
        ->set('roles', ['estudiante'])->call('siguiente')->assertSet('paso', 2)->assertSee('value="mc26003"', false);
});

it('CP-RF04-12 no modifica el carné, cambia de carrera dejando la anterior changed y no quita el rol con matrículas activas', function () {
    $e = escenario();
    $otra = carreraAdicionalDeAsignacion($e);
    $asignaciones = app(AsignacionService::class);
    actingAs($e['administrador']);

    expect(clavesDeErrorDeAsignacion(fn () => $asignaciones->perfilEstudiante($e['administrador'], $e['estudiante']->id, ['carrera' => $e['isi'], 'plan' => $e['plan'], 'ciclo' => 2, 'carne' => 'XX99999'])))->toContain('carne')
        ->and(DB::table('student_profiles')->where('user_id', $e['estudiante']->id)->value('student_code'))->toBe('GS21004')
        ->and(clavesDeErrorDeAsignacion(fn () => app(UsuarioService::class)->cambiarRoles($e['administrador'], $e['estudiante']->id, ['soporte_tecnico'])))->toContain('roles')
        ->and($e['estudiante']->fresh()->getRoleNames()->all())->toBe(['estudiante']);

    $asignaciones->perfilEstudiante($e['administrador'], $e['estudiante']->id, ['carrera' => $otra['carrera'], 'plan' => $otra['plan'], 'ciclo' => 3, 'carne' => 'GS21004']);

    expect(DB::table('student_careers')->where('student_id', $e['estudiante']->id)->orderBy('id')->pluck('status', 'career_id')->all())
        ->toBe([$e['isi'] => 'changed', $otra['carrera'] => 'active'])
        ->and(DB::table('student_profiles')->where('user_id', $e['estudiante']->id)->value('student_code'))->toBe('GS21004');
});

it('CP-RF04-13 prohíbe editar datos, roles y asignaciones de un usuario', function (string $quien) {
    $e = escenario();
    $actor = $e[$quien];
    $usuarios = app(UsuarioService::class);
    $asignaciones = app(AsignacionService::class);
    actingAs($actor);

    expect(fn () => $usuarios->actualizarDatos($actor, $e['docente']->id, ['nombres' => 'Intruso', 'apellidos' => 'Intruso']))->toThrow(AuthorizationException::class)
        ->and(fn () => $usuarios->cambiarRoles($actor, $e['docente']->id, ['docente', 'coordinador'], ['coordinador' => ['carrera' => $e['isi']]]))->toThrow(AuthorizationException::class)
        ->and(fn () => $asignaciones->aplicar($actor, $e['docente']->id, 'coordinador', ['carrera' => $e['isi']]))->toThrow(AuthorizationException::class)
        ->and($e['docente']->fresh()->first_name)->not->toBe('Intruso')
        ->and($e['docente']->fresh()->getRoleNames()->all())->toBe(['docente'])
        ->and(DB::table('career_coordinators')->where('career_id', $e['isi'])->whereNull('valid_to')->value('user_id'))->toBe($e['coordinador']->id);
})->with(['coordinador', 'soporte']);

it('CP-RF04-13 no deja a Soporte tocar cuentas ni roles fuera de los que asigna', function () {
    $e = escenario();
    $soporte = $e['soporte'];
    $usuarios = app(UsuarioService::class);
    actingAs($soporte);

    expect(fn () => $usuarios->actualizarDatos($soporte, $e['docente']->id, ['nombres' => 'Intruso', 'apellidos' => 'Intruso']))->toThrow(AuthorizationException::class)
        ->and(fn () => $usuarios->cambiarRoles($soporte, $e['docente']->id, ['docente', 'soporte_tecnico']))->toThrow(AuthorizationException::class)
        ->and(fn () => $usuarios->desactivar($soporte, $e['docente']->id, 'Prueba de alcance'))->toThrow(AuthorizationException::class)
        ->and(fn () => $usuarios->crear($soporte, ['correo' => 'in26001@ues.edu.sv', 'nombres' => 'Intruso', 'apellidos' => 'Intruso', 'rol' => 'soporte_tecnico']))->toThrow(AuthorizationException::class)
        ->and($e['docente']->fresh()->only(['first_name', 'is_active']))->toBe(['first_name' => $e['docente']->first_name, 'is_active' => true])
        ->and($e['docente']->fresh()->getRoleNames()->all())->toBe(['docente'])
        ->and(User::query()->where('institutional_email', 'in26001@ues.edu.sv')->exists())->toBeFalse();
});

it('CP-RF04-13 deja a Soporte consultar la ficha sin editarla y la niega al Coordinador', function () {
    $e = escenario();

    actingAs($e['soporte'])->get(route('usuarios.show', $e['docente']->id))
        ->assertOk()
        ->assertSeeText($e['docente']->institutional_email)
        ->assertDontSee('guardarDatos', false)
        ->assertDontSee(route('usuarios.roles', ['usuario' => $e['docente']->id]), false);
    actingAs($e['soporte'])->get(route('usuarios.roles', ['usuario' => $e['docente']->id]))->assertForbidden();
    Livewire::test(UsuarioDetalle::class, ['usuario' => $e['docente']->id])
        ->set('nombres', 'Intruso')
        ->call('guardarDatos')
        ->assertForbidden();
    actingAs($e['coordinador'])->get(route('usuarios.show', $e['docente']->id))->assertForbidden();

    expect($e['docente']->fresh()->first_name)->not->toBe('Intruso');
});

it('CP-RF04-14 registra en audit_logs los cambios de perfiles, carreras, coordinaciones y jefaturas', function () {
    $e = escenario();
    $otra = carreraAdicionalDeAsignacion($e);
    $docente = usuarioCon('soporte_tecnico');
    $estudiante = usuarioCon('soporte_tecnico');
    $usuarios = app(UsuarioService::class);
    actingAs($e['administrador']);

    $usuarios->cambiarRoles($e['administrador'], $docente->id, ['docente', 'coordinador', 'jefe_departamento'], [
        'docente' => ['departamento' => $e['dia']],
        'coordinador' => ['carrera' => $e['isi']],
        'jefe_departamento' => ['departamento' => $e['dia']],
    ]);
    $usuarios->cambiarRoles($e['administrador'], $estudiante->id, ['estudiante'], ['estudiante' => ['carrera' => $e['isi'], 'plan' => $e['plan'], 'ciclo' => 1]]);
    app(AsignacionService::class)->perfilEstudiante($e['administrador'], $e['estudiante']->id, ['carrera' => $otra['carrera'], 'plan' => $otra['plan'], 'ciclo' => 3, 'carne' => 'GS21004']);

    DbContext::auditScope(all: true);
    $auditoria = DB::table('audit_logs')->where('actor_user_id', $e['administrador']->id)->get(['entity_type', 'action']);
    $acciones = fn (string $tabla) => $auditoria->where('entity_type', $tabla)->pluck('action')->unique()->values()->all();

    expect($acciones('teacher_profiles'))->toContain('insert')
        ->and($acciones('student_profiles'))->toContain('insert')
        ->and($acciones('student_careers'))->toContain('insert', 'update')
        ->and($acciones('career_coordinators'))->toContain('insert', 'update')
        ->and($acciones('department_heads'))->toContain('insert', 'update');
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'Requiere PostgreSQL');
