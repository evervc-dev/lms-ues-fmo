<?php

namespace Database\Seeders\Escenarios;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Entrega y calificación de una tarea (RF11, RF15, RF18).
 *
 * La tarea abre hace dos días y cierra en dos, así que la ventana está viva: el estudiante puede
 * entregar y el docente calificar. Lleva rúbrica de tres criterios y política de prórroga con
 * penalización, para probar también la entrega tardía. Parte de los estudiantes ya entregó y está
 * pendiente de calificar; el resto no, para que haya ambas columnas en la lista de entregas.
 */
class EntregaDeTarea extends Escenario
{
    private const UNIDADES = ['Fundamentos', 'Conceptos y técnicas', 'Aplicaciones'];

    public static function clave(): string
    {
        return 'entrega-de-tarea';
    }

    public static function titulo(): string
    {
        return 'Entrega y calificación de una tarea';
    }

    public static function descripcion(): string
    {
        return 'Tarea con rúbrica y ventana abierta (cierra en dos días), con parte de las entregas ya hechas y pendientes de calificar.';
    }

    public static function roles(): array
    {
        return [
            'docente' => self::rol('Docente: crea, recibe y califica la tarea'),
            'estudiante' => self::rol('Estudiantes que entregan', 1, 10),
            'jefe_departamento' => self::rol('Jefe del departamento', 0, 1),
            'coordinador' => self::rol('Coordinador de la carrera', 0, 1),
        ];
    }

    public static function rfs(): array
    {
        return ['RF11', 'RF15', 'RF18'];
    }

    public static function rellenoSugerido(): int
    {
        return 12;
    }

    public function preparar(Reparto $reparto, Esqueleto $esqueleto): Resultado
    {
        $resultado = new Resultado;
        $materia = $esqueleto->materia('BAD115');
        $seccion = $esqueleto->seccion($materia);
        $docente = $esqueleto->docente($reparto->uno('docente'), $seccion);
        $computo = $esqueleto->computoDeHoy($seccion);

        // Unidades con un recurso cada una: sin contenido la pestaña de la sección sale vacía.
        $unidades = [];
        foreach (self::UNIDADES as $posicion => $titulo) {
            $unidadId = $esqueleto->registro->insertar('units', [
                'section_id' => $seccion->id,
                'title' => $titulo,
                'position' => $posicion,
            ] + $esqueleto->sellos());
            $unidades[] = $unidadId;

            $esqueleto->registro->insertar('resources', [
                'ulid' => (string) Str::ulid(),
                'unit_id' => $unidadId,
                'resource_type' => 'page',
                'title' => "Guía de {$titulo}",
                'description' => "Material de apoyo de la unidad «{$titulo}».",
                'html_content' => "<h2>{$titulo}</h2><p>Contenido de prueba del escenario {$esqueleto->codigo}.</p>",
                'completion_mode' => 'manual',
                'position' => 0,
            ] + $esqueleto->sellos());
        }

        $matriculas = [];
        foreach ($reparto->varios('estudiante') as $estudiante) {
            $matriculas[$estudiante] = $esqueleto->matricular($estudiante, $seccion);
        }
        $relleno = $esqueleto->relleno($reparto->relleno, $seccion);

        $abre = Carbon::now()->subDays(2)->setTime(7, 0);
        $cierra = Carbon::now()->addDays(2)->setTime(23, 59);

        $rubrica = $this->rubrica($docente, $esqueleto);
        $itemId = $esqueleto->registro->insertar('grade_items', [
            'component_id' => $computo->id,
            'section_id' => $seccion->id,
            'unit_id' => $unidades[1],
            'item_type' => 'assignment',
            'title' => 'Tarea 1 · Modelo entidad-relación',
            'weight_percent' => 40,
            'is_published' => true,
        ] + $esqueleto->sellos());

        $tareaUlid = (string) Str::ulid();
        $tareaId = $esqueleto->registro->insertar('assessments', [
            'ulid' => $tareaUlid,
            'section_id' => $seccion->id,
            'grade_item_id' => $itemId,
            'assessment_type' => 'assignment',
            'title' => 'Tarea 1 · Modelo entidad-relación',
            'instructions' => 'Entregue el diagrama en PDF. Se califica con la rúbrica publicada.',
            'opens_at' => $abre,
            'closes_at' => $cierra,
            'max_attempts' => 1,
            'rubric_id' => $rubrica,
            'is_anonymous' => false,
        ] + $esqueleto->sellos());

        $esqueleto->registro->insertarCon('assignment_settings', [
            'assessment_id' => $tareaId,
            'is_group' => false,
            'late_policy' => 'grace_penalty',
            'grace_until' => $cierra->copy()->addDays(2),
            'late_penalty_percent' => 10,
            'allowed_extensions' => json_encode(['pdf', 'docx', 'zip']),
            'max_file_mb' => 10,
        ], ['assessment_id']);

        // Dos tercios del relleno ya entregó y espera nota; los del equipo entregan en vivo.
        $entregadas = 0;
        foreach ($relleno as $indice => $alumno) {
            if ($indice % 3 === 2) {
                continue;
            }
            $esqueleto->registro->insertar('submissions', [
                'ulid' => (string) Str::ulid(),
                'assessment_id' => $tareaId,
                'section_id' => $seccion->id,
                'enrollment_id' => $alumno['matricula'],
                'attempt_number' => 1,
                'status' => 'submitted',
                'is_late' => false,
                'late_penalty_applied' => 0,
                'submitted_at' => Carbon::now()->subDay()->subHours($indice % 12),
            ] + $esqueleto->sellos());
            $entregadas++;
        }

        $resultado
            ->enlace('docente', 'secciones.actividades', ['seccion' => $seccion->ulid], 'Actividades de la sección')
            ->enlace('docente', 'secciones.tareas.show', ['seccion' => $seccion->ulid, 'tarea' => $tareaUlid], 'La tarea, como la ve el docente')
            ->enlace('docente', 'secciones.tareas.entregas', ['seccion' => $seccion->ulid, 'tarea' => $tareaUlid], "Entregas por calificar ({$entregadas})")
            ->enlace('docente', 'secciones.tareas.crear', ['seccion' => $seccion->ulid], 'Crear otra tarea, para probar el formulario')
            ->enlace('docente', 'pendientes-de-calificar', [], 'Bandeja de pendientes de calificar')
            ->enlace('docente', 'secciones.contenidos', ['seccion' => $seccion->ulid], 'Contenidos: 3 unidades con un recurso cada una')
            ->enlace('estudiante', 'secciones.tareas.show', ['seccion' => $seccion->ulid, 'tarea' => $tareaUlid], 'Entregar la tarea')
            ->enlace('estudiante', 'secciones.contenidos', ['seccion' => $seccion->ulid], 'Material de la materia')
            ->enlace('estudiante', 'secciones.progreso', ['seccion' => $seccion->ulid], 'Mi progreso por unidad (RF18)')
            ->enlace('estudiante', 'mis-notas', [], 'Mis notas');

        if ($reparto->unoONulo('jefe_departamento') !== null) {
            $resultado->enlace('jefe_departamento', 'secciones.analitica', ['seccion' => $seccion->ulid], 'Analítica de la sección');
        }
        if ($reparto->unoONulo('coordinador') !== null) {
            $resultado->enlace('coordinador', 'secciones', [], 'Secciones de la carrera');
        }

        return $resultado
            ->dato('seccion_id', $seccion->id)
            ->dato('seccion_ulid', $seccion->ulid)
            ->dato('tarea_id', $tareaId)
            ->dato('tarea_ulid', $tareaUlid)
            ->dato('materia', $materia->code)
            ->nota("Sección {$materia->code}-01 de «{$materia->name}».")
            ->nota('La tarea abrió hace 2 días y cierra en 2; con prórroga de 2 días más y 10 % de penalización.')
            ->nota("Ya hay {$entregadas} entregas del relleno esperando nota; los del equipo entregan en vivo.")
            ->nota('La rúbrica tiene 3 criterios (10 puntos en total) y 3 niveles cada uno.');
    }

    private function rubrica(int $docente, Esqueleto $esqueleto): int
    {
        $rubricaId = $esqueleto->registro->insertar('rubrics', [
            'owner_id' => $docente,
            'title' => 'Rúbrica · Modelo entidad-relación',
        ] + $esqueleto->sellos());

        foreach ([['Contenido y exactitud', 4], ['Formato y presentación', 3], ['Puntualidad y orden', 3]] as $posicion => [$nombre, $maximo]) {
            $criterioId = $esqueleto->registro->insertar('rubric_criteria', [
                'rubric_id' => $rubricaId,
                'name' => $nombre,
                'max_points' => $maximo,
                'position' => $posicion,
            ] + $esqueleto->sellos());

            foreach ([['Excelente', 1.0], ['Bueno', 0.7], ['Regular', 0.4]] as [$nivel, $factor]) {
                $esqueleto->registro->insertar('rubric_levels', [
                    'criterion_id' => $criterioId,
                    'name' => $nivel,
                    'points' => $maximo * $factor,
                ] + $esqueleto->sellos());
            }
        }

        return $rubricaId;
    }
}
