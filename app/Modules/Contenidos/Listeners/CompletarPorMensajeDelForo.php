<?php

namespace App\Modules\Contenidos\Listeners;

use App\Modules\Comunicacion\Events\RespuestaPublicada;
use App\Modules\Contenidos\Services\ProgresoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * Completa los recursos cuya regla es escribir N mensajes en ese hilo del foro (RF18, RF30, ADR-067): se cuentan los
 * mensajes visibles del autor y se evalúa solo su matrícula.
 */
class CompletarPorMensajeDelForo implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private ProgresoService $progreso) {}

    public function handle(RespuestaPublicada $evento): void
    {
        $mensaje = DB::table('forum_posts')->where('id', $evento->mensajeId)->first(['thread_id', 'author_id']);
        if ($mensaje !== null) {
            $this->progreso->evaluarPorHilo((int) $mensaje->thread_id, (int) $mensaje->author_id);
        }
    }
}
