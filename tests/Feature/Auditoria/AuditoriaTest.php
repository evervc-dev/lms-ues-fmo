<?php

use App\Modules\Academica\Services\CargaAcademicaService;
use App\Modules\Academica\Services\PeriodoService;
use App\Modules\Asistencia\Livewire\DiagnosticoAsistencia;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Auditoria\Livewire\Auditoria;
use App\Modules\Auditoria\Services\AuditoriaService;
use App\Modules\Comunicacion\Livewire\Notificaciones;
use App\Modules\Identidad\Services\UsuarioService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Un evento de auditoría ya escrito. `$extra` sobrescribe cualquier columna (actor, fecha, acción, entidad)
 * para probar los filtros de la pantalla.
 *
 * @param  array<string, mixed>  $extra
 */
function eventoDeAuditoria(?int $departamento, string $evento, array $extra = []): void
{
    DB::table('audit_logs')->insert($extra + ['occurred_at' => now(), 'department_id' => $departamento, 'event_type' => $evento, 'entity_type' => 'grades', 'entity_id' => 1, 'action' => 'update',
        'old_values' => json_encode(['score' => 5]), 'new_values' => json_encode(['score' => 6])]);
}

it('CP-RF05-03 shows the department head only the events of their department', function () {
    $e = escenario();
    if (DB::connection()->getDriverName() === 'pgsql') {
        $this->markTestSkipped('En PostgreSQL los eventos los escriben los triggers; la RLS se prueba en SchemaTest.');
    }
    eventoDeAuditoria($e['dia'], 'grade.corrected.dia');
    eventoDeAuditoria($e['dcnm'], 'grade.corrected.dcnm');

    actingAs($e['jefe'])->get(route('auditoria'))->assertOk()->assertSeeText('grade.corrected.dia')->assertDontSeeText('grade.corrected.dcnm');
    actingAs($e['administrador'])->get(route('auditoria'))->assertOk()->assertSeeText(['grade.corrected.dia', 'grade.corrected.dcnm']);
});

it('CP-RF05-02 el Administrador ve todos los eventos y los filtra por usuario, fecha, acción y entidad', function () {
    $e = escenario();
    if (DB::connection()->getDriverName() === 'pgsql') {
        $this->markTestSkipped('En PostgreSQL los eventos los escriben los triggers; la RLS se prueba en SchemaTest.');
    }
    eventoDeAuditoria($e['dia'], 'grade.corrected.dia', ['actor_user_id' => $e['docente']->id]);
    eventoDeAuditoria($e['dcnm'], 'grade.corrected.dcnm', ['actor_user_id' => $e['administrador']->id]);
    // Un tercer evento de otro día, otro actor, otra entidad y otra acción: el que separa cada filtro.
    eventoDeAuditoria($e['dia'], 'enrollment.created.julio', ['occurred_at' => '2026-07-28 08:00:00',
        'actor_user_id' => $e['coordinador']->id, 'entity_type' => 'enrollments', 'entity_id' => 7, 'action' => 'insert']);

    $auditoria = app(AuditoriaService::class);
    /** @var callable(array<string, string>): array<int, string> $eventos */
    $eventos = fn (array $filtros) => collect($auditoria->listar($e['administrador'], $filtros)->items())->pluck('event_type')->all();
    $deHoy = ['grade.corrected.dcnm', 'grade.corrected.dia'];

    expect($eventos([]))->toHaveCount(3)
        // Por usuario: el correo institucional del actor.
        ->and($eventos(['buscar' => $e['coordinador']->institutional_email]))->toBe(['enrollment.created.julio'])
        ->and($eventos(['buscar' => $e['docente']->institutional_email]))->toBe(['grade.corrected.dia'])
        // Por fecha: desde, hasta y el rango de un solo día.
        ->and($eventos(['desde' => now()->toDateString()]))->toEqualCanonicalizing($deHoy)
        ->and($eventos(['hasta' => '2026-07-28']))->toBe(['enrollment.created.julio'])
        ->and($eventos(['desde' => '2026-07-28', 'hasta' => '2026-07-28']))->toBe(['enrollment.created.julio'])
        ->and($eventos(['desde' => '2026-07-29', 'hasta' => '2026-07-29']))->toBeEmpty()
        // Por tipo de acción.
        ->and($eventos(['accion' => 'insert']))->toBe(['enrollment.created.julio'])
        ->and($eventos(['accion' => 'update']))->toEqualCanonicalizing($deHoy)
        ->and($eventos(['accion' => 'delete']))->toBeEmpty()
        // Por entidad, y los cuatro filtros combinados.
        ->and($eventos(['entidad' => 'enrollments']))->toBe(['enrollment.created.julio'])
        ->and($eventos(['entidad' => 'grades']))->toEqualCanonicalizing($deHoy)
        ->and($eventos(['entidad' => 'grades', 'accion' => 'insert']))->toBeEmpty()
        ->and($eventos(['buscar' => $e['coordinador']->institutional_email, 'desde' => '2026-07-28', 'accion' => 'insert', 'entidad' => 'enrollments']))->toBe(['enrollment.created.julio'])
        // Las entidades que ofrece el filtro son las que hay en los eventos.
        ->and($auditoria->entidades($e['administrador']))->toContain('enrollments', 'grades');
});

it('CP-RF05-02 exporta a CSV los eventos filtrados dentro de su alcance', function () {
    $e = escenario();
    if (DB::connection()->getDriverName() === 'pgsql') {
        $this->markTestSkipped('En PostgreSQL los eventos los escriben los triggers; la RLS se prueba en SchemaTest.');
    }
    eventoDeAuditoria($e['dia'], 'grade.corrected.dia');
    eventoDeAuditoria($e['dcnm'], 'grade.corrected.dcnm');

    $delJefe = app(AuditoriaService::class)->exportar($e['jefe'], []);
    $filtrado = app(AuditoriaService::class)->exportar($e['administrador'], ['buscar' => 'dcnm']);

    expect(collect($delJefe['filas'])->pluck(3)->all())->toBe(['grade.corrected.dia'])
        ->and(collect($filtrado['filas'])->pluck(3)->all())->toBe(['grade.corrected.dcnm'])
        ->and($delJefe['columnas'])->toContain('Qué pasó', 'Qué cambió');

    actingAs($e['administrador']);
    Livewire::test(Auditoria::class)->call('exportar', 'csv')->assertFileDownloaded('auditoria-'.now()->format('Y-m-d').'.csv');
});

it('CP-RF05-03 no deja al Jefe ver eventos de otro departamento aunque los busque', function () {
    $e = escenario();
    if (DB::connection()->getDriverName() === 'pgsql') {
        $this->markTestSkipped('En PostgreSQL los eventos los escriben los triggers; la RLS se prueba aparte.');
    }
    eventoDeAuditoria($e['dia'], 'grade.corrected.dia');
    eventoDeAuditoria($e['dcnm'], 'grade.corrected.dcnm');

    $filas = app(AuditoriaService::class)->listar($e['jefe'], ['buscar' => 'dcnm'])->items();

    expect($filas)->toBeEmpty();
});

it('CP-RF05-03 la RLS de PostgreSQL oculta al Jefe los eventos de otro departamento aunque consulte sin filtros', function () {
    $e = escenario();
    eventoDeAuditoria($e['dia'], 'grade.corrected.dia');
    eventoDeAuditoria($e['dcnm'], 'grade.corrected.dcnm');

    // Sin where: lo que se ve lo decide solo la política de la tabla.
    DbContext::auditScope(false, [$e['dia']]);
    $visibles = DB::table('audit_logs')->whereIn('event_type', ['grade.corrected.dia', 'grade.corrected.dcnm'])->pluck('event_type')->all();

    expect($visibles)->toBe(['grade.corrected.dia']);
})->skip(fn () => rlsNoVerificable(), 'La RLS solo se puede verificar en PostgreSQL con un rol que no la evite (ADR-029, ADR-033).');

it('CP-RF05-04 forbids the audit trail to every role but the administrator and the head', function (string $rol) {
    $e = escenario();

    actingAs($e[$rol])->get(route('auditoria'))->assertForbidden();
    expect(fn () => app(AuditoriaService::class)->listar($e[$rol], []))->toThrow(AuthorizationException::class);
})->with(['coordinador', 'docente', 'estudiante', 'soporte']);

it('CP-RF05-01 registra quién, cuándo, desde qué IP, la acción, la entidad y los valores de cada cambio crítico', function (string $cambio) {
    $e = escenario();
    [$actor, $entidad, $hacer] = match ($cambio) {
        'asignación docente' => [$e['administrador'], 'teaching_assignments', function () use ($e) {
            $docente = usuarioCon('docente');
            DbContext::system(fn () => DB::table('teacher_profiles')->insert(['user_id' => $docente->id, 'department_id' => $e['dia'], 'created_at' => now(), 'updated_at' => now()]));
            app(CargaAcademicaService::class)->asignarDocente($e['administrador'], $e['seccion'], $docente->id, false);
        }],
        'corrección de asistencia' => [$e['docente'], 'attendance_records', function () use ($e) {
            $asistencia = app(AsistenciaService::class);
            $sesion = $asistencia->abrir($e['docente'], $e['seccion'], 10);
            $asistencia->cerrar($e['docente'], $e['seccion'], $sesion);
            $asistencia->marcar($e['docente'], $e['seccion'], $sesion, $e['matricula'], 'present', 'Llegó con el docente.');
        }],
        'modificación de período' => [$e['administrador'], 'academic_periods', function () use ($e) {
            $periodo = DbContext::system(fn () => DB::table('academic_periods')->insertGetId(['name' => '2027-I', 'start_date' => '2027-02-01', 'end_date' => '2027-06-30',
                'status' => 'planned', 'preparation_stage' => 'planning', 'created_at' => now(), 'updated_at' => now()]));
            app(PeriodoService::class)->avanzarEtapa($e['administrador'], $periodo);
        }],
        'gestión de usuarios' => [$e['administrador'], 'users', fn () => app(UsuarioService::class)->desactivar($e['administrador'], $e['estudiante']->id, 'Retiro de la carrera.')],
    };
    actingAs($actor);

    $hacer();

    DbContext::auditScope(all: true);
    $evento = DB::table('audit_logs')->where('entity_type', $entidad)->where('actor_user_id', $actor->id)->whereIn('action', ['insert', 'update'])->orderByDesc('id')->first();
    expect($evento)->not->toBeNull()
        ->and((int) $evento->actor_user_id)->toBe($actor->id)
        ->and($evento->occurred_at)->not->toBeNull()
        ->and($evento->ip_address)->not->toBeNull()
        ->and($evento->entity_id)->not->toBeNull()
        ->and($evento->new_values)->not->toBeNull();
})->with(['asignación docente', 'corrección de asistencia', 'modificación de período', 'gestión de usuarios'])
    ->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La auditoría la escriben los triggers, solo en PostgreSQL (ADR-033).');

it('CP-RF05-05 nadie edita ni borra un registro de auditoría, tampoco el Administrador', function () {
    $e = escenario();
    actingAs($e['administrador']);
    DbContext::run(fn () => eventoDeAuditoria($e['dia'], 'grade.corrected.dia'));
    DbContext::auditScope(all: true);
    $id = (int) DB::table('audit_logs')->where('event_type', 'grade.corrected.dia')->value('id');

    expect(fn () => DbContext::run(fn () => DB::table('audit_logs')->where('id', $id)->update(['event_type' => 'maquillado'])))->toThrow(QueryException::class)
        ->and(fn () => DbContext::run(fn () => DB::table('audit_logs')->where('id', $id)->delete()))->toThrow(QueryException::class);

    DbContext::auditScope(all: true);
    expect(DB::table('audit_logs')->where('id', $id)->value('event_type'))->toBe('grade.corrected.dia');
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La inmutabilidad la dan los triggers, solo en PostgreSQL (ADR-027, ADR-033).');

it('shows support the rejected scans of today without coordinates', function () {
    $e = escenario();
    DbContext::system(function () use ($e) {
        $sesion = DB::table('attendance_sessions')->insertGetId(['ulid' => (string) str()->ulid(), 'section_id' => $e['seccion'], 'session_date' => now()->toDateString(), 'status' => 'closed', 'opened_by' => $e['docente']->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('attendance_scan_attempts')->insert(['session_id' => $sesion, 'student_id' => $e['estudiante']->id, 'result' => 'out_of_range', 'distance_m' => 640, 'occurred_at' => now()]);
    });
    actingAs($e['soporte']);

    Livewire::test(DiagnosticoAsistencia::class)->assertSee('Fuera del área')->assertSee('640 m')->assertSee('PRN115');
});

it('marks notifications as read', function () {
    $e = escenario();
    DbContext::system(fn () => $e['estudiante']->notifications()->create(['id' => (string) str()->uuid(), 'type' => 'Prueba', 'event_code' => 'grade.published', 'data' => ['title' => 'Notas publicadas: Parcial 1']]));
    actingAs($e['estudiante']);

    Livewire::test(Notificaciones::class)->assertSee('Notas publicadas: Parcial 1')->call('marcarTodas');

    expect($e['estudiante']->unreadNotifications()->count())->toBe(0);
});

it('CP-RF05-06 no cuenta las filas de la auditoría para paginar ni rehace la lista de entidades', function () {
    // La pantalla no llegaba a cargar: el count(*) de paginate() y el distinct de las entidades recorrían
    // entera la tabla particionada en cada render (revisión de diseño del 25/10/2026).
    $e = escenario();

    $consultas = 0;
    DB::listen(function ($consulta) use (&$consultas) {
        if (str_contains($consulta->sql, 'audit_logs')) {
            $consultas++;
        }
    });

    $pagina = app(AuditoriaService::class)->listar($e['administrador'], []);
    expect($pagina)->not->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($pagina)->toBeInstanceOf(Paginator::class);

    app(AuditoriaService::class)->entidades($e['administrador']);
    $trasLaPrimera = $consultas;
    app(AuditoriaService::class)->entidades($e['administrador']);

    expect($consultas)->toBe($trasLaPrimera);
});

it('CP-RF05-07 la auditoría tiene índice por fecha, que es como la lee la pantalla', function () {
    $indices = collect(Schema::getIndexes('audit_logs'))->pluck('columns')->map(fn ($c) => (array) $c);

    expect($indices->contains(fn (array $columnas) => $columnas[0] === 'occurred_at'))->toBeTrue();
});

it('CP-RF05-02 la auditoría en palabras muestra una columna JSON como su JSON en vez de fallar', function () {
    $antes = json_encode(['status' => 'active', 'settings' => ['modo' => 'qr']]);
    $despues = json_encode(['status' => 'withdrawn', 'settings' => ['modo' => 'meet', 'radio' => 50]]);

    expect(AuditoriaService::cambiosEnTexto($antes, $despues))
        ->toContain('Estado: Activa → ')
        ->toContain('settings: {"modo":"qr"} → {"modo":"meet","radio":50}');
});
