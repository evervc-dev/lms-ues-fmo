<?php

use App\Models\User;
use App\Support\Navegacion\Vistas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class)->group('humo');

/**
 * Prueba de humo con la demo completa (DatabaseSeeder): cada cuenta *.dev abre cada vista del registro
 * con ids reales de su alcance. Ninguna puede responder 500.
 */
function parametrosReales(User $usuario, string $uri): array
{
    $seccion = DB::table('sections')->join('academic_periods', 'academic_periods.id', '=', 'sections.period_id')->where('academic_periods.status', 'active')
        ->where(fn ($q) => $q->whereIn('sections.id', DB::table('teaching_assignments')->where('teacher_id', $usuario->id)->whereNull('ended_at')->select('section_id'))
            ->orWhereIn('sections.id', DB::table('enrollments')->where('student_id', $usuario->id)->where('status', 'active')->select('section_id')))
        ->value('sections.id') ?? DB::table('sections')->join('academic_periods', 'academic_periods.id', '=', 'sections.period_id')->where('academic_periods.status', 'active')->value('sections.id');
    $ulid = DB::table('sections')->where('id', $seccion)->value('ulid');
    $evaluacion = fn (string $tipo) => DB::table('assessments')->where('section_id', $seccion)->where('assessment_type', $tipo)->value('ulid') ?? 'x';

    preg_match_all('/\{(\w+)\??\}/', $uri, $m);

    return collect($m[1])->mapWithKeys(fn ($p) => [$p => match ($p) {
        'seccion' => $ulid,
        'recurso' => DB::table('resources')->join('units', 'units.id', '=', 'resources.unit_id')->where('units.section_id', $seccion)->whereNull('resources.deleted_at')->value('resources.ulid') ?? 'x',
        'tarea' => $evaluacion('assignment'),
        'cuestionario' => $evaluacion('quiz'),
        'sesion' => DB::table('attendance_sessions')->where('section_id', $seccion)->value('ulid') ?? 'x',
        'hilo' => DB::table('forum_threads')->where('section_id', $seccion)->value('id') ?? 999999,
        'intento' => DB::table('quiz_attempts')->join('enrollments', 'enrollments.id', '=', 'quiz_attempts.enrollment_id')->where('enrollments.student_id', $usuario->id)->value('quiz_attempts.ulid') ?? 'x',
        'banco' => DB::table('question_banks')->where('owner_id', $usuario->id)->value('id') ?? 999999,
        'usuario' => $usuario->id,
        'periodo' => DB::table('academic_periods')->value('id'),
        'lote' => DB::table('import_batches')->value('id') ?? 999999,
        'manual' => 'bienvenida',
        default => 'x',
    }])->all();
}

it('opens every screen with every development account on the demo data without errors', function () {
    $this->seed();
    $this->artisan('ayuda:sincronizar');
    $fallas = [];

    foreach (['admin', 'jefe', 'coordinador', 'docente', 'estudiante', 'soporte'] as $cuenta) {
        $usuario = User::query()->where('institutional_email', "{$cuenta}.dev@ues.edu.sv")->firstOrFail();
        foreach (['inicio' => ['uri' => '/inicio']] + Vistas::todas() as $nombre => $vista) {
            $respuesta = $this->actingAs($usuario)->get(route($nombre, parametrosReales($usuario, $vista['uri'])));
            if ($respuesta->status() >= 500) {
                $fallas[] = "{$cuenta} en {$nombre}: ".($respuesta->exception?->getMessage() ?? $respuesta->status());
            }
        }
    }

    expect($fallas)->toBe([]);
});
