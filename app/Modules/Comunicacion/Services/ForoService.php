<?php

namespace App\Modules\Comunicacion\Services;

use App\Models\User;
use App\Modules\Comunicacion\Events\RespuestaPublicada;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Foros de la sección (RF30): hilos y respuestas anidadas. Estudiantes (M) y docentes (S) participan;
 * el docente modera: fija, cierra u oculta. Los mensajes se guardan como texto (escapado al mostrar).
 */
class ForoService
{
    public function __construct(private Alcance $alcance) {}

    public function puedeParticipar(User $usuario, int $seccionId): bool
    {
        return $this->alcance->puedeEnSeccion($usuario, 'foros.participar', $seccionId);
    }

    public function puedeModerar(User $usuario, int $seccionId): bool
    {
        return $this->alcance->puedeEnSeccion($usuario, 'foros.moderar', $seccionId);
    }

    /**
     * @return Collection<int, object>
     */
    public function hilos(User $usuario, int $seccionId): Collection
    {
        $this->exigir($this->puedeParticipar($usuario, $seccionId));

        return DB::table('forum_threads as t')->join('users as u', 'u.id', '=', 't.author_id')->where('t.section_id', $seccionId)
            ->select('t.*', DB::raw("u.first_name || ' ' || u.last_name as autor"),
                DB::raw('(select count(*) from forum_posts p where p.thread_id = t.id and p.is_hidden = false) as respuestas'),
                DB::raw('(select max(p.created_at) from forum_posts p where p.thread_id = t.id) as ultima'))
            ->orderByDesc('t.is_pinned')->orderByDesc(DB::raw('coalesce((select max(p.created_at) from forum_posts p where p.thread_id = t.id), t.created_at)'))->get();
    }

    public function crearHilo(User $usuario, int $seccionId, string $titulo, string $mensaje): int
    {
        $this->exigir($this->puedeParticipar($usuario, $seccionId));

        return DbContext::run(function () use ($usuario, $seccionId, $titulo, $mensaje) {
            $hilo = DB::table('forum_threads')->insertGetId(['section_id' => $seccionId, 'author_id' => $usuario->id, 'title' => $titulo, 'is_closed' => false, 'is_pinned' => false, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('forum_posts')->insert(['thread_id' => $hilo, 'author_id' => $usuario->id, 'body_html' => $mensaje, 'is_hidden' => false, 'created_at' => now(), 'updated_at' => now()]);

            return $hilo;
        }, 'forum.thread_created');
    }

    /**
     * Hilo con sus mensajes en árbol (padre → hijos).
     *
     * @return array{hilo: object, raiz: Collection<int, object>, hijos: Collection<int|string, Collection<int, object>>}
     */
    public function hilo(User $usuario, int $seccionId, int $hiloId): array
    {
        $this->exigir($this->puedeParticipar($usuario, $seccionId));
        $hilo = DB::table('forum_threads')->where('id', $hiloId)->where('section_id', $seccionId)->first() ?? abort(404);
        $modera = $this->puedeModerar($usuario, $seccionId);

        $mensajes = DB::table('forum_posts as p')->join('users as u', 'u.id', '=', 'p.author_id')->where('p.thread_id', $hiloId)
            ->when(! $modera, fn ($q) => $q->where('p.is_hidden', false))
            ->orderBy('p.created_at')
            ->get(['p.id', 'p.parent_post_id', 'p.body_html', 'p.is_hidden', 'p.created_at', 'p.author_id', 'u.first_name', 'u.last_name',
                DB::raw("exists(select 1 from teaching_assignments t where t.section_id = {$seccionId} and t.teacher_id = p.author_id and t.ended_at is null) as es_docente")]);

        return ['hilo' => $hilo, 'raiz' => $mensajes->whereNull('parent_post_id')->values(), 'hijos' => $mensajes->whereNotNull('parent_post_id')->groupBy('parent_post_id')];
    }

    public function responder(User $usuario, int $seccionId, int $hiloId, string $mensaje, ?int $padre = null): void
    {
        $hilo = $this->hilo($usuario, $seccionId, $hiloId)['hilo'];
        if ($hilo->is_closed) {
            throw ValidationException::withMessages(['mensaje' => __('El hilo está cerrado.')]);
        }
        if ($padre !== null && ! DB::table('forum_posts')->where('id', $padre)->where('thread_id', $hiloId)->exists()) {
            abort(404);
        }
        $id = DbContext::run(fn () => DB::table('forum_posts')->insertGetId(['thread_id' => $hiloId, 'parent_post_id' => $padre, 'author_id' => $usuario->id,
            'body_html' => $mensaje, 'is_hidden' => false, 'created_at' => now(), 'updated_at' => now()]), 'forum.post_created');

        RespuestaPublicada::dispatch((int) $id);
    }

    /**
     * Fijar o cerrar el hilo, u ocultar un mensaje. Queda registrado (auditoría).
     */
    public function moderar(User $usuario, int $seccionId, int $hiloId, string $accion, ?int $mensaje = null): void
    {
        $this->exigir($this->puedeModerar($usuario, $seccionId));
        DB::table('forum_threads')->where('id', $hiloId)->where('section_id', $seccionId)->exists() || abort(404);

        DbContext::run(fn () => match ($accion) {
            'fijar' => DB::table('forum_threads')->where('id', $hiloId)->update(['is_pinned' => DB::raw('not is_pinned'), 'updated_at' => now()]),
            'cerrar' => DB::table('forum_threads')->where('id', $hiloId)->update(['is_closed' => DB::raw('not is_closed'), 'updated_at' => now()]),
            'ocultar' => DB::table('forum_posts')->where('id', $mensaje)->where('thread_id', $hiloId)->update(['is_hidden' => DB::raw('not is_hidden'), 'updated_at' => now()]),
            default => abort(400),
        }, 'forum.moderated');
    }

    /**
     * Sin permiso o fuera del alcance de la sección, 403 (ADR-026).
     *
     * @throws AuthorizationException
     */
    private function exigir(bool $permitido): void
    {
        if (! $permitido) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
    }
}
