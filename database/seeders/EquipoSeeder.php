<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\InsertsRows;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Perfil de siembra «equipo» (LMS_SEED_PERFIL=equipo, solo fuera de producción): la estructura institucional de
 * siempre, pero sin personas inventadas. Las cuentas son las de los integrantes del equipo
 * (database/seeders/data/testers.php) con su correo institucional real, para que entren con Google, más
 * admin.dev@ues.edu.sv y, si el .env la define, la cuenta de emergencia (ADR-007).
 *
 * Los roles se reparten al azar con una semilla fija (LMS_SEED_SEMILLA): sembrar de nuevo da el mismo reparto y
 * cambiar la semilla lo baraja. 1 Jefe de DIA, 1 Coordinador de ISI y 3 docentes (los cinco con perfil docente
 * e imparten grupos) y el resto estudiantes, que solo tienen ese rol (ADR-081). No se inventan notas ni asistencia:
 * se prueban desde cero. Al terminar imprime el reparto.
 */
class EquipoSeeder extends Seeder
{
    use InsertsRows;

    /** Ciclos con grupos en el período en curso (impar: 2026-II) y su plan. */
    private const CICLOS = [1 => 'Plan 25', 3 => 'Plan 25', 5 => 'Plan 98', 7 => 'Plan 98', 9 => 'Plan 98'];

    /** Franja (índice de materia en el ciclo) => [[día, inicio, fin], ...], como en DemoAcademicSeeder. */
    private const FRANJAS = [
        0 => [[1, '07:00', '08:40'], [3, '07:00', '08:40']],
        1 => [[1, '08:50', '10:30'], [3, '08:50', '10:30']],
        2 => [[2, '07:00', '08:40'], [4, '07:00', '08:40']],
        3 => [[2, '08:50', '10:30'], [4, '08:50', '10:30']],
        4 => [[5, '07:00', '08:40'], [5, '08:50', '10:30']],
    ];

    /** Grupos teóricos que imparte cada docente del equipo; los demás quedan sin docente, para probar la asignación. */
    private const GRUPOS_POR_DOCENTE = 2;

    public function run(): void
    {
        $semilla = (int) config('lms.seed_semilla', 2026);

        $departamentos = DB::table('departments')->pluck('id', 'code');
        $carreraIsi = (int) DB::table('careers')->where('code', 'ISI')->value('id');
        $planes = DB::table('study_plans')->where('career_id', $carreraIsi)->pluck('id', 'name');
        $periodo = (int) DB::table('academic_periods')->where('name', '2026-II')->value('id');

        $admin = $this->cuenta('admin.dev@ues.edu.sv', 'Administración', 'Desarrollo');
        $this->darRol($admin, 'administrador');
        $this->cuentaDeEmergencia();

        // Reparto al azar, reproducible con la semilla.
        $equipo = require database_path('seeders/data/testers.php');
        ['jefe' => $jefe, 'coordinador' => $coordinador, 'docentes' => $docentes, 'estudiantes' => $estudiantes] = self::reparto(array_keys($equipo), $semilla);

        $ids = [];
        foreach ($equipo as $due => $persona) {
            $ids[$due] = $this->cuenta("{$due}@ues.edu.sv", $persona['nombre'], $persona['apellido']);
        }

        // Jefe, Coordinador y docentes: todos con perfil docente en DIA e imparten grupos (ADR-081 deja combinar
        // Docente con Jefe y con Coordinador).
        foreach ([$jefe, $coordinador, ...$docentes] as $due) {
            $this->darRol($ids[$due], 'docente');
            DB::table('teacher_profiles')->insert(['user_id' => $ids[$due], 'department_id' => $departamentos['DIA']] + $this->stamps());
        }
        $this->darRol($ids[$jefe], 'jefe_departamento');
        DB::table('department_heads')->insert(['user_id' => $ids[$jefe], 'department_id' => $departamentos['DIA'], 'valid_from' => '2025-01-01'] + $this->stamps());
        $this->darRol($ids[$coordinador], 'coordinador');
        DB::table('career_coordinators')->insert(['user_id' => $ids[$coordinador], 'career_id' => $carreraIsi, 'valid_from' => '2025-01-01'] + $this->stamps());

        // Estudiantes: carné = DUE (el del correo), repartidos entre los ciclos del período.
        $ciclos = array_keys(self::CICLOS);
        $cicloDe = [];
        foreach (array_values($estudiantes) as $i => $due) {
            $ciclo = $ciclos[$i % count($ciclos)];
            $cicloDe[$due] = $ciclo;
            $this->darRol($ids[$due], 'estudiante');
            DB::table('student_profiles')->insert(['user_id' => $ids[$due], 'student_code' => strtoupper($due)] + $this->stamps());
            DB::table('student_careers')->insert(['student_id' => $ids[$due], 'career_id' => $carreraIsi, 'study_plan_id' => $planes[self::CICLOS[$ciclo]],
                'current_cycle' => $ciclo, 'status' => 'active'] + $this->stamps());
        }

        $grupos = $this->grupos($periodo, array_map(fn ($due) => $ids[$due], [$jefe, $coordinador, ...$docentes]));
        $this->claves($periodo);
        $this->plazos($periodo, $admin);
        $this->matriculas($periodo, $grupos, array_map(fn ($due) => [$ids[$due], $cicloDe[$due]], $estudiantes));

        $this->imprimirReparto($semilla, $equipo, $jefe, $coordinador, $docentes, $estudiantes, $cicloDe);
    }

    /**
     * Grupos 01 y 02 de cada materia de los ciclos del período, con horario y aula. Cada docente del equipo queda
     * como responsable de GRUPOS_POR_DOCENTE grupos de franjas distintas; el resto queda sin docente.
     *
     * @param  array<int, int>  $docentes
     * @return list<object>
     */
    private function grupos(int $periodo, array $docentes): array
    {
        $materias = DB::table('subjects')->get()->keyBy('code');
        $aulas = DB::table('classrooms')->where('code', 'not like', 'LAB%')->where('code', 'not like', 'AUD%')->orderBy('id')->pluck('id')->values();
        $alta = Carbon::parse('2026-07-01');
        $grupos = [];
        foreach (array_keys(self::CICLOS) as $n => $ciclo) {
            $codigos = collect(InstitutionSeeder::SUBJECTS)->filter(fn ($s) => $s[1] === $ciclo)->keys()->values();
            foreach ([0, 1] as $turno) {
                foreach ($codigos as $i => $codigo) {
                    $grupo = str_pad((string) ($turno + 1), 2, '0', STR_PAD_LEFT);
                    $id = DB::table('sections')->insertGetId(['ulid' => (string) Str::ulid(), 'period_id' => $periodo, 'subject_id' => $materias[$codigo]->id,
                        'code' => $grupo, 'min_capacity' => 10, 'max_capacity' => 40, 'status' => 'open'] + $this->stamps($alta));
                    foreach (self::FRANJAS[$i] as [$dia, $inicio, $fin]) {
                        DB::table('section_schedules')->insert(['section_id' => $id, 'period_id' => $periodo, 'classroom_id' => $aulas[$n * 2 + $turno],
                            'weekday' => $dia, 'start_time' => $this->correr($inicio, $turno), 'end_time' => $this->correr($fin, $turno)] + $this->stamps($alta));
                    }
                    $grupos[] = (object) ['id' => $id, 'subject_id' => $materias[$codigo]->id, 'ciclo' => $ciclo, 'grupo' => $grupo, 'franja' => "{$i}-{$turno}"];
                }
            }
        }

        // Cada docente, en franjas distintas (no choca consigo mismo), empezando por los grupos 01 de ciclos distintos.
        $libres = collect($grupos)->where('grupo', '01')->values();
        $conDocente = [];
        foreach ($docentes as $k => $docente) {
            $ocupadas = [];
            $asignados = 0;
            foreach ($libres->slice($k)->concat($libres->take($k)) as $g) {
                if ($asignados >= self::GRUPOS_POR_DOCENTE) {
                    break;
                }
                if (isset($conDocente[$g->id]) || isset($ocupadas[$g->franja])) {
                    continue;
                }
                DB::table('teaching_assignments')->insert(['section_id' => $g->id, 'teacher_id' => $docente, 'is_responsible' => true, 'started_at' => '2026-07-01'] + $this->stamps());
                $conDocente[$g->id] = true;
                $ocupadas[$g->franja] = true;
                $asignados++;
            }
        }

        return $grupos;
    }

    private function claves(int $periodo): void
    {
        $filas = DB::table('subjects')->get(['id', 'code'])->map(fn ($m) => ['subject_id' => $m->id, 'period_id' => $periodo,
            'key_hash' => Hash::make('ISI-'.$m->code, ['rounds' => 4]), 'valid_until' => null, 'is_active' => true] + $this->stamps())->all();
        $this->insertRows('enrollment_keys', $filas);
    }

    /**
     * Fecha límite de inscripción hasta el fin del período (RF29, ADR-051), para poder inscribir hoy.
     */
    private function plazos(int $periodo, int $admin): void
    {
        $fin = DB::table('academic_periods')->where('id', $periodo)->value('end_date');
        $this->insertRows('enrollment_deadlines', DB::table('departments')->pluck('id')->map(fn ($d) => ['period_id' => $periodo, 'department_id' => $d,
            'deadline' => $fin, 'set_by' => $admin] + $this->stamps())->all());
    }

    /**
     * Cada estudiante, en el grupo 01 de las materias de su ciclo. Sin notas ni asistencia.
     *
     * @param  list<object>  $grupos
     * @param  list<array{0: int, 1: int}>  $estudiantes  [usuario, ciclo]
     */
    private function matriculas(int $periodo, array $grupos, array $estudiantes): void
    {
        $filas = [];
        foreach ($estudiantes as [$estudiante, $ciclo]) {
            foreach (collect($grupos)->where('ciclo', $ciclo)->where('grupo', '01') as $g) {
                $filas[] = ['student_id' => $estudiante, 'section_id' => $g->id, 'period_id' => $periodo, 'subject_id' => $g->subject_id,
                    'status' => 'active', 'enrolled_via' => 'key', 'enrolled_by' => $estudiante] + $this->stamps(Carbon::parse('2026-07-27'));
            }
        }
        $this->insertRows('enrollments', $filas);
        $this->insertRows('enrollment_changes', DB::table('enrollments')->where('period_id', $periodo)->get()->map(fn ($e) => [
            'enrollment_id' => $e->id, 'change_type' => 'enrolled', 'reason' => null, 'actor_id' => $e->enrolled_by, 'occurred_at' => $e->created_at,
        ])->all());
    }

    /**
     * Cuenta de emergencia (ADR-007), solo si el .env trae su correo y su contraseña; nunca van en el repositorio.
     * Sin el segundo factor, que ADR-007 deja pendiente.
     */
    private function cuentaDeEmergencia(): void
    {
        $correo = (string) config('lms.emergencia.correo', '');
        $clave = (string) config('lms.emergencia.clave', '');
        if ($correo === '' || $clave === '') {
            return;
        }
        $id = DB::table('users')->insertGetId(['institutional_email' => strtolower($correo), 'first_name' => 'Cuenta', 'last_name' => 'de Emergencia',
            'is_active' => true, 'is_emergency' => true, 'password' => Hash::make($clave)] + $this->stamps());
        $this->darRol($id, 'administrador');
    }

    private function cuenta(string $correo, string $nombre, string $apellido): int
    {
        return DB::table('users')->insertGetId(['institutional_email' => $correo, 'first_name' => $nombre, 'last_name' => $apellido, 'is_active' => true] + $this->stamps());
    }

    private function darRol(int $usuario, string $rol): void
    {
        DB::table('model_has_roles')->insert(['role_id' => Role::findByName($rol, 'web')->id, 'model_type' => User::class, 'model_id' => $usuario]);
    }

    /**
     * Reparto de roles: Fisher-Yates con mt_rand y la semilla, así la misma semilla da siempre el mismo reparto.
     *
     * @param  array<int, string>  $dues
     * @return array{jefe: string, coordinador: string, docentes: array<int, string>, estudiantes: array<int, string>}
     */
    public static function reparto(array $dues, int $semilla): array
    {
        mt_srand($semilla);
        $lista = array_values($dues);
        for ($i = count($lista) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$lista[$i], $lista[$j]] = [$lista[$j], $lista[$i]];
        }

        return ['jefe' => $lista[0], 'coordinador' => $lista[1], 'docentes' => array_slice($lista, 2, 3), 'estudiantes' => array_slice($lista, 5)];
    }

    private function correr(string $hora, int $turno): string
    {
        return $turno === 0 ? $hora : Carbon::createFromFormat('H:i', $hora)->addHours(6)->format('H:i');
    }

    /**
     * @param  array<string, array{nombre: string, apellido: string}>  $equipo
     * @param  array<int, string>  $docentes
     * @param  array<int, string>  $estudiantes
     * @param  array<string, int>  $cicloDe
     */
    private function imprimirReparto(int $semilla, array $equipo, string $jefe, string $coordinador, array $docentes, array $estudiantes, array $cicloDe): void
    {
        // Sin consola (por ejemplo, llamado desde una prueba) no hay dónde imprimir: Seeder::$command queda en null.
        /** @var Command|null $consola */
        $consola = $this->command;
        if ($consola === null) {
            return;
        }
        $nombre = fn (string $due) => $equipo[$due]['nombre'].' '.$equipo[$due]['apellido'];
        $filas = [[__('Jefe de Departamento (DIA) y docente'), $jefe, $nombre($jefe)], [__('Coordinador (ISI) y docente'), $coordinador, $nombre($coordinador)]];
        foreach ($docentes as $due) {
            $filas[] = [__('Docente'), $due, $nombre($due)];
        }
        foreach ($estudiantes as $due) {
            $filas[] = [__('Estudiante · ciclo :c', ['c' => $cicloDe[$due]]), $due, $nombre($due)];
        }
        $consola->info(__('Reparto del equipo (semilla :s; cambie LMS_SEED_SEMILLA para barajar):', ['s' => $semilla]));
        $consola->table([__('Rol'), 'DUE', __('Nombre')], $filas);
    }
}
