<?php

namespace App\Modules\Comunicacion\Services;

use App\Models\User;
use App\Modules\Comunicacion\Jobs\EnviarMensajeMasivo;
use App\Modules\Evaluaciones\Services\EquipoService;
use App\Support\Alcance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mensajería condicional a estudiantes (RF31): el docente filtra desde el libro de calificaciones a quienes no
 * entregaron una actividad o sacaron menos de cierta nota, revisa la lista, quita a quien quiera y les escribe.
 *
 * Es correo, no mensajería interna: cada estudiante recibe un correo individual y un aviso en la plataforma.
 * El permiso es `mensajes.condicionales.enviar` (Docente, alcance S: sus secciones).
 */
class MensajeriaService
{
    public const NO_ENTREGARON = 'not_submitted';

    public const NOTA_MENOR = 'score_below';

    /** @var array<string, string> */
    public const CONDICIONES = [self::NO_ENTREGARON => 'No entregaron', self::NOTA_MENOR => 'Nota menor a'];

    public function __construct(private Alcance $alcance) {}

    /**
     * Si el usuario puede mensajear a los estudiantes de la sección.
     */
    public function puede(User $docente, int $seccionId): bool
    {
        return $this->alcance->puedeEnSeccion($docente, 'mensajes.condicionales.enviar', $seccionId);
    }

    /**
     * Actividades de la sección sobre las que se puede filtrar. `entregable` dice si recibe entregas, que es lo
     * que necesita la condición «no entregaron».
     *
     * @return Collection<int, object{id: int, title: string, entregable: bool}>
     */
    public function actividades(User $docente, int $seccionId): Collection
    {
        $this->exigir($docente, $seccionId);

        return DB::table('grade_items as i')->leftJoin('assessments as a', 'a.grade_item_id', '=', 'i.id')
            ->where('i.section_id', $seccionId)->orderBy('i.title')
            ->get(['i.id', 'i.title', 'a.id as assessment_id'])
            ->map(fn (object $i) => (object) ['id' => $i->id, 'title' => $i->title, 'entregable' => $i->assessment_id !== null]);
    }

    /**
     * Matriculados activos de la sección que cumplen la condición sobre la actividad.
     *
     * - «No entregaron»: sin entrega enviada ni intento de cuestionario terminado.
     * - «Nota menor a X»: con nota **publicada** menor a X; quien no tiene nota publicada no entra.
     *
     * @return Collection<int, object{id: int, nombre: string, correo: string, nota: ?float}>
     */
    public function destinatarios(User $docente, int $seccionId, int $itemId, string $condicion, ?float $umbral = null): Collection
    {
        $this->exigir($docente, $seccionId);

        $item = DB::table('grade_items')->where('id', $itemId)->where('section_id', $seccionId)->first(['id'])
            ?? throw ValidationException::withMessages(['item' => __('Esa actividad no es de esta sección.')]);

        $consulta = DB::table('enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')
            ->where('e.section_id', $seccionId)->where('e.status', 'active')
            ->orderBy('u.last_name')->orderBy('u.first_name');

        $columnas = ['u.id', 'u.first_name', 'u.last_name', 'u.institutional_email'];

        if ($condicion === self::NO_ENTREGARON) {
            $evaluacion = DB::table('assessments')->where('grade_item_id', $item->id)->where('section_id', $seccionId)->value('id')
                ?? throw ValidationException::withMessages(['item' => __('Esa actividad no recibe entregas.')]);

            $consulta
                // Una entrega grupal cuenta para todo el equipo (RF15).
                ->whereNotExists(fn ($q) => EquipoService::deMatriculaColumna($q->from('submissions as s'), 'e.id')
                    ->where('s.assessment_id', $evaluacion)->where('s.status', 'submitted'))
                ->whereNotExists(fn ($q) => $q->from('quiz_attempts as q')->whereColumn('q.enrollment_id', 'e.id')
                    ->where('q.assessment_id', $evaluacion)->where('q.status', '!=', 'in_progress'));
            $columnas[] = DB::raw('null as nota');
        } elseif ($condicion === self::NOTA_MENOR) {
            if ($umbral === null) {
                throw ValidationException::withMessages(['umbral' => __('Indique la nota.')]);
            }
            $consulta->join('grades as g', fn ($j) => $j->on('g.enrollment_id', '=', 'e.id')->where('g.grade_item_id', $item->id)->where('g.status', 'published'))
                ->where('g.score', '<', $umbral);
            $columnas[] = 'g.score as nota';
        } else {
            throw ValidationException::withMessages(['condicion' => __('Esa condición no existe.')]);
        }

        return $consulta->get($columnas)->map(fn (object $f) => (object) [
            'id' => (int) $f->id, 'nombre' => trim($f->first_name.' '.$f->last_name), 'correo' => $f->institutional_email,
            'nota' => $f->nota !== null ? (float) $f->nota : null,
        ]);
    }

    /**
     * Guarda el mensaje y lo manda a la cola. La lista se **vuelve a calcular aquí**: solo reciben el mensaje
     * quienes cumplen la condición y el docente no quitó; un id que no esté en ella se ignora.
     *
     * @param  array{item: int, condicion: string, umbral: ?float, destinatarios: array<int, int>, asunto: string, texto: string}  $datos
     * @return int id del mensaje
     */
    public function enviar(User $docente, int $seccionId, array $datos): int
    {
        $lista = $this->destinatarios($docente, $seccionId, $datos['item'], $datos['condicion'], $datos['umbral'])
            ->whereIn('id', $datos['destinatarios']);

        if ($lista->isEmpty()) {
            throw ValidationException::withMessages(['destinatarios' => __('Debe quedar al menos un destinatario.')]);
        }

        $id = DB::transaction(function () use ($docente, $seccionId, $datos, $lista): int {
            $ahora = now();
            $id = DB::table('mass_messages')->insertGetId([
                'grade_item_id' => $datos['item'], 'section_id' => $seccionId,
                'condition' => json_encode(['type' => $datos['condicion'], 'threshold' => $datos['umbral']]),
                'subject' => $datos['asunto'], 'body' => $datos['texto'], 'sent_by' => $docente->id,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
            DB::table('mass_message_recipients')->insert($lista->map(fn (object $d) => ['mass_message_id' => $id, 'user_id' => $d->id])->values()->all());

            return $id;
        });

        EnviarMensajeMasivo::dispatch($id)->afterCommit();

        return $id;
    }

    /**
     * Mensajes enviados en la sección, del más reciente al más antiguo, con sus destinatarios y si ya se
     * entregaron (Notas › Mensajes enviados).
     *
     * @return Collection<int, object>
     */
    public function historial(User $docente, int $seccionId): Collection
    {
        $this->exigir($docente, $seccionId);

        $mensajes = DB::table('mass_messages as m')->join('users as u', 'u.id', '=', 'm.sent_by')
            ->leftJoin('grade_items as i', 'i.id', '=', 'm.grade_item_id')
            ->where('m.section_id', $seccionId)->orderByDesc('m.id')
            ->get(['m.id', 'm.subject', 'm.body', 'm.condition', 'm.created_at', 'u.first_name', 'u.last_name', 'i.title as actividad']);

        $destinatarios = DB::table('mass_message_recipients as r')->join('users as u', 'u.id', '=', 'r.user_id')
            ->whereIn('r.mass_message_id', $mensajes->pluck('id'))->orderBy('u.last_name')->orderBy('u.first_name')
            ->get(['r.mass_message_id', 'u.first_name', 'u.last_name', 'u.institutional_email', 'r.delivered_at'])
            ->groupBy('mass_message_id');

        return $mensajes->map(function (object $m) use ($destinatarios): object {
            $lista = ($destinatarios[$m->id] ?? collect())->map(fn (object $d) => (object) [
                'nombre' => trim($d->first_name.' '.$d->last_name), 'correo' => $d->institutional_email, 'entregado' => $d->delivered_at !== null,
            ]);

            return (object) [
                'id' => $m->id, 'asunto' => $m->subject, 'texto' => $m->body, 'actividad' => $m->actividad,
                'condicion' => json_decode($m->condition, true), 'cuando' => $m->created_at,
                'enviadoPor' => trim($m->first_name.' '.$m->last_name),
                'destinatarios' => $lista, 'entregados' => $lista->where('entregado', true)->count(),
            ];
        });
    }

    private function exigir(User $docente, int $seccionId): void
    {
        abort_unless($this->puede($docente, $seccionId), 403);
    }
}
