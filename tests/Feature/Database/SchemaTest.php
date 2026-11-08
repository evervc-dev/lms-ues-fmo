<?php

use App\Support\DbContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Esquema del modelo v2 (docs/07 Datos/Modelo entidad-relación.md).
 * Corre en SQLite (por defecto) y en PostgreSQL (CI), ADR-033.
 */
function seedSection(): array
{
    return DbContext::system(function (): array {
        $student = DB::table('users')->insertGetId(['institutional_email' => 'rr19118@ues.edu.sv', 'first_name' => 'Ana', 'last_name' => 'Ruiz', 'created_at' => now(), 'updated_at' => now()]);
        $department = DB::table('departments')->insertGetId(['code' => 'DIA', 'name' => 'Ingeniería', 'created_at' => now(), 'updated_at' => now()]);
        $period = DB::table('academic_periods')->insertGetId(['name' => '2026-II', 'start_date' => '2026-07-01', 'end_date' => '2026-12-15', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $subject = DB::table('subjects')->insertGetId(['code' => 'PRN115', 'name' => 'Programación I', 'credits' => 4, 'department_id' => $department, 'created_at' => now(), 'updated_at' => now()]);
        $sectionA = DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $period, 'subject_id' => $subject, 'code' => '01', 'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
        $sectionB = DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $period, 'subject_id' => $subject, 'code' => '02', 'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);

        return compact('student', 'department', 'period', 'subject', 'sectionA', 'sectionB');
    });
}

function enroll(array $ids, int $section): int
{
    return DbContext::system(fn () => DB::table('enrollments')->insertGetId([
        'student_id' => $ids['student'], 'section_id' => $section, 'period_id' => $ids['period'],
        'subject_id' => $ids['subject'], 'enrolled_via' => 'key', 'created_at' => now(), 'updated_at' => now(),
    ]));
}

it('crea las tablas principales de cada módulo', function () {
    foreach (['users', 'student_profiles', 'teaching_assignments', 'enrollments', 'enrollment_changes', 'units', 'resources',
        'assessments', 'submissions', 'quiz_attempts', 'grades', 'grade_sheets', 'department_closures', 'attendance_sessions',
        'qr_tokens', 'attendance_records', 'meet_reports', 'notifications', 'audit_logs', 'roles', 'permissions'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Falta la tabla {$table}");
    }
});

it('permite una sola matrícula activa por estudiante, materia y período', function () {
    $ids = seedSection();
    enroll($ids, $ids['sectionA']);

    expect(fn () => enroll($ids, $ids['sectionB']))->toThrow(QueryException::class);
});

it('CP-RF06-01 permite un solo período activo en la base', function () {
    seedSection();

    expect(fn () => DbContext::system(fn () => DB::table('academic_periods')->insert([
        'name' => '2027-I', 'start_date' => '2027-01-10', 'end_date' => '2027-06-30', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(QueryException::class);
});

it('permite muchos usuarios normales y una sola cuenta de emergencia', function () {
    $user = fn (string $email, bool $emergency) => DbContext::system(fn () => DB::table('users')->insert([
        'institutional_email' => $email, 'first_name' => 'Ana', 'last_name' => 'Ruiz', 'is_emergency' => $emergency,
        'password' => $emergency ? 'hash' : null, 'created_at' => now(), 'updated_at' => now(),
    ]));

    $user('aa00001@ues.edu.sv', false);
    $user('aa00002@ues.edu.sv', false);
    $user('emergencia@ues.edu.sv', true);

    expect(fn () => $user('emergencia2@ues.edu.sv', true))->toThrow(QueryException::class);
});

it('exige que la nota y la matrícula sean de la misma sección', function () {
    $ids = seedSection();
    $enrollment = enroll($ids, $ids['sectionA']);

    $item = DbContext::system(function () use ($ids): int {
        $component = DB::table('grading_components')->insertGetId(['section_id' => $ids['sectionB'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-01', 'ends_on' => '2026-08-31', 'weight_percent' => 33.33, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('grade_items')->insertGetId(['component_id' => $component, 'section_id' => $ids['sectionB'], 'item_type' => 'manual', 'title' => 'Parcial', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now()]);
    });

    expect(fn () => DbContext::system(fn () => DB::table('grades')->insert([
        'grade_item_id' => $item, 'enrollment_id' => $enrollment, 'section_id' => $ids['sectionB'], 'score' => 8, 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(QueryException::class);
});

it('expone la capacidad usada de cada sección', function () {
    $ids = seedSection();
    enroll($ids, $ids['sectionA']);

    $row = DB::table('v_section_capacity')->where('section_id', $ids['sectionA'])->first();

    expect((int) $row->active_enrollments)->toBe(1)
        ->and((int) $row->max_capacity)->toBe(40);
});

it('audita y protege los datos solo en PostgreSQL', function () {
    $ids = seedSection();
    enroll($ids, $ids['sectionA']);

    DbContext::auditScope(all: true);
    expect(DB::table('audit_logs')->where('entity_type', 'enrollments')->count())->toBe(1);

    expect(fn () => DB::table('users')->insert(['institutional_email' => 'sin.actor@ues.edu.sv', 'first_name' => 'X', 'last_name' => 'Y', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'Los triggers de auditoría existen solo en PostgreSQL (ADR-033).');
