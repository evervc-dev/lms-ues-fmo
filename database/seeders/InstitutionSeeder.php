<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\InsertsRows;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Estructura institucional de ejemplo (datos inventados hasta recibir el catálogo real de la UES).
 * Departamentos, carreras, planes 98 y 25 de ISI, 50 materias en 10 ciclos, aulas, área del campus
 * y tres períodos: 2026-I (histórico), 2026-II (activo) y 2027-I (en planificación).
 */
class InstitutionSeeder extends Seeder
{
    use InsertsRows;

    /**
     * Materias de ISI: código => [nombre, ciclo, departamento].
     *
     * @var array<string, array{0: string, 1: int, 2: string}>
     */
    public const SUBJECTS = [
        'MAT115' => ['Matemática I', 1, 'DCNM'], 'PRN115' => ['Programación I', 1, 'DIA'], 'ISI115' => ['Introducción a la Informática', 1, 'DIA'],
        'IDI115' => ['Idioma Inglés I', 1, 'DCE'], 'PSI115' => ['Psicología Social', 1, 'DCE'],
        'MAT215' => ['Matemática II', 2, 'DCNM'], 'PRN215' => ['Programación II', 2, 'DIA'], 'FIR115' => ['Física I', 2, 'DCNM'],
        'ADS115' => ['Administración General', 2, 'DCE'], 'IDI215' => ['Idioma Inglés II', 2, 'DCE'],
        'MAT315' => ['Matemática III', 3, 'DCNM'], 'EST115' => ['Estadística I', 3, 'DCNM'], 'ESD115' => ['Estructuras de Datos', 3, 'DIA'],
        'FIR215' => ['Física II', 3, 'DCNM'], 'CON115' => ['Contabilidad General', 3, 'DCE'],
        'MAT415' => ['Matemática IV', 4, 'DCNM'], 'MEP115' => ['Métodos Probabilísticos', 4, 'DCNM'], 'PRN315' => ['Programación III', 4, 'DIA'],
        'ARC115' => ['Arquitectura de Computadoras', 4, 'DIA'], 'ECO115' => ['Economía', 4, 'DCE'],
        'ANS115' => ['Análisis Numérico', 5, 'DCNM'], 'BAD115' => ['Bases de Datos I', 5, 'DIA'], 'SIO115' => ['Sistemas Operativos', 5, 'DIA'],
        'DSI115' => ['Diseño de Sistemas I', 5, 'DIA'], 'HDP115' => ['Herramientas de Productividad', 5, 'DIA'],
        'COS115' => ['Comunicaciones I', 6, 'DIA'], 'BAD215' => ['Bases de Datos II', 6, 'DIA'], 'DSI215' => ['Diseño de Sistemas II', 6, 'DIA'],
        'TSI115' => ['Teoría de Sistemas', 6, 'DIA'], 'IEC115' => ['Ingeniería Económica', 6, 'DCE'],
        'RED115' => ['Redes de Computadoras', 7, 'DIA'], 'IGF115' => ['Ingeniería de Software', 7, 'DIA'], 'PDM115' => ['Programación para Dispositivos Móviles', 7, 'DIA'],
        'ACC115' => ['Administración de Centros de Cómputo', 7, 'DIA'], 'SGG115' => ['Sistemas de Gestión de Calidad', 7, 'DCE'],
        'SIC115' => ['Seguridad Informática', 8, 'DIA'], 'IAI115' => ['Inteligencia Artificial', 8, 'DIA'], 'DPW115' => ['Desarrollo Web', 8, 'DIA'],
        'PYE115' => ['Proyectos de Ingeniería', 8, 'DIA'], 'LEG115' => ['Legislación Informática', 8, 'DCE'],
        'TPI115' => ['Técnicas de Programación para Internet', 9, 'DIA'], 'SGI115' => ['Sistemas de Información Gerencial', 9, 'DIA'],
        'CLD115' => ['Computación en la Nube', 9, 'DIA'], 'ETI115' => ['Ética Profesional', 9, 'DCE'], 'OPT115' => ['Optativa I: Ciencia de Datos', 9, 'DIA'],
        'TDG115' => ['Trabajo de Graduación I', 10, 'DIA'], 'OPT215' => ['Optativa II: Ciberseguridad Aplicada', 10, 'DIA'],
        'ERP115' => ['Sistemas Empresariales', 10, 'DIA'], 'GPR115' => ['Gestión de Proyectos', 10, 'DCE'], 'SEM115' => ['Seminario de Investigación', 10, 'DIA'],
    ];

    public function run(): void
    {
        $departments = [
            'DIA' => 'Departamento de Ingeniería y Arquitectura',
            'DCNM' => 'Departamento de Ciencias Naturales y Matemática',
            'DCE' => 'Departamento de Ciencias Económicas',
        ];

        foreach ($departments as $code => $name) {
            DB::table('departments')->insert(['code' => $code, 'name' => $name] + $this->stamps());
        }

        $dept = DB::table('departments')->pluck('id', 'code');

        $careers = [
            ['ISI', 'Ingeniería de Sistemas Informáticos', 'DIA', ['Plan 98', 'Plan 25']],
            ['ICI', 'Ingeniería Civil', 'DIA', ['Plan 08']],
            ['LMA', 'Licenciatura en Matemática', 'DCNM', ['Plan 07']],
            ['LAE', 'Licenciatura en Administración de Empresas', 'DCE', ['Plan 13']],
        ];

        foreach ($careers as [$code, $name, $department, $plans]) {
            $careerId = DB::table('careers')->insertGetId(['department_id' => $dept[$department], 'code' => $code, 'name' => $name] + $this->stamps());

            foreach ($plans as $plan) {
                DB::table('study_plans')->insert(['career_id' => $careerId, 'name' => $plan] + $this->stamps());
            }
        }

        foreach (self::SUBJECTS as $code => [$name, $cycle, $department]) {
            DB::table('subjects')->insert([
                'code' => $code,
                'name' => $name,
                'description' => "Asignatura del ciclo {$cycle} de Ingeniería de Sistemas Informáticos.",
                'credits' => in_array($code, ['TDG115', 'SEM115'], true) ? 5 : 4,
                'department_id' => $dept[$department],
            ] + $this->stamps());
        }

        $subjects = DB::table('subjects')->pluck('id', 'code');
        $isiPlans = DB::table('study_plans')->join('careers', 'careers.id', '=', 'study_plans.career_id')
            ->where('careers.code', 'ISI')->pluck('study_plans.id', 'study_plans.name');

        $rows = [];
        foreach ($isiPlans as $planId) {
            foreach (self::SUBJECTS as $code => [, $cycle]) {
                $rows[] = ['study_plan_id' => $planId, 'subject_id' => $subjects[$code], 'cycle' => $cycle];
            }
        }
        $this->insertRows('career_subjects', $rows);

        // Prerrequisitos: cada materia "II/III/IV" requiere la anterior (se registran, no se validan: ADR-030).
        $prerequisites = ['MAT215' => 'MAT115', 'MAT315' => 'MAT215', 'MAT415' => 'MAT315', 'PRN215' => 'PRN115', 'PRN315' => 'PRN215',
            'ESD115' => 'PRN215', 'BAD215' => 'BAD115', 'DSI215' => 'DSI115', 'FIR215' => 'FIR115', 'IDI215' => 'IDI115', 'TDG115' => 'PYE115'];
        $this->insertRows('subject_prerequisites', collect($prerequisites)->map(fn ($pre, $code) => [
            'subject_id' => $subjects[$code], 'prerequisite_id' => $subjects[$pre],
        ])->values()->all());

        $classrooms = [];
        foreach (['A-101', 'A-102', 'A-103', 'A-104', 'A-105', 'B-201', 'B-202', 'B-203', 'B-204', 'B-205', 'LAB-1', 'LAB-2', 'LAB-3', 'AUD-1'] as $code) {
            $classrooms[] = ['code' => $code, 'capacity' => str_starts_with($code, 'LAB') ? 30 : (str_starts_with($code, 'AUD') ? 120 : 45)] + $this->stamps();
        }
        $this->insertRows('classrooms', $classrooms);

        // Por defecto el campus; LMS_AREA_* (config/lms.php) la mueve para probar el QR desde otro lugar.
        $area = config('lms.area_asistencia');
        DB::table('attendance_areas')->insert([
            'name' => $area['nombre'],
            'area_type' => 'circle',
            'center_lat' => $area['lat'],
            'center_lng' => $area['lng'],
            'radius_m' => $area['radio'],
            'is_active' => true,
        ] + $this->stamps());

        $periods = [
            ['2026-I', '2026-02-02', '2026-06-19', 'planned', 'ready'],
            ['2026-II', '2026-07-27', '2026-12-11', 'active', 'ready'],
            ['2027-I', '2027-02-01', '2027-06-18', 'planned', 'planning'],
        ];

        foreach ($periods as [$name, $start, $end, $status, $stage]) {
            $periodId = DB::table('academic_periods')->insertGetId([
                'name' => $name, 'start_date' => $start, 'end_date' => $end, 'status' => $status, 'preparation_stage' => $stage,
            ] + $this->stamps());

            // Tres cómputos por período: 30 %, 30 % y 40 %.
            $from = now()->parse($start);
            foreach ([[1, 30], [2, 30], [3, 40]] as [$number, $weight]) {
                DB::table('grading_component_templates')->insert([
                    'period_id' => $periodId,
                    'number' => $number,
                    'name' => "Cómputo {$number}",
                    'starts_on' => $from->copy()->addWeeks(($number - 1) * 6)->toDateString(),
                    'ends_on' => $number === 3 ? $end : $from->copy()->addWeeks($number * 6)->subDay()->toDateString(),
                    'weight_percent' => $weight,
                ] + $this->stamps());
            }
        }
    }
}
