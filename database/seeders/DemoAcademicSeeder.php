<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\InsertsRows;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Actividad académica de prueba (solo local y staging), coherente con las reglas del modelo v2:
 * secciones sin choques de aula, docente ni estudiante; una matrícula activa por materia;
 * notas y asistencia ligadas a la matrícula de la misma sección.
 */
class DemoAcademicSeeder extends Seeder
{
    use InsertsRows;

    private const CYCLES = [1, 3, 5, 7, 9];

    /** Franja (índice de materia en el ciclo) => [[día, inicio, fin], ...] en la mañana. */
    private const SLOTS = [
        0 => [[1, '07:00', '08:40'], [3, '07:00', '08:40']],
        1 => [[1, '08:50', '10:30'], [3, '08:50', '10:30']],
        2 => [[2, '07:00', '08:40'], [4, '07:00', '08:40']],
        3 => [[2, '08:50', '10:30'], [4, '08:50', '10:30']],
        4 => [[5, '07:00', '08:40'], [5, '08:50', '10:30']],
    ];

    private int $period;

    private int $pastPeriod;

    /** @var array<string, int> */
    private array $dev;

    /** @var array<int, float> student_id => propensión a asistir */
    private array $attendanceRate = [];

    /** @var array<int, float> student_id => habilidad */
    private array $skill = [];

    public function run(): void
    {
        mt_srand(2026);
        $this->period = (int) DB::table('academic_periods')->where('name', '2026-II')->value('id');
        $this->pastPeriod = (int) DB::table('academic_periods')->where('name', '2026-I')->value('id');
        $this->dev = DB::table('users')->where('institutional_email', 'like', '%.dev@ues.edu.sv')->pluck('id', 'institutional_email')
            ->mapWithKeys(fn ($id, $email) => [Str::before($email, '.dev@') => (int) $id])->all();

        $sections = $this->seedSections();
        $this->seedEnrollmentKeys();
        $this->seedEnrollmentDeadlines();
        $enrollments = $this->seedEnrollments($sections);
        $content = $this->seedContent($sections, $enrollments);
        $this->seedAssessmentsAndGrades($sections, $enrollments, $content);
        $this->seedAttendance($sections, $enrollments);
        $this->seedAlerts($enrollments);
        $this->seedCommunication($sections, $enrollments);
        $this->seedCalendarAndIntegrations($sections);
        $this->seedHistory();
        $this->seedAuditSamples();

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('REFRESH MATERIALIZED VIEW mv_offer_stats');
        }
    }

    /**
     * @return Collection<int, object>
     */
    private function seedSections(): Collection
    {
        $subjects = DB::table('subjects')->get()->keyBy('code');
        $classrooms = DB::table('classrooms')->where('code', 'not like', 'LAB%')->where('code', 'not like', 'AUD%')->orderBy('id')->pluck('id')->values();
        $teacherDept = DB::table('teacher_profiles')
            ->join('model_has_roles', 'model_has_roles.model_id', '=', 'teacher_profiles.user_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->where('roles.name', 'docente')
            ->pluck('teacher_profiles.department_id', 'teacher_profiles.user_id');
        $busy = [];
        $load = $teacherDept->map(fn () => 0)->all();
        $forced = ['PRN115-01' => $this->dev['docente'], 'BAD115-02' => $this->dev['docente'], 'DSI115-01' => $this->dev['coordinador']];
        foreach ($forced as $key => $teacher) {
            [$code, $group] = explode('-', $key);
            $index = collect(InstitutionSeeder::SUBJECTS)->filter(fn ($s) => $s[1] === InstitutionSeeder::SUBJECTS[$code][1])->keys()->search($code);
            $busy[$teacher][$index.'-'.((int) $group - 1)] = true;
        }

        $pick = function (int $department, string $slot, ?int $except = null) use (&$busy, &$load, $teacherDept): int {
            $candidates = collect($load)->keys()->filter(fn ($t) => ! isset($busy[$t][$slot]) && $t !== $except);
            $own = $candidates->filter(fn ($t) => (int) $teacherDept[$t] === $department);
            $pool = $own->isNotEmpty() ? $own : $candidates;

            return (int) $pool->sortBy(fn ($t) => $load[$t] * 1000 + $t)->first();
        };

        $sectionRows = [];
        foreach (self::CYCLES as $cycleIndex => $cycle) {
            $codes = collect(InstitutionSeeder::SUBJECTS)->filter(fn ($s) => $s[1] === $cycle)->keys()->values();
            foreach ([0, 1] as $s) {
                $classroom = $classrooms[$cycleIndex * 2 + $s];
                foreach ($codes as $i => $code) {
                    $subject = $subjects[$code];
                    $sectionCode = str_pad((string) ($s + 1), 2, '0', STR_PAD_LEFT);
                    $sectionId = DB::table('sections')->insertGetId([
                        'ulid' => (string) Str::ulid(), 'period_id' => $this->period, 'subject_id' => $subject->id,
                        'code' => $sectionCode, 'min_capacity' => 10, 'max_capacity' => 40, 'status' => 'open',
                    ] + $this->stamps(now()->parse('2026-07-01')));

                    foreach (self::SLOTS[$i] as [$day, $start, $end]) {
                        DB::table('section_schedules')->insert([
                            'section_id' => $sectionId, 'period_id' => $this->period, 'classroom_id' => $classroom, 'weekday' => $day,
                            'start_time' => $this->shift($start, $s), 'end_time' => $this->shift($end, $s),
                        ] + $this->stamps(now()->parse('2026-07-01')));
                    }

                    $slot = "{$i}-{$s}";
                    $teacher = $forced["{$code}-{$sectionCode}"] ?? $pick((int) $subject->department_id, $slot);
                    $busy[$teacher][$slot] = true;
                    $load[$teacher] = ($load[$teacher] ?? 0) + 1;
                    DB::table('teaching_assignments')->insert(['section_id' => $sectionId, 'teacher_id' => $teacher, 'is_responsible' => true, 'started_at' => '2026-07-01'] + $this->stamps());

                    $sectionRows[] = (object) ['id' => $sectionId, 'code' => $code, 'section' => $sectionCode, 'cycle' => $cycle, 'index' => $i, 'shift' => $s,
                        'subject_id' => $subject->id, 'subject' => $subject->name, 'department_id' => $subject->department_id, 'teacher' => $teacher];
                }
            }
        }

        // Cuatro secciones compartidas por dos docentes (el segundo no es responsable).
        foreach (collect($sectionRows)->where('cycle', 7)->take(4) as $section) {
            $slot = "{$section->index}-{$section->shift}";
            $second = $pick((int) $section->department_id, $slot, $section->teacher);
            $busy[$second][$slot] = true;
            DB::table('teaching_assignments')->insert(['section_id' => $section->id, 'teacher_id' => $second, 'is_responsible' => false, 'started_at' => '2026-07-01'] + $this->stamps());
            $section->coTeacher = $second;
        }

        // Una reasignación histórica (el docente anterior dejó la sección en agosto).
        $first = $sectionRows[5];
        DB::table('teaching_assignments')->insert(['section_id' => $first->id, 'teacher_id' => $this->dev['coordinador'], 'is_responsible' => false,
            'started_at' => '2026-07-01', 'ended_at' => '2026-08-10'] + $this->stamps());

        return collect($sectionRows);
    }

    private function seedEnrollmentKeys(): void
    {
        $rows = [];
        foreach (DB::table('subjects')->get(['id', 'code']) as $subject) {
            $rows[] = ['subject_id' => $subject->id, 'period_id' => $this->period, 'key_hash' => Hash::make('ISI-'.$subject->code, ['rounds' => 4]),
                'valid_until' => '2026-08-14 23:59:00', 'is_active' => true] + $this->stamps(now()->parse('2026-07-20'));
        }
        $this->insertRows('enrollment_keys', $rows);
    }

    /**
     * Fecha límite de inscripción del período activo (RF29, ADR-051): una por departamento, hasta el fin del período,
     * para que el entorno de desarrollo pueda inscribir hoy. Va aquí y no en InstitutionSeeder porque set_by exige un
     * usuario y las cuentas se crean en DemoPeopleSeeder.
     */
    private function seedEnrollmentDeadlines(): void
    {
        $end = DB::table('academic_periods')->where('id', $this->period)->value('end_date');
        $rows = [];
        foreach (DB::table('departments')->pluck('id') as $departmentId) {
            $rows[] = ['period_id' => $this->period, 'department_id' => $departmentId, 'deadline' => $end, 'set_by' => $this->dev['admin']]
                + $this->stamps(now()->parse('2026-07-20'));
        }
        $this->insertRows('enrollment_deadlines', $rows);
    }

    /**
     * @param  Collection<int, object>  $sections
     * @return Collection<int, object>
     */
    private function seedEnrollments(Collection $sections): Collection
    {
        $students = DB::table('student_careers')->where('status', 'active')->orderBy('student_id')->get(['student_id', 'current_cycle']);
        $rows = [];
        foreach ($students->groupBy('current_cycle') as $cycle => $group) {
            foreach ($group->values() as $k => $student) {
                $this->attendanceRate[$student->student_id] = min(0.99, max(0.55, $this->gauss(0.91, 0.06)));
                $this->skill[$student->student_id] = min(0.98, max(0.35, $this->gauss(0.72, 0.15)));
                if ((int) $student->student_id === $this->dev['estudiante']) {
                    continue;
                }
                $section = (string) ($k % 2 === 0 ? '01' : '02');
                foreach ($sections->where('cycle', (int) $cycle)->where('section', $section) as $s) {
                    $rows[] = ['student_id' => $student->student_id, 'section_id' => $s->id, 'period_id' => $this->period, 'subject_id' => $s->subject_id,
                        'status' => 'active', 'enrolled_via' => $k % 10 === 0 ? 'coordinator' : 'key',
                        'enrolled_by' => $k % 10 === 0 ? $this->dev['coordinador'] : $student->student_id] + $this->stamps(now()->parse('2026-07-27'));
                }
            }
        }
        // estudiante.dev solo en las secciones de docente.dev (no chocan: el mismo docente no da dos a la vez), para
        // probar con las dos cuentas de desarrollo la asistencia con QR y el resto del curso.
        foreach ($sections->where('teacher', $this->dev['docente']) as $s) {
            $rows[] = ['student_id' => $this->dev['estudiante'], 'section_id' => $s->id, 'period_id' => $this->period, 'subject_id' => $s->subject_id,
                'status' => 'active', 'enrolled_via' => 'key', 'enrolled_by' => $this->dev['estudiante']] + $this->stamps(now()->parse('2026-07-27'));
        }
        $this->insertRows('enrollments', $rows);

        $enrollments = DB::table('enrollments')->where('period_id', $this->period)->get();
        $this->insertRows('enrollment_changes', $enrollments->map(fn ($e) => [
            'enrollment_id' => $e->id, 'change_type' => 'enrolled', 'reason' => null, 'actor_id' => $e->enrolled_by, 'occurred_at' => $e->created_at,
        ])->all());

        // Cinco retiros con motivo.
        foreach ($enrollments->slice(30, 5) as $e) {
            DB::table('enrollments')->where('id', $e->id)->update(['status' => 'withdrawn', 'updated_at' => '2026-08-20']);
            DB::table('enrollment_changes')->insert(['enrollment_id' => $e->id, 'change_type' => 'withdrawn', 'reason' => 'Retiro voluntario por motivos laborales.', 'actor_id' => $this->dev['coordinador'], 'occurred_at' => '2026-08-20']);
        }

        // Tres cambios de grupo aprobados (01 -> 02 de la misma materia: la tarde no choca con la mañana).
        foreach ($enrollments->filter(fn ($e) => $sections->firstWhere('id', $e->section_id)->section === '01')->slice(100, 3) as $e) {
            $target = $sections->first(fn ($s) => $s->subject_id === $e->subject_id && $s->section === '02');
            DB::table('enrollments')->where('id', $e->id)->update(['status' => 'transferred', 'updated_at' => '2026-08-05']);
            $newId = DB::table('enrollments')->insertGetId(['student_id' => $e->student_id, 'section_id' => $target->id, 'period_id' => $this->period,
                'subject_id' => $e->subject_id, 'status' => 'active', 'enrolled_via' => 'group_change', 'enrolled_by' => $this->dev['coordinador']] + $this->stamps(now()->parse('2026-08-05')));
            DB::table('enrollments')->where('id', $e->id)->update(['transferred_to_id' => $newId]);
            DB::table('enrollment_changes')->insert(['enrollment_id' => $e->id, 'change_type' => 'transferred', 'reason' => 'Cambio de grupo por horario de trabajo.', 'actor_id' => $this->dev['coordinador'], 'occurred_at' => '2026-08-05']);
            DB::table('section_change_requests')->insert(['enrollment_id' => $e->id, 'target_section_id' => $target->id, 'justification' => 'Trabajo por las mañanas.',
                'status' => 'approved', 'resolved_by' => $this->dev['coordinador'], 'resolved_at' => '2026-08-05'] + $this->stamps(now()->parse('2026-08-03')));
        }

        // Solicitudes pendientes para la bandeja del Coordinador.
        foreach ($enrollments->filter(fn ($e) => $sections->firstWhere('id', $e->section_id)->section === '01')->slice(400, 3) as $e) {
            $target = $sections->first(fn ($s) => $s->subject_id === $e->subject_id && $s->section === '02');
            DB::table('section_change_requests')->insert(['enrollment_id' => $e->id, 'target_section_id' => $target->id,
                'justification' => 'Solicito el grupo de la tarde por cruce con mi práctica profesional.', 'status' => 'pending'] + $this->stamps(now()->subDays(2)));
        }

        return DB::table('enrollments')->where('period_id', $this->period)->where('status', 'active')->get();
    }

    /**
     * @param  Collection<int, object>  $sections
     * @param  Collection<int, object>  $enrollments
     * @return array<int, array<int, int>> section_id => [unit ids]
     */
    private function seedContent(Collection $sections, Collection $enrollments): array
    {
        $units = [];
        $unitTitles = ['Fundamentos', 'Conceptos y técnicas', 'Aplicaciones', 'Proyecto integrador'];
        foreach ($sections as $section) {
            foreach ($unitTitles as $position => $title) {
                $unitId = DB::table('units')->insertGetId(['section_id' => $section->id, 'title' => 'Unidad '.($position + 1).' · '.$title, 'position' => $position] + $this->stamps(now()->parse('2026-07-27')));
                $units[$section->id][] = $unitId;
                $slug = Str::slug($section->code);
                $rows = [
                    ['resource_type' => 'file', 'title' => "Presentación de la unidad {$position}", 'file_path' => "cursos/{$slug}/u{$position}/presentacion.pdf", 'pdf_mode' => 'viewer', 'completion_mode' => 'manual'],
                    ['resource_type' => 'video', 'title' => 'Clase grabada', 'url' => 'https://www.youtube.com/watch?v='.Str::random(11), 'completion_mode' => 'manual'],
                    ['resource_type' => 'page', 'title' => 'Lectura guiada', 'html_content' => '<h2>'.e($title).'</h2><p>Lea con atención y responda las preguntas al final.</p><p><strong>Objetivo:</strong> comprender los conceptos clave de '.e($section->subject).'.</p>', 'completion_mode' => 'automatic', 'completion_rule' => json_encode(['type' => 'opened'])],
                ];
                if ($position === 0) {
                    $rows[] = ['resource_type' => 'folder', 'title' => 'Material complementario', 'completion_mode' => 'manual'];
                    $rows[] = ['resource_type' => 'link', 'title' => 'Documentación oficial', 'url' => 'https://www.php.net/manual/es/', 'completion_mode' => 'manual'];
                }
                foreach ($rows as $k => $row) {
                    $resourceId = DB::table('resources')->insertGetId($row + ['ulid' => (string) Str::ulid(), 'unit_id' => $unitId, 'description' => 'Recurso de '.$section->subject.'.', 'position' => $k] + $this->stamps(now()->parse('2026-07-27')->addWeeks($position * 4)));
                    if ($row['resource_type'] === 'folder') {
                        $this->insertRows('resource_files', collect(['guia-1.pdf', 'guia-2.pdf', 'datos.xlsx'])->map(fn ($n) => ['resource_id' => $resourceId, 'path' => "cursos/{$slug}/material/{$n}", 'name' => $n, 'bytes' => mt_rand(40_000, 900_000)] + $this->stamps())->all());
                    }
                }
            }
            // Un recurso en la papelera (borrado hace 5 días).
            DB::table('resources')->insert(['ulid' => (string) Str::ulid(), 'unit_id' => $units[$section->id][1], 'resource_type' => 'file', 'title' => 'Versión anterior de la guía',
                'file_path' => 'cursos/papelera/guia-vieja.pdf', 'pdf_mode' => 'download', 'completion_mode' => 'manual', 'position' => 9, 'deleted_at' => now()->subDays(5)] + $this->stamps(now()->subDays(20)));
        }

        $resourcesBySection = DB::table('resources')->join('units', 'units.id', '=', 'resources.unit_id')->whereNull('resources.deleted_at')
            ->where('resources.created_at', '<=', now())->get(['resources.id', 'units.section_id'])->groupBy('section_id');
        $logs = [];
        $progress = [];
        foreach ($enrollments as $enrollment) {
            $pool = ($resourcesBySection[$enrollment->section_id] ?? collect())->pluck('id')->shuffle()->take(mt_rand(4, 10));
            foreach ($pool as $resourceId) {
                $logs[] = ['resource_id' => $resourceId, 'enrollment_id' => $enrollment->id, 'event_type' => mt_rand(1, 4) === 1 ? 'download' : 'open',
                    'duration_seconds' => mt_rand(20, 1800), 'occurred_at' => now()->parse('2026-07-28')->addMinutes(mt_rand(0, 100_000))];
                if (mt_rand(1, 2) === 1) {
                    $progress[$resourceId.'-'.$enrollment->id] = ['resource_id' => $resourceId, 'enrollment_id' => $enrollment->id, 'origin' => mt_rand(1, 3) === 1 ? 'rule' : 'manual', 'completed_at' => now()->subDays(mt_rand(1, 60))];
                }
            }
        }
        $this->insertRows('resource_access_logs', $logs, 500);
        $this->insertRows('resource_progress', array_values($progress), 500);

        return $units;
    }

    /**
     * @param  Collection<int, object>  $sections
     * @param  Collection<int, object>  $enrollments
     * @param  array<int, array<int, int>>  $units
     */
    private function seedAssessmentsAndGrades(Collection $sections, Collection $enrollments, array $units): void
    {
        $templates = DB::table('grading_component_templates')->where('period_id', $this->period)->orderBy('number')->get();
        $banks = [];
        $items = [
            1 => [['Tarea 1', 'assignment', 40, '2026-08-03 07:00', '2026-08-24 23:59', 'strict'], ['Parcial 1', 'quiz', 60, '2026-09-01 08:00', '2026-09-01 10:00', null]],
            2 => [['Tarea 2', 'assignment', 40, '2026-09-21 07:00', '2026-10-16 23:59', 'grace_penalty'], ['Parcial 2', 'quiz', 60, '2026-10-20 08:00', '2026-10-20 10:00', null]],
            3 => [['Proyecto final', 'assignment', 50, '2026-10-26 07:00', '2026-11-30 23:59', 'grace'], ['Parcial 3', 'quiz', 50, '2026-12-01 08:00', '2026-12-01 10:00', null]],
        ];

        foreach ($sections as $section) {
            $teacher = $section->teacher;
            $bankKey = "{$teacher}-{$section->subject_id}";
            $banks[$bankKey] ??= $this->bank($teacher, $section);
            if (isset($section->coTeacher)) {
                DB::table('question_bank_shares')->insertOrIgnore(['bank_id' => $banks[$bankKey], 'user_id' => $section->coTeacher]);
            }

            $sectionEnrollments = $enrollments->where('section_id', $section->id)->values();
            foreach ($templates as $template) {
                $componentId = DB::table('grading_components')->insertGetId(['section_id' => $section->id, 'number' => $template->number, 'name' => $template->name,
                    'starts_on' => $template->starts_on, 'ends_on' => $template->ends_on, 'weight_percent' => $template->weight_percent] + $this->stamps(now()->parse('2026-07-27')));

                foreach ($items[$template->number] as [$title, $type, $weight, $opens, $closes, $policy]) {
                    $published = $template->number === 1;
                    // ADR-065: cada tarea con rúbrica tiene la suya; Tarea 2 se califica con nota directa.
                    $rubric = $type === 'assignment' && $title !== 'Tarea 2' ? $this->rubric($teacher, $title) : null;
                    $itemId = DB::table('grade_items')->insertGetId(['component_id' => $componentId, 'section_id' => $section->id, 'unit_id' => $units[$section->id][min(3, $template->number)],
                        'item_type' => $type, 'title' => $title, 'weight_percent' => $weight, 'is_published' => $published] + $this->stamps(now()->parse('2026-07-27')));
                    $assessmentId = DB::table('assessments')->insertGetId(['ulid' => (string) Str::ulid(), 'section_id' => $section->id, 'grade_item_id' => $itemId,
                        'assessment_type' => $type, 'title' => $title, 'instructions' => "Indicaciones de {$title} de {$section->subject}.",
                        'opens_at' => $opens, 'closes_at' => $closes, 'max_attempts' => 1, 'rubric_id' => $rubric['id'] ?? null,
                        'is_anonymous' => $title === 'Tarea 2'] + $this->stamps(now()->parse('2026-07-27')));

                    if ($type === 'assignment') {
                        DB::table('assignment_settings')->insert(['assessment_id' => $assessmentId, 'is_group' => $title === 'Proyecto final', 'late_policy' => $policy,
                            'grace_until' => $policy === 'strict' ? null : now()->parse($closes)->addDays(2), 'late_penalty_percent' => $policy === 'grace_penalty' ? 10 : 0,
                            'allowed_extensions' => json_encode(['pdf', 'docx', 'zip']), 'max_file_mb' => 10]);
                        if ($title === 'Proyecto final') {
                            $this->teams($assessmentId, $sectionEnrollments);
                        }
                        $this->submissions($assessmentId, $section, $itemId, $sectionEnrollments, $title, $opens, $closes, $rubric, $teacher);
                    } else {
                        DB::table('quiz_settings')->insert(['assessment_id' => $assessmentId, 'time_limit_minutes' => 60, 'autosave_seconds' => 30,
                            'navigation' => $title === 'Parcial 1' ? 'linear' : 'free', 'review_published' => $title === 'Parcial 1']);
                        DB::table('quiz_question_rules')->insert(['assessment_id' => $assessmentId, 'bank_id' => $banks[$bankKey], 'quantity' => 10] + $this->stamps());
                        if ($title === 'Parcial 1') {
                            $this->quizAttempts($assessmentId, $section, $itemId, $sectionEnrollments, $banks[$bankKey], $teacher);
                        }
                    }
                }
            }

            DB::table('grade_sheets')->insert(['section_id' => $section->id, 'status' => 'draft'] + $this->stamps());
        }
    }

    /**
     * @return array{id: int, criteria: array<int, array{id: int, max: float, levels: array<int, array{id: int, points: float}>}>}
     */
    private function rubric(int $teacher, string $title): array
    {
        $rubricId = DB::table('rubrics')->insertGetId(['owner_id' => $teacher, 'title' => $title] + $this->stamps());
        $criteria = [];
        foreach ([['Contenido y exactitud', 4], ['Formato y presentación', 3], ['Puntualidad y orden', 3]] as $position => [$name, $max]) {
            $criterionId = DB::table('rubric_criteria')->insertGetId(['rubric_id' => $rubricId, 'name' => $name, 'max_points' => $max, 'position' => $position] + $this->stamps());
            $levels = [];
            foreach ([['Excelente', 1.0], ['Bueno', 0.7], ['Regular', 0.4]] as [$level, $factor]) {
                $levels[] = ['id' => DB::table('rubric_levels')->insertGetId(['criterion_id' => $criterionId, 'name' => $level, 'points' => $max * $factor] + $this->stamps()), 'points' => $max * $factor];
            }
            $criteria[] = ['id' => $criterionId, 'max' => (float) $max, 'levels' => $levels];
        }

        return ['id' => $rubricId, 'criteria' => $criteria];
    }

    private function bank(int $teacher, object $section): int
    {
        $bankId = DB::table('question_banks')->insertGetId(['owner_id' => $teacher, 'subject_id' => $section->subject_id, 'name' => "Banco de {$section->subject}"] + $this->stamps());
        for ($q = 1; $q <= 15; $q++) {
            $type = $q <= 8 ? 'multiple_choice' : ($q <= 12 ? 'true_false' : ($q <= 14 ? 'short_answer' : 'essay'));
            $questionId = DB::table('questions')->insertGetId(['bank_id' => $bankId, 'question_type' => $type, 'statement' => "Pregunta {$q} de {$section->subject}: ".fake()->sentence(10),
                'topic' => 'Tema '.(($q % 3) + 1), 'difficulty' => ['easy', 'medium', 'hard'][$q % 3],
                'accepted_answers' => $type === 'short_answer' ? json_encode(['respuesta', 'resultado']) : null,
                'justification' => 'La respuesta correcta se explica en la unidad '.(($q % 4) + 1).'.'] + $this->stamps());
            if ($type === 'multiple_choice') {
                $correct = mt_rand(0, 3);
                $this->insertRows('question_options', collect(range(0, 3))->map(fn ($o) => ['question_id' => $questionId, 'text' => 'Opción '.chr(65 + $o), 'is_correct' => $o === $correct, 'position' => $o] + $this->stamps())->all());
            } elseif ($type === 'true_false') {
                $correct = (bool) mt_rand(0, 1);
                $this->insertRows('question_options', [['question_id' => $questionId, 'text' => 'Verdadero', 'is_correct' => $correct, 'position' => 0] + $this->stamps(), ['question_id' => $questionId, 'text' => 'Falso', 'is_correct' => ! $correct, 'position' => 1] + $this->stamps()]);
            }
        }

        return $bankId;
    }

    /**
     * @param  Collection<int, object>  $enrollments
     */
    private function teams(int $assessmentId, Collection $enrollments): void
    {
        foreach ($enrollments->chunk(4)->values() as $n => $members) {
            $teamId = DB::table('teams')->insertGetId(['assessment_id' => $assessmentId, 'name' => 'Equipo '.($n + 1)] + $this->stamps());
            $this->insertRows('team_members', $members->map(fn ($e) => ['team_id' => $teamId, 'enrollment_id' => $e->id])->values()->all());
        }
    }

    /**
     * @param  Collection<int, object>  $enrollments
     * @param  array{id: int, criteria: array<int, array{id: int, max: float, levels: array<int, array{id: int, points: float}>}>}|null  $rubric
     */
    private function submissions(int $assessmentId, object $section, int $itemId, Collection $enrollments, string $title, string $opens, string $closes, ?array $rubric, int $teacher): void
    {
        if ($title === 'Proyecto final') {
            return; // Aún no abre.
        }
        $isOpen = $title === 'Tarea 2';
        $share = $isOpen ? 0.4 : 0.9;
        $grades = [];
        foreach ($enrollments as $enrollment) {
            if (mt_rand(1, 1000) > $share * 1000) {
                continue;
            }
            $at = now()->parse($opens)->addMinutes(mt_rand(60, (int) now()->parse($opens)->diffInMinutes($isOpen ? now() : now()->parse($closes))));
            $submissionId = DB::table('submissions')->insertGetId(['ulid' => (string) Str::ulid(), 'assessment_id' => $assessmentId, 'section_id' => $section->id,
                'enrollment_id' => $enrollment->id, 'attempt_number' => 1, 'status' => 'submitted', 'is_late' => false, 'late_penalty_applied' => 0, 'submitted_at' => $at] + $this->stamps($at));
            DB::table('submission_files')->insert(['submission_id' => $submissionId, 'path' => "entregas/{$assessmentId}/{$submissionId}.pdf", 'original_name' => Str::slug($title).'.pdf', 'bytes' => mt_rand(80_000, 3_000_000)] + $this->stamps($at));
            DB::table('submission_events')->insert(['submission_id' => $submissionId, 'event_type' => 'submitted', 'actor_id' => $enrollment->student_id, 'occurred_at' => $at]);

            if ($isOpen) {
                continue; // Pendiente de calificar (bandeja del docente).
            }
            $skill = $this->skill[$enrollment->student_id] ?? 0.7;
            $points = 0.0;
            $scores = [];
            foreach ($rubric['criteria'] ?? [] as $criterion) {
                $level = $skill > 0.8 ? 0 : ($skill > 0.55 ? 1 : 2);
                $level = max(0, min(2, $level + (mt_rand(1, 5) === 1 ? 1 : 0)));
                $points += $criterion['levels'][$level]['points'];
                $scores[] = ['submission_id' => $submissionId, 'criterion_id' => $criterion['id'], 'level_id' => $criterion['levels'][$level]['id'], 'points' => $criterion['levels'][$level]['points']];
            }
            $this->insertRows('submission_rubric_scores', $scores);
            if (mt_rand(1, 3) === 1) {
                DB::table('submission_feedback')->insert(['submission_id' => $submissionId, 'comment' => 'Buen trabajo; revise la presentación de las conclusiones.', 'author_id' => $teacher] + $this->stamps($at->copy()->addDays(5)));
            }
            $grades[] = ['grade_item_id' => $itemId, 'enrollment_id' => $enrollment->id, 'section_id' => $section->id, 'score' => round(min(10, $points), 2), 'score_before_penalty' => round(min(10, $points), 2),
                'status' => 'published', 'comment' => null, 'graded_by' => $teacher, 'published_at' => now()->parse($closes)->addDays(7)] + $this->stamps(now()->parse($closes)->addDays(6));
        }
        $this->insertRows('grades', $grades);
    }

    /**
     * @param  Collection<int, object>  $enrollments
     */
    private function quizAttempts(int $assessmentId, object $section, int $itemId, Collection $enrollments, int $bankId, int $teacher): void
    {
        $questions = DB::table('questions')->where('bank_id', $bankId)->whereIn('question_type', ['multiple_choice', 'true_false'])->pluck('id');
        $options = DB::table('question_options')->whereIn('question_id', $questions)->get()->groupBy('question_id');
        $grades = [];
        foreach ($enrollments as $enrollment) {
            if (mt_rand(1, 100) > 95) {
                continue; // No se presentó: la nota faltante cuenta 0.
            }
            $skill = $this->skill[$enrollment->student_id] ?? 0.7;
            $start = now()->parse('2026-09-01 08:00')->addMinutes(mt_rand(0, 5));
            $attemptId = DB::table('quiz_attempts')->insertGetId(['ulid' => (string) Str::ulid(), 'assessment_id' => $assessmentId, 'enrollment_id' => $enrollment->id, 'attempt_number' => 1,
                'started_at' => $start, 'deadline_at' => $start->copy()->addMinutes(60), 'submitted_at' => $start->copy()->addMinutes(mt_rand(25, 58)), 'status' => 'graded'] + $this->stamps($start));
            $correct = 0;
            $answers = [];
            foreach ($questions->shuffle()->take(10)->values() as $position => $questionId) {
                $isRight = mt_rand(1, 1000) <= $skill * 1000;
                $option = $options[$questionId]->first(fn ($o) => (bool) $o->is_correct === $isRight) ?? $options[$questionId]->first();
                $correct += (int) $option->is_correct;
                $answers[] = ['attempt_id' => $attemptId, 'question_id' => $questionId, 'position' => $position, 'selected_option_id' => $option->id, 'answer_text' => null,
                    'score' => $option->is_correct ? 1 : 0, 'saved_at' => $start->copy()->addMinutes($position * 4 + 3)];
            }
            $this->insertRows('attempt_answers', $answers);
            DB::table('quiz_attempts')->where('id', $attemptId)->update(['score' => $correct]);
            $grades[] = ['grade_item_id' => $itemId, 'enrollment_id' => $enrollment->id, 'section_id' => $section->id, 'score' => $correct, 'score_before_penalty' => $correct,
                'status' => 'published', 'comment' => null, 'graded_by' => $teacher, 'published_at' => '2026-09-03 12:00'] + $this->stamps(now()->parse('2026-09-01 10:00'));
        }
        $this->insertRows('grades', $grades);
    }

    /**
     * @param  Collection<int, object>  $sections
     * @param  Collection<int, object>  $enrollments
     */
    private function seedAttendance(Collection $sections, Collection $enrollments): void
    {
        $area = (int) DB::table('attendance_areas')->value('id');
        $admin = $this->dev['admin'];
        DB::table('class_cancellations')->insert(['scope' => 'faculty', 'date' => '2026-09-15', 'kind' => 'cancellation', 'reason' => 'Día de la Independencia (asueto nacional).',
            'status' => 'approved', 'requested_by' => $admin, 'approved_by' => $admin] + $this->stamps(now()->parse('2026-09-01')));
        $devices = DB::table('student_devices')->where('status', 'active')->pluck('id', 'student_id');
        $components = DB::table('grading_components')->get(['id', 'section_id', 'number', 'starts_on', 'ends_on'])->groupBy('section_id');
        $schedules = DB::table('section_schedules')->where('period_id', $this->period)->get()->groupBy('section_id');

        foreach ($sections as $section) {
            $sectionEnrollments = $enrollments->where('section_id', $section->id)->values();
            $week = 0;
            for ($monday = now()->parse('2026-07-27'); $monday->lt(now()->startOfWeek()); $monday->addWeek(), $week++) {
                foreach ($schedules[$section->id] as $n => $schedule) {
                    $date = $monday->copy()->addDays($schedule->weekday - 1);
                    if ($date->toDateString() === '2026-09-15' || $date->gte(now()->startOfDay())) {
                        continue;
                    }
                    $virtual = $n === 0 && $week % 5 === 4;
                    $opened = Carbon::parse($date->toDateString().' '.$schedule->start_time)->addMinutes(mt_rand(0, 12));
                    $component = $components[$section->id]->first(fn ($c) => $date->toDateString() >= $c->starts_on && $date->toDateString() <= $c->ends_on);
                    $videoId = $virtual ? $this->videoSession($section, $opened) : null;
                    $sessionId = DB::table('attendance_sessions')->insertGetId(['ulid' => (string) Str::ulid(), 'section_id' => $section->id, 'grading_component_id' => $component?->id,
                        'area_id' => $virtual ? null : $area, 'video_session_id' => $videoId, 'session_date' => $date->toDateString(), 'modality' => $virtual ? 'virtual' : 'in_person',
                        'status' => 'closed', 'opened_at' => $opened, 'qr_validity_minutes' => 15, 'qr_rotation_seconds' => 10, 'late_after_minutes' => 15,
                        'closed_at' => $opened->copy()->addMinutes(15), 'opened_by' => $section->teacher] + $this->stamps($opened));
                    $this->records($sessionId, $section, $sectionEnrollments, $opened, $virtual, $devices, $videoId);
                }
            }
        }

        // Sesión abierta ahora mismo en PRN115-01 (docente.dev), con su QR rotativo.
        $open = $sections->first(fn ($s) => $s->code === 'PRN115' && $s->section === '01');
        $sessionId = DB::table('attendance_sessions')->insertGetId(['ulid' => (string) Str::ulid(), 'section_id' => $open->id, 'area_id' => $area, 'session_date' => now()->toDateString(),
            'modality' => 'in_person', 'status' => 'open', 'opened_at' => now()->subMinutes(3), 'qr_validity_minutes' => 15, 'qr_rotation_seconds' => 10, 'late_after_minutes' => 15,
            'opened_by' => $open->teacher] + $this->stamps(now()->subMinutes(3)));
        $this->insertRows('qr_tokens', collect([20, 10, 0])->map(fn ($ago) => ['session_id' => $sessionId, 'token_hash' => hash('sha256', Str::random(32)), 'expires_at' => now()->subSeconds($ago)->addSeconds(10), 'created_at' => now()->subSeconds($ago)])->all());
        DB::table('class_cancellations')->insert(['scope' => 'section', 'section_id' => $open->id, 'date' => now()->addDays(3)->toDateString(), 'kind' => 'modality_change', 'new_modality' => 'virtual',
            'reason' => 'Fumigación del edificio A.', 'status' => 'requested', 'requested_by' => $open->teacher] + $this->stamps());
    }

    private function videoSession(object $section, Carbon $start): int
    {
        $code = Str::lower(Str::random(3).'-'.Str::random(4).'-'.Str::random(3));
        $videoId = DB::table('video_sessions')->insertGetId(['section_id' => $section->id, 'google_event_id' => Str::random(26), 'meet_url' => "https://meet.google.com/{$code}",
            'topic' => "Clase virtual de {$section->subject}", 'starts_at' => $start, 'duration_minutes' => 100, 'status' => 'finished', 'recording_url' => 'https://drive.google.com/file/d/'.Str::random(28)] + $this->stamps($start->copy()->subDays(3)));
        DB::table('google_sync_refs')->insert(['entity_type' => 'video_session', 'entity_id' => $videoId, 'provider' => 'google_calendar', 'external_id' => Str::random(26), 'sync_status' => 'synced'] + $this->stamps());

        return $videoId;
    }

    /**
     * @param  Collection<int, object>  $enrollments
     * @param  Collection<int, int>  $devices
     */
    private function records(int $sessionId, object $section, Collection $enrollments, Carbon $opened, bool $virtual, Collection $devices, ?int $videoId): void
    {
        $rows = [];
        $attempts = [];
        $meetRows = [];
        foreach ($enrollments as $enrollment) {
            $rate = $this->attendanceRate[$enrollment->student_id] ?? 0.85;
            $present = mt_rand(1, 1000) <= $rate * 1000;
            $late = $present && mt_rand(1, 100) <= 8;
            $status = $present ? ($late ? 'late' : 'present') : 'absent';
            $method = 'manual';
            $extra = ['meet_percent' => null, 'distance_m' => null, 'accuracy_m' => null, 'inside_area' => null, 'device_id' => null];

            if ($virtual) {
                $method = 'meet';
                $minutes = $present ? mt_rand(72, 100) : mt_rand(0, 65);
                $extra['meet_percent'] = round($minutes, 2);
                $status = $minutes >= 70 ? 'present' : 'absent';
                if ($minutes > 0) {
                    $meetRows[] = ['name' => 'Estudiante '.$enrollment->student_id, 'email' => null, 'minutes' => $minutes, 'enrollment_id' => $enrollment->id, 'status' => 'matched'];
                }
            } elseif ($present) {
                $roll = mt_rand(1, 100);
                if (isset($devices[$enrollment->student_id]) && $roll <= 90) {
                    $method = 'qr';
                    $extra = ['meet_percent' => null, 'distance_m' => mt_rand(15, 380), 'accuracy_m' => mt_rand(5, 40), 'inside_area' => true, 'device_id' => $devices[$enrollment->student_id]];
                    $attempts[] = ['session_id' => $sessionId, 'student_id' => $enrollment->student_id, 'result' => 'ok', 'distance_m' => $extra['distance_m'], 'occurred_at' => $opened->copy()->addMinutes(mt_rand(1, 14))];
                } elseif ($roll <= 94) {
                    $method = 'code';
                }
            }

            $rows[] = ['session_id' => $sessionId, 'section_id' => $section->id, 'enrollment_id' => $enrollment->id, 'status' => $status, 'method' => $method,
                'recorded_at' => $opened->copy()->addMinutes($late ? mt_rand(16, 30) : mt_rand(1, 14)), 'corrected_by' => null, 'correction_reason' => null] + $extra + $this->stamps($opened);
        }

        // Intentos rechazados (diagnóstico de Soporte).
        foreach ($enrollments->random(min(2, $enrollments->count())) as $enrollment) {
            if (! $virtual) {
                $attempts[] = ['session_id' => $sessionId, 'student_id' => $enrollment->student_id, 'result' => ['token_expired', 'out_of_range', 'invalid_device', 'no_location'][mt_rand(0, 3)],
                    'distance_m' => mt_rand(400, 2500), 'occurred_at' => $opened->copy()->addMinutes(mt_rand(1, 14))];
            }
        }

        $this->insertRows('attendance_records', $rows);
        $this->insertRows('attendance_scan_attempts', $attempts);
        $this->insertRows('temporary_attendance_codes', collect($rows)->where('method', 'code')->map(fn ($r) => ['session_id' => $sessionId, 'enrollment_id' => $r['enrollment_id'],
            'code_hash' => hash('sha256', (string) mt_rand(100000, 999999)), 'expires_at' => $opened->copy()->addMinutes(30), 'used_at' => $r['recorded_at'], 'issued_by' => $section->teacher,
            'created_at' => $opened->copy()->addMinutes(2)])->values()->all());

        if ($virtual && $videoId) {
            $reportId = DB::table('meet_reports')->insertGetId(['video_session_id' => $videoId, 'file_path' => "meet/{$videoId}/asistencia.csv", 'uploaded_by' => $section->teacher,
                'confirmed_at' => $opened->copy()->addHours(3), 'is_current' => true] + $this->stamps($opened->copy()->addHours(2)));
            $meetRows[] = ['name' => 'Invitado (sin cuenta)', 'email' => null, 'minutes' => mt_rand(20, 90), 'enrollment_id' => null, 'status' => 'discarded'];
            $meetRows[] = ['name' => 'Usuario externo', 'email' => 'otro.correo@gmail.com', 'minutes' => mt_rand(40, 100), 'enrollment_id' => null, 'status' => 'unmatched'];
            $this->insertRows('meet_report_rows', array_map(fn ($r) => $r + ['meet_report_id' => $reportId], $meetRows));
        }

        // Correcciones y justificaciones de ejemplo.
        if (mt_rand(1, 6) === 1) {
            $record = DB::table('attendance_records')->where('session_id', $sessionId)->where('status', 'absent')->first();
            if ($record) {
                DB::table('attendance_records')->where('id', $record->id)->update(['status' => 'justified', 'method' => 'manual', 'corrected_by' => $section->teacher, 'correction_reason' => 'Constancia médica presentada.']);
                DB::table('attendance_justifications')->insert(['record_id' => $record->id, 'reason' => 'Cita médica en el ISSS.', 'file_path' => "justificaciones/{$record->id}.pdf",
                    'status' => 'accepted', 'reviewed_by' => $section->teacher, 'reviewed_at' => $opened->copy()->addDays(2)] + $this->stamps($opened->copy()->addDay()));
            }
        }
    }

    /**
     * @param  Collection<int, object>  $enrollments
     */
    private function seedAlerts(Collection $enrollments): void
    {
        $rates = DB::table('attendance_records')
            ->selectRaw("enrollment_id, sum(case when status in ('present','justified') then 1.0 when status = 'late' then 0.5 else 0 end) / count(*) as rate")
            ->groupBy('enrollment_id')->pluck('rate', 'enrollment_id');
        $components = DB::table('grading_components')->where('number', 2)->pluck('id', 'section_id');
        $rows = [];
        foreach ($enrollments as $enrollment) {
            $rate = (float) ($rates[$enrollment->id] ?? 1) * 100;
            foreach ([[85, 'attendance_85'], [78, 'attendance_78'], [75, 'attendance_75']] as [$threshold, $type]) {
                if ($rate < $threshold) {
                    $rows[] = ['enrollment_id' => $enrollment->id, 'alert_type' => $type, 'grading_component_id' => $components[$enrollment->section_id] ?? null,
                        'opened_at' => now()->subDays(mt_rand(1, 10)), 'notified_at' => now()->subDays(mt_rand(0, 1)), 'closed_at' => null];
                }
            }
        }

        $risk = DB::table('grades')->join('enrollments', 'enrollments.id', '=', 'grades.enrollment_id')->where('enrollments.period_id', $this->period)
            ->groupBy('grades.enrollment_id')->havingRaw('avg(grades.score) < 6')->pluck('grades.enrollment_id');
        foreach ($risk as $enrollmentId) {
            $rows[] = ['enrollment_id' => $enrollmentId, 'alert_type' => 'grade_risk', 'grading_component_id' => null, 'opened_at' => now()->subDays(5), 'notified_at' => now()->subDays(5), 'closed_at' => null];
        }
        $this->insertRows('academic_alerts', $rows);
    }

    /**
     * @param  Collection<int, object>  $sections
     * @param  Collection<int, object>  $enrollments
     */
    private function seedCommunication(Collection $sections, Collection $enrollments): void
    {
        $notifications = [];
        $deliveries = [];
        $add = function (int $userId, string $event, string $title, string $body, Carbon $at, bool $read) use (&$notifications, &$deliveries): void {
            $id = (string) Str::uuid();
            $notifications[] = ['id' => $id, 'type' => 'App\\Modules\\Comunicacion\\Notifications\\GenericNotification', 'notifiable_type' => 'App\\Models\\User', 'notifiable_id' => $userId,
                'event_code' => $event, 'dedup_key' => $event.':'.$userId.':'.count($notifications), 'data' => json_encode(['title' => $title, 'body' => $body]), 'read_at' => $read ? $at->copy()->addHours(2) : null] + $this->stamps($at);
            foreach (['database', 'mail'] as $channel) {
                $deliveries[] = ['notification_id' => $id, 'channel' => $channel, 'status' => 'sent', 'sent_at' => $at->copy()->addSeconds($channel === 'mail' ? mt_rand(20, 180) : mt_rand(1, 8)), 'error' => null] + $this->stamps($at);
            }
        };

        $sectionById = $sections->keyBy('id');
        foreach ($enrollments->groupBy('student_id') as $studentId => $studentEnrollments) {
            $first = $sectionById[$studentEnrollments->first()->section_id];
            $add((int) $studentId, 'grade.published', "Notas publicadas: Parcial 1 de {$first->subject}", 'Ya puede consultar su nota en la boleta.', now()->parse('2026-09-03 12:00'), true);
            $add((int) $studentId, 'deadline.upcoming', "Tarea 2 de {$first->subject} vence el 16/10", 'Le quedan pocos días para entregar.', now()->subDay(), false);
            $add((int) $studentId, 'resource.published', "Nuevo material en {$first->subject}", 'Se publicó la lectura guiada de la unidad 3.', now()->subDays(4), (bool) mt_rand(0, 1));
        }
        foreach (DB::table('academic_alerts')->join('enrollments', 'enrollments.id', '=', 'academic_alerts.enrollment_id')->get(['enrollments.student_id', 'academic_alerts.alert_type']) as $alert) {
            $add((int) $alert->student_id, 'academic_alert.opened', $alert->alert_type === 'grade_risk' ? 'Está en riesgo de reprobar una materia' : 'Su asistencia está cerca del límite',
                'Revise su panel para ver el detalle.', now()->subDays(2), false);
        }
        $this->insertRows('notifications', $notifications, 300);
        $this->insertRows('notification_deliveries', $deliveries, 300);

        $this->insertRows('notification_preferences', $enrollments->pluck('student_id')->unique()->take(20)->map(fn ($id) => ['user_id' => $id, 'event_code' => 'forum.reply', 'mail_enabled' => false])->values()->all());

        foreach ($sections as $section) {
            $threadId = DB::table('forum_threads')->insertGetId(['section_id' => $section->id, 'author_id' => $section->teacher, 'title' => 'Dudas de la Unidad 1', 'is_closed' => false, 'is_pinned' => true] + $this->stamps(now()->parse('2026-07-29')));
            $students = $enrollments->where('section_id', $section->id)->pluck('student_id')->shuffle()->take(3)->values();
            $previous = null;
            foreach ($students as $k => $studentId) {
                $postId = DB::table('forum_posts')->insertGetId(['thread_id' => $threadId, 'parent_post_id' => $k === 2 ? $previous : null, 'author_id' => $studentId,
                    'body_html' => '<p>'.e(fake()->sentence(14)).'</p>', 'is_hidden' => false] + $this->stamps(now()->parse('2026-07-30')->addDays($k)));
                $previous = $postId;
            }
            if ($previous) {
                DB::table('forum_posts')->insert(['thread_id' => $threadId, 'parent_post_id' => $previous, 'author_id' => $section->teacher, 'body_html' => '<p>Buena pregunta: revisen la lectura guiada de la unidad 1.</p>', 'is_hidden' => false] + $this->stamps(now()->parse('2026-08-03')));
            }
        }

        // Mensajes condicionales: «no entregaron la Tarea 1».
        foreach ($sections->take(3) as $section) {
            $item = DB::table('grade_items')->where('section_id', $section->id)->where('title', 'Tarea 1')->first();
            $assessment = DB::table('assessments')->where('grade_item_id', $item->id)->value('id');
            $submitted = DB::table('submissions')->where('assessment_id', $assessment)->pluck('enrollment_id');
            $missing = $enrollments->where('section_id', $section->id)->whereNotIn('id', $submitted)->pluck('student_id');
            $messageId = DB::table('mass_messages')->insertGetId(['grade_item_id' => $item->id, 'section_id' => $section->id, 'condition' => json_encode(['type' => 'not_submitted']),
                'subject' => 'Tarea 1 pendiente', 'body' => 'No hemos recibido su Tarea 1. Comuníquese con su docente.', 'sent_by' => $section->teacher] + $this->stamps(now()->parse('2026-08-25')));
            $this->insertRows('mass_message_recipients', $missing->map(fn ($id) => ['mass_message_id' => $messageId, 'user_id' => $id, 'delivered_at' => now()->parse('2026-08-25 08:05')])->values()->all());
        }
    }

    /**
     * @param  Collection<int, object>  $sections
     */
    private function seedCalendarAndIntegrations(Collection $sections): void
    {
        foreach ([['Primer parcial', '2026-09-01 08:00'], ['Segundo parcial', '2026-10-20 08:00'], ['Tercer parcial', '2026-12-01 08:00'], ['Cierre de notas', '2026-12-11 23:59']] as [$title, $at]) {
            $eventId = DB::table('period_calendar_events')->insertGetId(['period_id' => $this->period, 'title' => $title, 'starts_at' => $at] + $this->stamps(now()->parse('2026-07-20')));
            DB::table('google_sync_refs')->insert(['entity_type' => 'period_calendar_event', 'entity_id' => $eventId, 'provider' => 'google_calendar', 'external_id' => Str::random(26), 'sync_status' => 'synced'] + $this->stamps());
        }

        // Próxima clase virtual de docente.dev.
        $section = $sections->first(fn ($s) => $s->code === 'BAD115' && $s->section === '02');
        $videoId = DB::table('video_sessions')->insertGetId(['section_id' => $section->id, 'google_event_id' => Str::random(26), 'meet_url' => 'https://meet.google.com/abc-defg-hij',
            'topic' => 'Normalización (sesión virtual)', 'starts_at' => now()->addDays(4)->setTime(13, 0), 'duration_minutes' => 100, 'status' => 'scheduled'] + $this->stamps());
        DB::table('google_sync_refs')->insert(['entity_type' => 'video_session', 'entity_id' => $videoId, 'provider' => 'google_calendar', 'external_id' => Str::random(26), 'sync_status' => 'pending'] + $this->stamps());

        DB::table('integrations')->insert(['key' => 'google_workspace', 'settings' => Crypt::encryptString(json_encode(['client_id' => 'pendiente-ti-ues', 'allowed_domain' => 'ues.edu.sv']))] + $this->stamps());
        DB::table('scheduled_reports')->insert([
            ['user_id' => $this->dev['coordinador'], 'report_type' => 'attendance_by_section', 'filters' => json_encode(['career' => 'ISI']), 'frequency' => 'weekly', 'next_run_at' => now()->next('Monday')->setTime(7, 0)] + $this->stamps(),
            ['user_id' => $this->dev['jefe'], 'report_type' => 'teacher_kpis', 'filters' => json_encode(['department' => 'DIA']), 'frequency' => 'monthly', 'next_run_at' => now()->addMonth()->startOfMonth()->setTime(7, 0)] + $this->stamps(),
        ]);
        DB::table('exports')->insert([
            ['ulid' => (string) Str::ulid(), 'user_id' => $this->dev['docente'], 'export_type' => 'gradebook', 'path' => 'exportaciones/libro-notas-prn115-01.xlsx', 'status' => 'ready'] + $this->stamps(now()->subDays(2)),
            ['ulid' => (string) Str::ulid(), 'user_id' => $this->dev['coordinador'], 'export_type' => 'attendance_report', 'path' => null, 'status' => 'queued'] + $this->stamps(),
        ]);
    }

    /**
     * Período 2026-I cerrado: secciones, matrículas, actas validadas, cierres departamentales y resultados oficiales.
     */
    private function seedHistory(): void
    {
        $subjects = DB::table('subjects')->get()->keyBy('code');
        $teachers = DB::table('teaching_assignments')->distinct()->pluck('teacher_id')->values();
        $students = DB::table('student_careers')->where('current_cycle', '>', 1)->get(['student_id', 'current_cycle']);
        $heads = DB::table('department_heads')->whereNull('valid_to')->pluck('user_id', 'department_id');
        $policy = json_encode(['passing_score' => 6.0, 'grade_rounding' => ['decimals' => 1, 'mode' => 'half_up'], 'missing_grade_policy' => 'zero', 'confirmed' => false]);

        $k = 0;
        foreach (collect(InstitutionSeeder::SUBJECTS)->filter(fn ($s) => in_array($s[1], [2, 4, 6, 8], true)) as $code => [, $cycle]) {
            $subject = $subjects[$code];
            $sectionId = DB::table('sections')->insertGetId(['ulid' => (string) Str::ulid(), 'period_id' => $this->pastPeriod, 'subject_id' => $subject->id, 'code' => '01',
                'min_capacity' => 10, 'max_capacity' => 80, 'status' => 'closed'] + $this->stamps(now()->parse('2026-01-20')));
            DB::table('teaching_assignments')->insert(['section_id' => $sectionId, 'teacher_id' => $teachers[$k++ % $teachers->count()], 'is_responsible' => true,
                'started_at' => '2026-01-20', 'ended_at' => '2026-06-19'] + $this->stamps(now()->parse('2026-01-20')));

            $rows = $students->where('current_cycle', $cycle + 1)->map(fn ($s) => ['student_id' => $s->student_id, 'section_id' => $sectionId, 'period_id' => $this->pastPeriod,
                'subject_id' => $subject->id, 'status' => 'active', 'enrolled_via' => 'key', 'enrolled_by' => $s->student_id] + $this->stamps(now()->parse('2026-02-02')))->values()->all();
            $this->insertRows('enrollments', $rows);

            $results = DB::table('enrollments')->where('section_id', $sectionId)->pluck('id')->map(function ($id) use ($policy) {
                $score = round(min(10, max(0, $this->gauss(7.1, 1.5))), 1);

                return ['enrollment_id' => $id, 'final_score' => $score, 'result' => $score >= 6 ? 'passed' : 'failed', 'policy_snapshot' => $policy, 'frozen_at' => '2026-06-26 18:00'];
            })->all();
            $this->insertRows('enrollment_results', $results);

            DB::table('grade_sheets')->insert(['section_id' => $sectionId, 'status' => 'validated', 'published_by' => $teachers[($k - 1) % $teachers->count()], 'published_at' => '2026-06-20 10:00',
                'validated_by' => $this->dev['coordinador'], 'validated_at' => '2026-06-23 15:00'] + $this->stamps(now()->parse('2026-06-20')));
        }

        foreach ($heads as $departmentId => $headId) {
            DB::table('department_closures')->insert(['period_id' => $this->pastPeriod, 'department_id' => $departmentId, 'authorized_by' => $headId, 'authorized_at' => '2026-06-25 09:00'] + $this->stamps(now()->parse('2026-06-25')));
        }

        DB::table('academic_periods')->where('id', $this->pastPeriod)->update(['status' => 'closed', 'updated_at' => '2026-06-26 18:00']);

        // Copia de contenido de PRN215 (2026-I) a PRN115-01 (2026-II) hecha por docente.dev.
        $from = DB::table('sections')->where('period_id', $this->pastPeriod)->where('subject_id', $subjects['PRN215']->id)->value('id');
        $to = DB::table('sections')->where('period_id', $this->period)->where('subject_id', $subjects['PRN115']->id)->where('code', '01')->value('id');
        DB::table('content_copies')->insert(['source_section_id' => $from, 'target_section_id' => $to, 'copied_by' => $this->dev['docente']] + $this->stamps(now()->parse('2026-07-24')));
    }

    /**
     * En PostgreSQL la auditoría la escriben los triggers; en SQLite se siembran ejemplos para ver el diseño.
     */
    private function seedAuditSamples(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return;
        }

        $department = DB::table('departments')->where('code', 'DIA')->value('id');
        $grades = DB::table('grades')->limit(30)->get(['id', 'score']);
        $rows = $grades->map(fn ($g) => ['occurred_at' => now()->subDays(mt_rand(1, 30)), 'actor_user_id' => $this->dev['docente'], 'department_id' => $department, 'ip_address' => '10.10.4.'.mt_rand(2, 250),
            'request_id' => (string) Str::uuid(), 'event_type' => 'grade.corrected', 'entity_type' => 'grades', 'entity_id' => $g->id, 'action' => 'update',
            'old_values' => json_encode(['score' => max(0, $g->score - 1)]), 'new_values' => json_encode(['score' => $g->score]), 'reason' => 'Revisión de examen solicitada por el estudiante.'])->all();
        $rows[] = ['occurred_at' => now()->subDays(40), 'actor_user_id' => $this->dev['admin'], 'department_id' => null, 'ip_address' => '10.10.1.10', 'request_id' => (string) Str::uuid(),
            'event_type' => 'role.assigned', 'entity_type' => 'model_has_roles', 'entity_id' => null, 'action' => 'insert', 'old_values' => null, 'new_values' => json_encode(['role' => 'coordinador']), 'reason' => null];
        $this->insertRows('audit_logs', $rows);
    }

    private function shift(string $time, int $shift): string
    {
        return $shift === 0 ? $time : Carbon::parse($time)->addHours(6)->format('H:i');
    }

    private function gauss(float $mean, float $sd): float
    {
        $u = max(1e-9, mt_rand() / mt_getrandmax());
        $v = mt_rand() / mt_getrandmax();

        return $mean + $sd * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }
}
