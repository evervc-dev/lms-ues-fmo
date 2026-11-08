<?php

use App\Models\User;
use App\Modules\Identidad\Livewire\ImportarUsuarios;
use App\Modules\Identidad\Livewire\RolesUsuario;
use App\Modules\Identidad\Livewire\UsuarioDetalle;
use App\Modules\Identidad\Services\AsignacionService;
use App\Modules\Identidad\Services\RolService;
use App\Modules\Identidad\Services\UsuarioService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Jerarquía de creación y asignación de roles (ADR-037): Soporte crea estudiantes (y docentes con permiso);
 * el Jefe da Docente y Coordinador en su departamento sin crear cuentas.
 */

/**
 * @param  array<string, mixed>  $asignacion
 * @return array{correo: string, nombres: string, apellidos: string, rol: string, asignacion: array<string, mixed>}
 */
function nuevaCuenta(string $correo, string $rol, array $asignacion = []): array
{
    return ['correo' => $correo, 'nombres' => 'Ana', 'apellidos' => 'Pérez', 'rol' => $rol, 'asignacion' => $asignacion];
}

it('CP-RF04-15 lets technical support create a student with their career', function () {
    $e = escenario();
    actingAs($e['soporte']);

    $usuario = app(UsuarioService::class)->crear($e['soporte'], nuevaCuenta('ap26001@ues.edu.sv', 'estudiante', ['carrera' => $e['isi'], 'plan' => $e['plan'], 'ciclo' => 1, 'carne' => 'AP26001']));

    expect($usuario->getRoleNames()->all())->toBe(['estudiante']);
    $this->assertDatabaseHas('student_careers', ['student_id' => $usuario->id, 'career_id' => $e['isi'], 'status' => 'active']);
});

it('CP-RF04-15 does not let technical support create other roles', function (string $rol, array $asignacion) {
    $e = escenario();
    actingAs($e['soporte']);
    $asignacion = array_map(fn ($valor) => is_string($valor) && isset($e[$valor]) ? $e[$valor] : $valor, $asignacion);

    expect(fn () => app(UsuarioService::class)->crear($e['soporte'], nuevaCuenta('in26001@ues.edu.sv', $rol, $asignacion)))
        ->toThrow(AuthorizationException::class);
    expect(User::query()->where('institutional_email', 'in26001@ues.edu.sv')->exists())->toBeFalse();
})->with([
    'administrador' => ['administrador', []],
    'jefe' => ['jefe_departamento', ['departamento' => 'dia']],
    'coordinador' => ['coordinador', ['carrera' => 'isi']],
    'soporte' => ['soporte_tecnico', []],
    'docente sin permiso' => ['docente', ['departamento' => 'dia']],
]);

it('CP-RF04-15 lets technical support correct a student but not other accounts, and never deactivate', function () {
    $e = escenario();
    $usuarios = app(UsuarioService::class);
    actingAs($e['soporte']);

    $usuarios->actualizarDatos($e['soporte'], $e['estudiante']->id, ['nombres' => 'Gabriela', 'apellidos' => 'Solís']);

    expect($e['estudiante']->fresh()->first_name)->toBe('Gabriela')
        ->and(fn () => $usuarios->actualizarDatos($e['soporte'], $e['docente']->id, ['nombres' => 'X', 'apellidos' => 'X']))->toThrow(AuthorizationException::class)
        ->and(fn () => $usuarios->actualizarDatos($e['soporte'], $e['administrador']->id, ['nombres' => 'X', 'apellidos' => 'X']))->toThrow(AuthorizationException::class)
        ->and(fn () => $usuarios->cambiarRoles($e['soporte'], $e['estudiante']->id, ['estudiante', 'coordinador'], ['coordinador' => ['carrera' => $e['isi']]]))->toThrow(AuthorizationException::class)
        ->and(fn () => $usuarios->desactivar($e['soporte'], $e['estudiante']->id, 'Prueba de alcance'))->toThrow(AuthorizationException::class)
        ->and($e['estudiante']->fresh()->is_active)->toBeTrue();
});

it('CP-RF04-16 lets technical support create teachers only while the Administrator allows it', function () {
    $e = escenario();
    $usuarios = app(UsuarioService::class);
    $cuenta = fn (string $correo) => nuevaCuenta($correo, 'docente', ['departamento' => $e['dia']]);

    actingAs($e['administrador']);
    $usuarios->permitirCrearDocentes($e['administrador'], $e['soporte']->id, true);
    $soporte = $e['soporte']->fresh();
    actingAs($soporte);
    $docente = $usuarios->crear($soporte, $cuenta('dc26001@ues.edu.sv'));

    expect($docente->getRoleNames()->all())->toBe(['docente'])
        ->and(DB::table('teacher_profiles')->where('user_id', $docente->id)->value('department_id'))->toBe($e['dia']);

    actingAs($e['administrador']);
    $usuarios->permitirCrearDocentes($e['administrador'], $e['soporte']->id, false);
    $soporte = $e['soporte']->fresh();
    actingAs($soporte);

    expect(fn () => $usuarios->crear($soporte, $cuenta('dc26002@ues.edu.sv')))->toThrow(AuthorizationException::class);
});

it('CP-RF04-16 only lets the Administrator grant teacher creation, and only to technical support', function () {
    $e = escenario();
    $usuarios = app(UsuarioService::class);

    actingAs($e['soporte']);
    expect(fn () => $usuarios->permitirCrearDocentes($e['soporte'], $e['soporte']->id, true))->toThrow(AuthorizationException::class);

    actingAs($e['administrador']);
    expect(fn () => $usuarios->permitirCrearDocentes($e['administrador'], $e['docente']->id, true))->toThrow(ValidationException::class);
});

it('CP-RF04-17 lets the head make an existing user a teacher and coordinator of their department', function () {
    $e = escenario();
    // Una cuenta sin rol (ADR-038): con Estudiante ya no podría recibir Docente (ADR-053).
    $persona = DbContext::system(fn () => User::factory()->create());
    $usuarios = app(UsuarioService::class);
    actingAs($e['jefe']);

    expect(fn () => $usuarios->cambiarRoles($e['jefe'], $persona->id, ['docente'], ['docente' => ['departamento' => $e['dia']]]))->toThrow(AuthorizationException::class);
    $usuarios->listar($e['jefe'], ['buscar' => $persona->institutional_email]);
    $usuarios->cambiarRoles($e['jefe'], $persona->id, ['docente'], ['docente' => ['departamento' => $e['dia']]]);
    $usuarios->cambiarRoles($e['jefe'], $persona->id, ['docente', 'coordinador'], ['coordinador' => ['carrera' => $e['isi']]]);

    expect($persona->fresh()->getRoleNames()->sort()->values()->all())->toBe(['coordinador', 'docente'])
        ->and(DB::table('teacher_profiles')->where('user_id', $persona->id)->value('department_id'))->toBe($e['dia'])
        ->and(DB::table('career_coordinators')->where('career_id', $e['isi'])->whereNull('valid_to')->value('user_id'))->toBe($persona->id);
});

it('CP-RF04-17 keeps the head inside their department', function () {
    $e = escenario();
    $persona = DbContext::system(fn () => User::factory()->create());
    $docenteAjeno = usuarioCon('docente');
    $lma = DbContext::system(function () use ($e, $docenteAjeno) {
        DB::table('teacher_profiles')->insert(['user_id' => $docenteAjeno->id, 'department_id' => $e['dcnm'], 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('careers')->insertGetId(['department_id' => $e['dcnm'], 'code' => 'LMA', 'name' => 'Licenciatura en Matemática', 'created_at' => now(), 'updated_at' => now()]);
    });
    $usuarios = app(UsuarioService::class);
    actingAs($e['jefe']);
    foreach ([$persona, $docenteAjeno] as $cuenta) {
        $usuarios->listar($e['jefe'], ['buscar' => $cuenta->institutional_email]);
    }

    expect(fn () => $usuarios->cambiarRoles($e['jefe'], $persona->id, ['docente'], ['docente' => ['departamento' => $e['dcnm']]]))->toThrow(AuthorizationException::class)
        ->and(fn () => $usuarios->cambiarRoles($e['jefe'], $persona->id, ['coordinador'], ['coordinador' => ['carrera' => $lma]]))->toThrow(AuthorizationException::class)
        ->and(fn () => $usuarios->cambiarRoles($e['jefe'], $docenteAjeno->id, ['coordinador'], ['coordinador' => ['carrera' => $e['isi']]]))->toThrow(AuthorizationException::class)
        ->and(fn () => $usuarios->cambiarRoles($e['jefe'], $docenteAjeno->id, ['docente'], ['docente' => ['departamento' => $e['dia']]]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AsignacionService::class)->perfilDocente($e['jefe'], $e['docente']->id, $e['dcnm']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AsignacionService::class)->asignarCoordinacion($e['jefe'], $e['coordinador']->id, $lma))->toThrow(AuthorizationException::class)
        ->and($persona->fresh()->getRoleNames()->all())->toBe([])
        ->and($docenteAjeno->fresh()->getRoleNames()->all())->toBe(['docente'])
        ->and(DB::table('teacher_profiles')->where('user_id', $docenteAjeno->id)->value('department_id'))->toBe($e['dcnm'])
        ->and(DB::table('teacher_profiles')->where('user_id', $e['docente']->id)->value('department_id'))->toBe($e['dia'])
        ->and(DB::table('career_coordinators')->where('user_id', $e['coordinador']->id)->whereNull('valid_to')->value('career_id'))->toBe($e['isi']);
});

it('CP-RF04-17 does not let the head give Teacher to someone whose teacher profile is in another department', function () {
    $e = escenario();
    $persona = usuarioCon('estudiante');
    DbContext::system(fn () => DB::table('teacher_profiles')->insert(['user_id' => $persona->id, 'department_id' => $e['dcnm'], 'created_at' => now(), 'updated_at' => now()]));
    $usuarios = app(UsuarioService::class);
    actingAs($e['jefe']);
    $usuarios->listar($e['jefe'], ['buscar' => $persona->institutional_email]);

    expect(fn () => $usuarios->cambiarRoles($e['jefe'], $persona->id, ['estudiante', 'docente']))->toThrow(AuthorizationException::class)
        ->and($persona->fresh()->getRoleNames()->all())->toBe(['estudiante']);
});

it('CP-RF04-17 denies the head the page of a user outside their scope', function () {
    $e = escenario();

    actingAs($e['jefe'])->get(route('usuarios.show', $e['estudiante']->id))->assertForbidden();
    actingAs($e['jefe'])->get(route('usuarios.show', $e['administrador']->id))->assertForbidden();
});

it('CP-RF04-17 rejects tampered roles sent by the head through the page', function () {
    $e = escenario();

    Livewire::actingAs($e['jefe'])->test(RolesUsuario::class, ['usuario' => $e['docente']->id])
        ->set('roles', ['docente', 'administrador'])
        ->call('guardar')
        ->assertForbidden();
    expect($e['docente']->fresh()->getRoleNames()->all())->toBe(['docente']);
});

it('CP-RF04-17 does not let the head create accounts, edit data, grant other roles or deactivate', function () {
    $e = escenario();
    $usuarios = app(UsuarioService::class);
    actingAs($e['jefe']);

    expect(fn () => $usuarios->crear($e['jefe'], nuevaCuenta('dc26003@ues.edu.sv', 'docente', ['departamento' => $e['dia']])))->toThrow(AuthorizationException::class)
        ->and(fn () => $usuarios->actualizarDatos($e['jefe'], $e['docente']->id, ['nombres' => 'X', 'apellidos' => 'X']))->toThrow(AuthorizationException::class)
        ->and(fn () => $usuarios->cambiarRoles($e['jefe'], $e['docente']->id, ['docente', 'administrador']))->toThrow(AuthorizationException::class)
        ->and(fn () => $usuarios->cambiarRoles($e['jefe'], $e['estudiante']->id, ['estudiante', 'docente'], ['docente' => ['departamento' => $e['dia']]]))->toThrow(AuthorizationException::class)
        ->and(fn () => $usuarios->desactivar($e['jefe'], $e['docente']->id, 'Prueba de alcance'))->toThrow(AuthorizationException::class)
        ->and($e['docente']->fresh()->getRoleNames()->all())->toBe(['docente'])
        ->and($e['estudiante']->fresh()->getRoleNames()->all())->toBe(['estudiante']);

    $usuarios->listar($e['jefe'], ['buscar' => $e['estudiante']->institutional_email]);
    expect(fn () => $usuarios->cambiarRoles($e['jefe'], $e['estudiante']->id, ['docente'], ['docente' => ['departamento' => $e['dia']]]))->toThrow(AuthorizationException::class)
        ->and($e['estudiante']->fresh()->getRoleNames()->all())->toBe(['estudiante']);
});

it('ignores users management granted to the head outside the matrix', function () {
    $e = escenario();
    actingAs($e['administrador']);
    app(RolService::class)->alternar($e['administrador'], 'jefe_departamento', 'usuarios.gestionar');
    $jefe = $e['jefe']->fresh();
    actingAs($jefe);

    expect(fn () => app(UsuarioService::class)->crear($jefe, nuevaCuenta('dc26004@ues.edu.sv', 'docente', ['departamento' => $e['dcnm']])))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AsignacionService::class)->perfilDocente($jefe, $e['docente']->id, $e['dcnm']))->toThrow(AuthorizationException::class);
});

it('CP-RF04-18 lets technical support give the first role to an account without roles', function () {
    $e = escenario();
    $sinRoles = DbContext::system(fn () => User::factory()->create());
    $usuarios = app(UsuarioService::class);
    actingAs($e['soporte']);

    $usuarios->actualizarDatos($e['soporte'], $sinRoles->id, ['nombres' => 'Kevin', 'apellidos' => 'Rodríguez']);
    $usuarios->cambiarRoles($e['soporte'], $sinRoles->id, ['estudiante'], ['estudiante' => ['carrera' => $e['isi'], 'plan' => $e['plan'], 'ciclo' => 1]]);

    expect($sinRoles->fresh()->getRoleNames()->all())->toBe(['estudiante'])
        ->and($sinRoles->fresh()->first_name)->toBe('Kevin')
        ->and(fn () => $usuarios->cambiarRoles($e['soporte'], DbContext::system(fn () => User::factory()->create())->id, ['coordinador'], ['coordinador' => ['carrera' => $e['isi']]]))->toThrow(AuthorizationException::class);
});

it('CP-RF04-18 lists the accounts without roles', function () {
    $e = escenario();
    $sinRoles = DbContext::system(fn () => User::factory()->create());
    $usuarios = app(UsuarioService::class);

    expect($usuarios->listar($e['administrador'], ['rol' => UsuarioService::SIN_ROL])->pluck('id')->all())->toBe([$sinRoles->id])
        ->and($usuarios->resumen($e['administrador'])['sinRol'])->toBe(1);
    actingAs($e['administrador'])->get(route('usuarios'))->assertOk()->assertSeeText(['Sin rol', '1 sin rol']);
});

it('CP-RF04-15 does not let technical support change the student profile of an Administrator', function () {
    $e = escenario();
    DbContext::system(fn () => $e['administrador']->assignRole('estudiante'));
    actingAs($e['soporte']);

    expect(fn () => app(AsignacionService::class)->perfilEstudiante($e['soporte'], $e['administrador']->id, ['carrera' => $e['isi'], 'plan' => $e['plan'], 'ciclo' => 3]))->toThrow(AuthorizationException::class);
});

it('CP-RF04-16 does not let technical support remove Teacher or move a teacher to another department', function () {
    $e = escenario();
    $persona = usuarioCon('estudiante');
    DbContext::system(function () use ($e, $persona) {
        $e['soporte']->givePermissionTo('usuarios.crear-docente');
        $persona->assignRole('docente');
        DB::table('teacher_profiles')->insert(['user_id' => $persona->id, 'department_id' => $e['dia'], 'created_at' => now(), 'updated_at' => now()]);
    });
    $soporte = $e['soporte']->fresh();
    $usuarios = app(UsuarioService::class);
    actingAs($soporte);

    expect(fn () => $usuarios->cambiarRoles($soporte, $persona->id, ['estudiante']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AsignacionService::class)->perfilDocente($soporte, $persona->id, $e['dcnm']))->toThrow(AuthorizationException::class)
        ->and(DB::table('teacher_profiles')->where('user_id', $persona->id)->value('department_id'))->toBe($e['dia']);
});

it('CP-RF04-16 does not let teacher creation be granted through the technical support role', function () {
    $e = escenario();
    actingAs($e['administrador']);
    app(RolService::class)->alternar($e['administrador'], 'soporte_tecnico', 'usuarios.crear-docente');
    $soporte = $e['soporte']->fresh();
    actingAs($soporte);

    expect(fn () => app(UsuarioService::class)->crear($soporte, nuevaCuenta('dc26005@ues.edu.sv', 'docente', ['departamento' => $e['dia']])))->toThrow(AuthorizationException::class);
});

it('CP-RF04-17 shows the head only their staff, plus the account whose full email they type', function () {
    $e = escenario();
    $usuarios = app(UsuarioService::class);

    $ids = fn (string $buscar = '') => $usuarios->listar($e['jefe'], ['buscar' => $buscar])->pluck('id')->all();

    expect($ids())->toContain($e['docente']->id, $e['coordinador']->id)->not->toContain($e['estudiante']->id, $e['administrador']->id)
        ->and($ids($e['estudiante']->institutional_email))->toBe([$e['estudiante']->id])
        ->and($ids(substr($e['estudiante']->institutional_email, 0, 4)))->not->toContain($e['estudiante']->id);
});

it('CP-RF04-17 hides access history and device from the head in the user page', function () {
    $e = escenario();

    actingAs($e['jefe'])->get(route('usuarios.show', $e['docente']->id))
        ->assertOk()
        ->assertSeeText('Roles y asignaciones')
        ->assertDontSeeText(['Editar datos', 'Últimos accesos', 'Teléfono de asistencia']);
});

it('shows each action only to whoever may use it in the user page', function () {
    $e = escenario();

    actingAs($e['soporte'])->get(route('usuarios.show', $e['estudiante']->id))->assertOk()->assertSeeText(['Editar datos', 'Últimos accesos']);
    actingAs($e['soporte'])->get(route('usuarios.show', $e['docente']->id))->assertOk()->assertDontSeeText(['Editar datos', 'Roles y asignaciones']);
    actingAs($e['administrador'])->get(route('usuarios.show', $e['soporte']->id))->assertOk()->assertSeeText('Permitir crear docentes');

    Livewire::actingAs($e['administrador'])->test(UsuarioDetalle::class, ['usuario' => $e['soporte']->id])
        ->call('permitirCrearDocentes', true)
        ->assertSeeText('Puede crear docentes');
});

it('CP-RF04-15 lets technical support import students of any career but no other role', function () {
    $e = escenario();
    $filas = [
        ['AB26001', 'Ana', 'Ruiz', 'ab26001@ues.edu.sv', 'estudiante', 'ISI', 'Plan 25', '1'],
        ['', 'Rosa', 'Díaz', 'rd26002@ues.edu.sv', 'docente', '', '', ''],
        ['', 'Luis', 'Mena', 'lm26003@ues.edu.sv', 'administrador', '', '', ''],
    ];
    actingAs($e['soporte']);

    $previa = Livewire::test(ImportarUsuarios::class)->set('archivo', UploadedFile::fake()->createWithContent('soporte.csv', "carnet,nombres,apellidos,correo,rol,carrera,plan,ciclo\n".implode("\n", array_map(fn ($f) => implode(',', $f), $filas))))->get('previa');

    expect($previa['validas'])->toBe(1)
        ->and(collect($previa['errores'])->map(fn ($error) => [$error['fila'], $error['campo']])->all())->toBe([[3, 'rol'], [4, 'rol']]);
});

it('CP-RF04-16 audits granting and revoking teacher creation', function () {
    $e = escenario();
    $usuarios = app(UsuarioService::class);
    actingAs($e['administrador']);

    $usuarios->permitirCrearDocentes($e['administrador'], $e['soporte']->id, true);
    $usuarios->permitirCrearDocentes($e['administrador'], $e['soporte']->id, false);
    DbContext::auditScope(all: true);

    expect(DB::table('audit_logs')->where('entity_type', 'model_has_permissions')->where('actor_user_id', $e['administrador']->id)->orderBy('id')->pluck('action')->all())
        ->toBe(['insert', 'delete']);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La auditoría por triggers solo existe en PostgreSQL.');

it('CP-RF04-22 rechaza nombrando el choque cuando la cuenta de un estudiante recibe Docente o Soporte Técnico', function (array $roles, array $asignaciones, string $mensaje) {
    $e = escenario();
    $usuarios = app(UsuarioService::class);
    actingAs($e['administrador']);
    $asignaciones = array_map(fn (array $datos) => array_map(fn ($valor) => is_string($valor) && isset($e[$valor]) ? $e[$valor] : $valor, $datos), $asignaciones);

    try {
        $usuarios->cambiarRoles($e['administrador'], $e['estudiante']->id, $roles, $asignaciones);
        $this->fail('Se esperaba el rechazo de la combinación de roles.');
    } catch (ValidationException $error) {
        expect($error->errors()['roles'][0])->toBe($mensaje);
    }

    expect($e['estudiante']->fresh()->getRoleNames()->all())->toBe(['estudiante'])
        ->and(DB::table('teacher_profiles')->where('user_id', $e['estudiante']->id)->exists())->toBeFalse();
})->with([
    'docente' => [['estudiante', 'docente'], ['docente' => ['departamento' => 'dia']], 'Una cuenta con el rol Estudiante no puede tener además el rol Docente.'],
    'soporte técnico' => [['estudiante', 'soporte_tecnico'], [], 'Una cuenta con el rol Estudiante no puede tener además el rol Soporte Técnico.'],
    'los dos a la vez' => [['estudiante', 'docente', 'soporte_tecnico'], ['docente' => ['departamento' => 'dia']], 'Una cuenta con el rol Estudiante no puede tener además los roles Docente y Soporte Técnico.'],
]);

it('CP-RF04-22 muestra el choque sobre las casillas de la pantalla de roles', function () {
    $e = escenario();

    Livewire::actingAs($e['administrador'])->test(RolesUsuario::class, ['usuario' => $e['estudiante']->id])
        ->set('roles', ['estudiante', 'docente'])
        ->call('guardar')
        ->assertHasErrors('roles');
    expect($e['estudiante']->fresh()->getRoleNames()->all())->toBe(['estudiante']);
});

it('CP-RF04-22 deja que Estudiante conviva con los roles compatibles', function () {
    $e = escenario();
    actingAs($e['administrador']);

    app(UsuarioService::class)->cambiarRoles($e['administrador'], $e['estudiante']->id, ['estudiante', 'coordinador'], ['coordinador' => ['carrera' => $e['isi']]]);

    expect($e['estudiante']->fresh()->getRoleNames()->sort()->values()->all())->toBe(['coordinador', 'estudiante'])
        ->and(DB::table('career_coordinators')->where('user_id', $e['estudiante']->id)->whereNull('valid_to')->value('career_id'))->toBe($e['isi']);
});

it('CP-RF04-22 no toca a las cuentas que ya tienen la combinación incompatible', function () {
    $e = escenario();
    $antigua = usuarioCon('estudiante');
    DbContext::system(function () use ($antigua, $e) {
        $antigua->assignRole(['docente', 'soporte_tecnico']);
        DB::table('teacher_profiles')->insert(['user_id' => $antigua->id, 'department_id' => $e['dia'], 'created_at' => now(), 'updated_at' => now()]);
    });
    $usuarios = app(UsuarioService::class);

    expect($usuarios->listar($e['administrador'], ['buscar' => $antigua->institutional_email])->pluck('id')->all())->toBe([$antigua->id])
        ->and($usuarios->ficha($e['administrador'], $antigua->id)['rolesActuales'])->toEqualCanonicalizing(['estudiante', 'docente', 'soporte_tecnico']);

    actingAs($e['administrador'])->get(route('usuarios.show', $antigua->id))->assertOk();
    expect($antigua->fresh()->getRoleNames()->sort()->values()->all())->toBe(['docente', 'estudiante', 'soporte_tecnico']);
});
