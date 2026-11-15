<?php

namespace Database\Seeders\Escenarios;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Notas, solicitud de revisión y cierre del acta (RF17, RF25, RF34, RF35).
 *
 * La sección llega con los tres cómputos cargados y las notas de las dos primeras actividades ya
 * publicadas, de modo que el estudiante vea su acumulada y el docente pueda cerrar el acta. Al primer
 * estudiante del reparto se le deja una solicitud de revisión sin responder, que es la bandeja que el
 * docente tiene que atender; el jefe autoriza el cierre del departamento.
 */
class NotasYCierre extends Escenario
{
    public static function clave(): string
    {
        return 'notas-y-cierre';
    }

    public static function titulo(): string
    {
        return 'Notas, revisión y cierre del acta';
    }

    public static function descripcion(): string
    {
        return 'Notas publicadas de dos actividades, una solicitud de revisión sin responder y el acta lista para publicarse y validarse.';
    }

    public static function roles(): array
    {
        return [
            'docente' => self::rol('Docente: califica, responde la revisión y publica el acta'),
            'estudiante' => self::rol('Estudiantes con notas; el primero pide revisión', 1, 10),
            'jefe_departamento' => self::rol('Jefe: valida el acta y autoriza el cierre'),
            'coordinador' => self::rol('Coordinador de la carrera', 0, 1),
        ];
    }

    public static function rfs(): array
    {
        return ['RF17', 'RF25', 'RF34', 'RF35'];
    }

    public static function rellenoSugerido(): int
    {
        return 15;
    }

    public function preparar(Reparto $reparto, Esqueleto $esqueleto): Resultado
    {
        $resultado = new Resultado;
        $materia = $esqueleto->materia('DSI115');
        $seccion = $esqueleto->seccion($materia);
        $docente = $esqueleto->docente($reparto->uno('docente'), $seccion);
        $computos = $esqueleto->computos($seccion);

        $matriculas = [];
        foreach ($reparto->varios('estudiante') as $estudiante) {
            $matriculas[$estudiante] = $esqueleto->matricular($estudiante, $seccion);
        }
        $relleno = $esqueleto->relleno($reparto->relleno, $seccion);
        $todas = array_merge(array_values($matriculas), array_map(fn (array $a) => $a['matricula'], $relleno));

        // Dos actividades del primer cómputo, con notas publicadas.
        $actividades = [];
        foreach ([['Tarea 1', 'assignment', 40], ['Parcial 1', 'quiz', 60]] as [$titulo, $tipo, $peso]) {
            $itemId = $esqueleto->registro->insertar('grade_items', [
                'component_id' => $computos[0]->id,
                'section_id' => $seccion->id,
                'item_type' => $tipo,
                'title' => $titulo,
                'weight_percent' => $peso,
                'is_published' => true,
            ] + $esqueleto->sellos());

            $ulid = (string) Str::ulid();
            $assessmentId = $esqueleto->registro->insertar('assessments', [
                'ulid' => $ulid,
                'section_id' => $seccion->id,
                'grade_item_id' => $itemId,
                'assessment_type' => $tipo,
                'title' => $titulo,
                'instructions' => "Actividad ya calificada del escenario {$esqueleto->codigo}.",
                'opens_at' => Carbon::now()->subDays(20),
                'closes_at' => Carbon::now()->subDays(10),
                'max_attempts' => 1,
            ] + $esqueleto->sellos());

            if ($tipo === 'assignment') {
                $esqueleto->registro->insertarCon('assignment_settings', [
                    'assessment_id' => $assessmentId,
                    'is_group' => false,
                    'late_policy' => 'strict',
                    'late_penalty_percent' => 0,
                    'allowed_extensions' => json_encode(['pdf']),
                    'max_file_mb' => 10,
                ], ['assessment_id']);
            } else {
                $esqueleto->registro->insertarCon('quiz_settings', [
                    'assessment_id' => $assessmentId,
                    'time_limit_minutes' => 60,
                    'autosave_seconds' => 30,
                    'navigation' => 'free',
                    'review_published' => true,
                ], ['assessment_id']);
            }

            $notas = [];
            foreach ($todas as $indice => $matriculaId) {
                $notas[] = [
                    'grade_item_id' => $itemId,
                    'enrollment_id' => $matriculaId,
                    'section_id' => $seccion->id,
                    'score' => round(5.0 + (($indice * 7) % 50) / 10, 2),
                    'status' => 'published',
                    'graded_by' => $docente,
                    'published_at' => Carbon::now()->subDays(5),
                ] + $esqueleto->sellos();
            }
            $esqueleto->registro->insertarVarias('grades', $notas);

            $actividades[] = ['id' => $assessmentId, 'ulid' => $ulid, 'item' => $itemId, 'titulo' => $titulo];
        }

        // El acta, en borrador: el docente la publica y el jefe la valida.
        $esqueleto->registro->insertar('grade_sheets', [
            'section_id' => $seccion->id,
            'status' => 'draft',
        ] + $esqueleto->sellos());

        // Solicitud de revisión sin responder, del primer estudiante del reparto (RF35).
        $revisionUlid = null;
        $primera = array_key_first($matriculas);
        if ($primera !== null) {
            $nota = (float) DB::table('grades')->where('grade_item_id', $actividades[0]['item'])->where('enrollment_id', $matriculas[$primera])->value('score');
            $revisionUlid = (string) Str::ulid();
            $esqueleto->registro->insertar('grade_review_requests', [
                'ulid' => $revisionUlid,
                'section_id' => $seccion->id,
                'enrollment_id' => $matriculas[$primera],
                'assessment_id' => $actividades[0]['id'],
                'status' => 'pending',
                'reason' => 'Creo que el criterio de formato se calificó de menos: entregué el diagrama con la portada pedida.',
                'score_before' => $nota,
                'requested_at' => Carbon::now()->subDays(2),
            ] + $esqueleto->sellos(Carbon::now()->subDays(2)));
        }

        $resultado
            ->enlace('docente', 'secciones.notas', ['seccion' => $seccion->ulid], 'Cuadro de notas de la sección')
            ->enlace('docente', 'secciones.pesos', ['seccion' => $seccion->ulid], 'Pesos de los cómputos y las actividades')
            ->enlace('docente', 'secciones.revisiones', ['seccion' => $seccion->ulid], 'Solicitudes de revisión (hay 1 sin responder)')
            ->enlace('docente', 'secciones.acta', ['seccion' => $seccion->ulid], 'Acta de la sección: publicarla')
            ->enlace('docente', 'secciones.mensajes', ['seccion' => $seccion->ulid], 'Mensajería condicional por nota (RF31)')
            ->enlace('estudiante', 'mis-notas', [], 'Mis notas, con la acumulada del cómputo 1')
            ->enlace('estudiante', 'secciones.actividades', ['seccion' => $seccion->ulid], 'Actividades calificadas')
            ->enlace('jefe_departamento', 'actas', [], 'Actas por validar del departamento')
            ->enlace('jefe_departamento', 'cierre', [], 'Autorizar el cierre del departamento (RF34)')
            ->enlace('jefe_departamento', 'reportes', [], 'Reportes y exportaciones (RF25)');

        if ($revisionUlid !== null) {
            $resultado->enlace('docente', 'secciones.revisiones.show', ['seccion' => $seccion->ulid, 'revision' => $revisionUlid], 'La solicitud de revisión pendiente');
        }
        if ($reparto->unoONulo('coordinador') !== null) {
            $esqueleto->coordinador($reparto->uno('coordinador'));
            $resultado->enlace('coordinador', 'reportes', [], 'Reportes de la carrera');
        }

        $cuantos = count($todas);

        return $resultado
            ->dato('seccion_id', $seccion->id)
            ->dato('seccion_ulid', $seccion->ulid)
            ->dato('revision_ulid', $revisionUlid)
            ->dato('materia', $materia->code)
            ->nota("Sección {$materia->code}-01 de «{$materia->name}», con {$cuantos} estudiantes.")
            ->nota('Tarea 1 (40 %) y Parcial 1 (60 %) del cómputo 1, con nota publicada para todos.')
            ->nota('Hay una solicitud de revisión sin responder, del primer estudiante del reparto.')
            ->nota('El acta está en borrador: el docente la publica y el jefe la valida y autoriza el cierre.');
    }
}
