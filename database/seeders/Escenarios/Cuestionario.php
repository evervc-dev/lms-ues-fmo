<?php

namespace Database\Seeders\Escenarios;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Cuestionario en línea (RF16).
 *
 * El parcial está abierto ahora y cierra en dos horas, con 60 minutos de límite por intento, así que
 * el estudiante lo puede resolver de verdad y el docente verlo en el monitor. El banco trae 15
 * preguntas de los cuatro tipos (selección, verdadero/falso, respuesta corta y ensayo) y la regla
 * saca 10 al azar, que es lo que hace que dos intentos no sean iguales.
 */
class Cuestionario extends Escenario
{
    public static function clave(): string
    {
        return 'cuestionario';
    }

    public static function titulo(): string
    {
        return 'Cuestionario en línea (parcial)';
    }

    public static function descripcion(): string
    {
        return 'Parcial abierto ahora, 60 minutos por intento, con banco de 15 preguntas de los cuatro tipos y 10 al azar por intento.';
    }

    public static function roles(): array
    {
        return [
            'docente' => self::rol('Docente: arma el banco y vigila el monitor'),
            'estudiante' => self::rol('Estudiantes que resuelven el parcial', 1, 10),
            'jefe_departamento' => self::rol('Jefe del departamento', 0, 1),
        ];
    }

    public static function rfs(): array
    {
        return ['RF16'];
    }

    public static function rellenoSugerido(): int
    {
        return 8;
    }

    public function preparar(Reparto $reparto, Esqueleto $esqueleto): Resultado
    {
        $resultado = new Resultado;
        $materia = $esqueleto->materia('ESD115');
        $seccion = $esqueleto->seccion($materia);
        $docente = $esqueleto->docente($reparto->uno('docente'), $seccion);
        $computo = $esqueleto->computoDeHoy($seccion);

        foreach ($reparto->varios('estudiante') as $estudiante) {
            $esqueleto->matricular($estudiante, $seccion);
        }
        $esqueleto->relleno($reparto->relleno, $seccion);

        $bancoId = $this->banco($docente, $materia, $esqueleto);

        $itemId = $esqueleto->registro->insertar('grade_items', [
            'component_id' => $computo->id,
            'section_id' => $seccion->id,
            'unit_id' => null,
            'item_type' => 'quiz',
            'title' => 'Parcial 1',
            'weight_percent' => 60,
            'is_published' => true,
        ] + $esqueleto->sellos());

        $quizUlid = (string) Str::ulid();
        $quizId = $esqueleto->registro->insertar('assessments', [
            'ulid' => $quizUlid,
            'section_id' => $seccion->id,
            'grade_item_id' => $itemId,
            'assessment_type' => 'quiz',
            'title' => 'Parcial 1',
            'instructions' => 'Cuestionario de 10 preguntas. Tiene 60 minutos desde que inicia el intento.',
            'opens_at' => Carbon::now()->subMinutes(5),
            'closes_at' => Carbon::now()->addHours(2),
            'max_attempts' => 1,
            'is_anonymous' => false,
        ] + $esqueleto->sellos());

        $esqueleto->registro->insertarCon('quiz_settings', [
            'assessment_id' => $quizId,
            'time_limit_minutes' => 60,
            'autosave_seconds' => 30,
            'navigation' => 'free',
            'review_published' => false,
        ], ['assessment_id']);

        $esqueleto->registro->insertar('quiz_question_rules', [
            'assessment_id' => $quizId,
            'bank_id' => $bancoId,
            'quantity' => 10,
        ] + $esqueleto->sellos());

        $resultado
            ->enlace('docente', 'secciones.cuestionarios.show', ['seccion' => $seccion->ulid, 'cuestionario' => $quizUlid], 'El parcial y su monitor en vivo')
            ->enlace('docente', 'secciones.actividades', ['seccion' => $seccion->ulid], 'Actividades de la sección')
            ->enlace('docente', 'secciones.cuestionarios.crear', ['seccion' => $seccion->ulid], 'Crear otro cuestionario')
            ->enlace('docente', 'bancos-de-preguntas.show', ['banco' => $bancoId], 'El banco de 15 preguntas')
            ->enlace('docente', 'pendientes-de-calificar', [], 'Los ensayos quedan por calificar a mano')
            ->enlace('estudiante', 'secciones.actividades', ['seccion' => $seccion->ulid], 'Actividades: desde aquí inicia el parcial')
            ->enlace('estudiante', 'secciones.cuestionarios.show', ['seccion' => $seccion->ulid, 'cuestionario' => $quizUlid], 'Resolver el parcial')
            ->enlace('estudiante', 'mis-notas', [], 'Mis notas');

        if ($reparto->unoONulo('jefe_departamento') !== null) {
            $resultado->enlace('jefe_departamento', 'secciones.analitica', ['seccion' => $seccion->ulid], 'Analítica de la sección');
        }

        return $resultado
            ->dato('seccion_id', $seccion->id)
            ->dato('seccion_ulid', $seccion->ulid)
            ->dato('cuestionario_id', $quizId)
            ->dato('cuestionario_ulid', $quizUlid)
            ->dato('banco_id', $bancoId)
            ->dato('materia', $materia->code)
            ->nota("Sección {$materia->code}-01 de «{$materia->name}».")
            ->nota('El parcial abrió hace 5 minutos y cierra en 2 horas; 60 minutos de límite por intento.')
            ->nota('Banco de 15 preguntas (8 de selección, 4 de verdadero/falso, 2 cortas y 1 de ensayo); el intento saca 10.')
            ->nota('Navegación libre y revisión sin publicar: el estudiante no ve las respuestas correctas al terminar.');
    }

    private function banco(int $docente, object $materia, Esqueleto $esqueleto): int
    {
        $bancoId = $esqueleto->registro->insertar('question_banks', [
            'owner_id' => $docente,
            'subject_id' => $materia->id,
            'name' => "Banco de {$materia->name}",
        ] + $esqueleto->sellos());

        for ($n = 1; $n <= 15; $n++) {
            $tipo = match (true) {
                $n <= 8 => 'multiple_choice',
                $n <= 12 => 'true_false',
                $n <= 14 => 'short_answer',
                default => 'essay',
            };

            $preguntaId = $esqueleto->registro->insertar('questions', [
                'bank_id' => $bancoId,
                'question_type' => $tipo,
                'statement' => "Pregunta {$n} de {$materia->name}: ".fake()->sentence(10),
                'topic' => 'Tema '.(($n % 3) + 1),
                'accepted_answers' => $tipo === 'short_answer' ? json_encode(['respuesta', 'resultado']) : null,
                'justification' => 'La respuesta correcta se explica en la unidad '.(($n % 4) + 1).'.',
            ] + $esqueleto->sellos());

            if ($tipo === 'multiple_choice') {
                $correcta = random_int(0, 3);
                foreach (range(0, 3) as $opcion) {
                    $esqueleto->registro->insertar('question_options', [
                        'question_id' => $preguntaId,
                        'text' => 'Opción '.chr(65 + $opcion),
                        'is_correct' => $opcion === $correcta,
                        'position' => $opcion,
                    ] + $esqueleto->sellos());
                }
            } elseif ($tipo === 'true_false') {
                $correcta = (bool) random_int(0, 1);
                foreach ([['Verdadero', $correcta, 0], ['Falso', ! $correcta, 1]] as [$texto, $esCorrecta, $posicion]) {
                    $esqueleto->registro->insertar('question_options', [
                        'question_id' => $preguntaId,
                        'text' => $texto,
                        'is_correct' => $esCorrecta,
                        'position' => $posicion,
                    ] + $esqueleto->sellos());
                }
            }
        }

        return $bancoId;
    }
}
