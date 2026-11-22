<?php

namespace Database\Seeders\Escenarios;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * El andamio que comparten todos los escenarios: período, materia propia, sección, horario, el plantel
 * (docente, jefe, coordinador, administrador) y las matrículas.
 *
 * Dos reglas gobiernan este archivo:
 *
 * 1. **Cada escenario estrena su propia materia y su propia sección.** Es lo que lo hace repetible: el
 *    esquema tiene un único índice `enrollments_single_active_unique` por (estudiante, período, materia),
 *    así que si dos escenarios compartieran materia, el segundo no podría matricular a la misma persona.
 *    La materia lleva el código de la original más el código del escenario: `PRN115-E7K2M`.
 *
 * 2. **Las personas y sus perfiles son compartidos y no se limpian.** `student_profiles`,
 *    `teacher_profiles` y `student_careers` tienen clave o índice único por persona, y otro escenario
 *    vivo puede estar usándolos. Se crean una vez y se reutilizan; la limpieza solo se lleva lo
 *    académico del escenario y los estudiantes de relleno, que sí son suyos.
 */
final class Esqueleto
{
    /** Franjas de la sección: [día de la semana (1 = lunes), hora de inicio, hora de fin]. */
    private const FRANJAS = [[1, '07:00', '08:40'], [3, '07:00', '08:40']];

    private ?object $periodo = null;

    public function __construct(
        public readonly Registro $registro,
        public readonly string $codigo,
    ) {}

    /**
     * El período activo. No lo crea: si no hay ninguno, el entorno no está sembrado.
     */
    public function periodo(): object
    {
        if ($this->periodo === null) {
            $periodo = DB::table('academic_periods')->where('status', 'active')->orderByDesc('start_date')->first();

            if ($periodo === null) {
                throw new RuntimeException('No hay un período académico activo. Corra «composer fresh» antes de preparar escenarios.');
            }

            $this->periodo = $periodo;
        }

        return $this->periodo;
    }

    public function departamento(string $codigo = 'DIA'): object
    {
        return $this->fila('departments', 'code', $codigo, 'El departamento');
    }

    public function carrera(string $codigo = 'ISI'): object
    {
        return $this->fila('careers', 'code', $codigo, 'La carrera');
    }

    /**
     * La materia del escenario: una copia de una del catálogo, con su propio código.
     */
    public function materia(string $codigoBase = 'PRN115'): object
    {
        $base = $this->fila('subjects', 'code', $codigoBase, 'La asignatura');
        $codigo = "{$codigoBase}-{$this->codigo}";

        $id = $this->registro->insertar('subjects', [
            'code' => $codigo,
            'name' => $base->name,
            'description' => "Asignatura del escenario de prueba {$this->codigo} (copia de {$codigoBase}).",
            'credits' => $base->credits,
            'department_id' => $base->department_id,
            'is_active' => true,
        ] + $this->sellos());

        // En el plan de estudios, para que salga en la oferta y en la inscripción de materias.
        $planCiclo = DB::table('career_subjects')->where('subject_id', $base->id)->first(['study_plan_id', 'cycle']);
        if ($planCiclo !== null) {
            $this->registro->insertarCon('career_subjects', [
                'study_plan_id' => $planCiclo->study_plan_id,
                'subject_id' => $id,
                'cycle' => $planCiclo->cycle,
            ], ['study_plan_id', 'subject_id']);
        }

        return (object) ['id' => $id, 'code' => $codigo, 'name' => $base->name, 'department_id' => (int) $base->department_id, 'base' => $codigoBase];
    }

    /**
     * La sección del escenario, con su horario. Sin aula: así ningún escenario choca con el horario
     * del demo ni con el de otro escenario (en PostgreSQL el aula tiene restricción de traslape).
     *
     * @param  array<int, array{0: int, 1: string, 2: string}>|null  $franjas
     */
    public function seccion(object $materia, ?array $franjas = null, int $cupo = 40): object
    {
        $periodo = $this->periodo();
        $ulid = (string) Str::ulid();
        $id = $this->registro->insertar('sections', [
            'ulid' => $ulid,
            'period_id' => $periodo->id,
            'subject_id' => $materia->id,
            'code' => '01',
            'min_capacity' => 1,
            'max_capacity' => $cupo,
            'status' => 'open',
        ] + $this->sellos());

        foreach ($franjas ?? self::FRANJAS as [$dia, $inicio, $fin]) {
            $this->registro->insertar('section_schedules', [
                'section_id' => $id,
                'period_id' => $periodo->id,
                'classroom_id' => null,
                'weekday' => $dia,
                'start_time' => $inicio,
                'end_time' => $fin,
            ] + $this->sellos());
        }

        // Clave de inscripción, para probar la automatrícula del estudiante.
        $clave = "{$materia->base}-{$this->codigo}";
        $this->registro->insertar('enrollment_keys', [
            'subject_id' => $materia->id,
            'period_id' => $periodo->id,
            'key_hash' => Hash::make($clave, ['rounds' => 4]),
            'key_encrypted' => Crypt::encryptString($clave),
            'valid_until' => Carbon::parse($periodo->end_date)->endOfDay(),
            'is_active' => true,
        ] + $this->sellos());

        // `ulid` es lo que va en las URL: las rutas de sección resuelven por ULID, no por id.
        return (object) ['id' => $id, 'ulid' => $ulid, 'code' => '01', 'clave' => $clave, 'materia' => $materia, 'periodo' => $periodo];
    }

    /**
     * Los tres cómputos de la sección, copiados de las plantillas del período.
     *
     * @return array<int, object>
     */
    public function computos(object $seccion): array
    {
        $plantillas = DB::table('grading_component_templates')->where('period_id', $this->periodo()->id)->orderBy('number')->get();
        $componentes = [];

        foreach ($plantillas as $plantilla) {
            $id = $this->registro->insertar('grading_components', [
                'section_id' => $seccion->id,
                'number' => $plantilla->number,
                'name' => $plantilla->name,
                'starts_on' => $plantilla->starts_on,
                'ends_on' => $plantilla->ends_on,
                'weight_percent' => $plantilla->weight_percent,
            ] + $this->sellos());
            $componentes[] = (object) ['id' => $id, 'number' => (int) $plantilla->number, 'starts_on' => $plantilla->starts_on, 'ends_on' => $plantilla->ends_on];
        }

        return $componentes;
    }

    /**
     * El cómputo vigente hoy, o el primero si el período aún no arrancó.
     */
    public function computoDeHoy(object $seccion): object
    {
        $componentes = $this->computos($seccion);
        $hoy = Carbon::now()->toDateString();

        foreach ($componentes as $componente) {
            if ($hoy >= $componente->starts_on && $hoy <= $componente->ends_on) {
                return $componente;
            }
        }

        return $componentes[0];
    }

    public function docente(int $usuarioId, object $seccion, bool $responsable = true): int
    {
        $this->rol($usuarioId, 'docente');
        $this->perfilDocente($usuarioId, (int) $seccion->materia->department_id);

        $this->registro->insertar('teaching_assignments', [
            'section_id' => $seccion->id,
            'teacher_id' => $usuarioId,
            'is_responsible' => $responsable,
            'started_at' => Carbon::now(),
        ] + $this->sellos());

        return $usuarioId;
    }

    public function jefe(int $usuarioId, ?int $departamentoId = null): int
    {
        $departamentoId ??= (int) $this->departamento()->id;
        $this->rol($usuarioId, 'jefe_departamento');
        $this->perfilDocente($usuarioId, $departamentoId);

        $vigente = DB::table('department_heads')->where('user_id', $usuarioId)->where('department_id', $departamentoId)->whereNull('valid_to')->exists();
        if (! $vigente) {
            $this->registro->insertar('department_heads', [
                'user_id' => $usuarioId,
                'department_id' => $departamentoId,
                'valid_from' => Carbon::now()->toDateString(),
            ] + $this->sellos());
        }

        return $usuarioId;
    }

    public function coordinador(int $usuarioId, ?int $carreraId = null): int
    {
        $carreraId ??= (int) $this->carrera()->id;
        $this->rol($usuarioId, 'coordinador');

        $vigente = DB::table('career_coordinators')->where('user_id', $usuarioId)->where('career_id', $carreraId)->whereNull('valid_to')->exists();
        if (! $vigente) {
            $this->registro->insertar('career_coordinators', [
                'user_id' => $usuarioId,
                'career_id' => $carreraId,
                'valid_from' => Carbon::now()->toDateString(),
            ] + $this->sellos());
        }

        return $usuarioId;
    }

    public function administrador(int $usuarioId): int
    {
        return $this->rol($usuarioId, 'administrador');
    }

    public function soporte(int $usuarioId): int
    {
        return $this->rol($usuarioId, 'soporte_tecnico');
    }

    /**
     * Matricula a una persona en la sección. Le crea el perfil de estudiante si no lo tiene.
     *
     * @return int el id de la matrícula
     */
    public function matricular(int $usuarioId, object $seccion, string $via = 'key'): int
    {
        $this->rol($usuarioId, 'estudiante');
        $this->perfilEstudiante($usuarioId);

        return $this->registro->insertar('enrollments', [
            'student_id' => $usuarioId,
            'section_id' => $seccion->id,
            'period_id' => $seccion->periodo->id,
            'subject_id' => $seccion->materia->id,
            'status' => 'active',
            'enrolled_via' => $via,
            'enrolled_by' => $usuarioId,
        ] + $this->sellos());
    }

    /**
     * Deja a la persona como estudiante (rol y perfil) sin matricularla en ninguna sección. Sirve a los
     * escenarios que prueban identidad y dispositivos, donde no hace falta una materia.
     */
    public function matricularSinSeccion(int $usuarioId): int
    {
        $this->rol($usuarioId, 'estudiante');
        $this->perfilEstudiante($usuarioId);

        return $usuarioId;
    }

    /**
     * Estudiantes inventados para dar volumen, matriculados en la sección.
     *
     * @return array<int, array{usuario: int, matricula: int}>
     */
    public function relleno(int $cantidad, object $seccion): array
    {
        $creados = [];

        for ($i = 1; $i <= $cantidad; $i++) {
            $correo = Str::lower($this->codigo).'-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'@ues.edu.sv';
            $usuarioId = $this->registro->insertar('users', [
                'institutional_email' => $correo,
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName().' '.fake()->lastName(),
                'is_active' => true,
            ] + $this->sellos());

            $this->rol($usuarioId, 'estudiante', true);
            $this->perfilEstudiante($usuarioId, Str::upper($this->codigo).str_pad((string) $i, 2, '0', STR_PAD_LEFT), true);
            $creados[] = ['usuario' => $usuarioId, 'matricula' => $this->matricular($usuarioId, $seccion, 'import')];
        }

        return $creados;
    }

    /**
     * @return array{created_at: Carbon, updated_at: Carbon}
     */
    public function sellos(?Carbon $en = null): array
    {
        $en ??= Carbon::now();

        return ['created_at' => $en, 'updated_at' => $en];
    }

    /**
     * Asigna un rol de Spatie si la persona no lo tiene. Solo se anota para la limpieza cuando la
     * persona es de relleno: a alguien del equipo no se le quitan roles que otro escenario puede usar.
     */
    private function rol(int $usuarioId, string $nombre, bool $propio = false): int
    {
        $rolId = (int) DB::table('roles')->where('name', $nombre)->value('id');
        $fila = ['role_id' => $rolId, 'model_type' => User::class, 'model_id' => $usuarioId];

        if (DB::table('model_has_roles')->where($fila)->exists()) {
            return $usuarioId;
        }

        if ($propio) {
            $this->registro->insertarCon('model_has_roles', $fila, ['role_id', 'model_type', 'model_id']);
        } else {
            DB::table('model_has_roles')->insert($fila);
        }

        return $usuarioId;
    }

    private function perfilDocente(int $usuarioId, int $departamentoId): void
    {
        if (DB::table('teacher_profiles')->where('user_id', $usuarioId)->exists()) {
            return;
        }

        DB::table('teacher_profiles')->insert(['user_id' => $usuarioId, 'department_id' => $departamentoId] + $this->sellos());
    }

    private function perfilEstudiante(int $usuarioId, ?string $carne = null, bool $propio = false): void
    {
        if (! DB::table('student_profiles')->where('user_id', $usuarioId)->exists()) {
            $carne ??= Str::upper(Str::before((string) DB::table('users')->where('id', $usuarioId)->value('institutional_email'), '@'));
            $fila = ['user_id' => $usuarioId, 'student_code' => Str::limit($carne, 20, '')] + $this->sellos();

            if ($propio) {
                $this->registro->insertarCon('student_profiles', $fila, ['user_id']);
            } else {
                DB::table('student_profiles')->insert($fila);
            }
        }

        if (DB::table('student_careers')->where('student_id', $usuarioId)->where('status', 'active')->exists()) {
            return;
        }

        $carrera = $this->carrera();
        $plan = DB::table('study_plans')->where('career_id', $carrera->id)->orderByDesc('id')->first(['id']);
        $fila = [
            'student_id' => $usuarioId,
            'career_id' => $carrera->id,
            'study_plan_id' => $plan->id,
            'current_cycle' => 1,
            'status' => 'active',
        ] + $this->sellos();

        if ($propio) {
            $this->registro->insertar('student_careers', $fila);
        } else {
            DB::table('student_careers')->insert($fila);
        }
    }

    private function fila(string $tabla, string $columna, string $valor, string $que): object
    {
        $fila = DB::table($tabla)->where($columna, $valor)->first();

        if ($fila === null) {
            throw new RuntimeException("{$que} «{$valor}» no existe. Corra «composer fresh» antes de preparar escenarios.");
        }

        return $fila;
    }
}
