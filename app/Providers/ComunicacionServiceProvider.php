<?php

namespace App\Providers;

use App\Modules\Academica\Events\HorarioCambiado;
use App\Modules\Academica\Events\MatriculaCambiada;
use App\Modules\Academica\Events\PeriodoReabierto;
use App\Modules\Asistencia\Events\AlertaAsistenciaAbierta;
use App\Modules\Asistencia\Events\ClaseCancelada;
use App\Modules\Calificaciones\Events\ActaDevuelta;
use App\Modules\Calificaciones\Events\ActaPublicada;
use App\Modules\Calificaciones\Events\NotaCorregida;
use App\Modules\Calificaciones\Events\NotasPublicadas;
use App\Modules\Calificaciones\Events\RevisionResuelta;
use App\Modules\Calificaciones\Events\RevisionSolicitada;
use App\Modules\Calificaciones\Events\RiesgoAcademicoDetectado;
use App\Modules\Comunicacion\Events\RespuestaPublicada;
use App\Modules\Comunicacion\Listeners\EnviarAvisos;
use App\Modules\Comunicacion\Listeners\RegistrarEntregas;
use App\Modules\Evaluaciones\Events\ProrrogaConcedida;
use App\Modules\Evaluaciones\Events\RevisionCuestionarioPublicada;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

/**
 * Módulo Comunicación y notificaciones (ADR-017): componentes Livewire, rutas y los avisos que escucha
 * de los demás módulos (RF23).
 */
class ComunicacionServiceProvider extends ModuloServiceProvider
{
    protected function modulo(): string
    {
        return 'Comunicacion';
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(NotasPublicadas::class, [EnviarAvisos::class, 'notasPublicadas']);
        Event::listen(ActaPublicada::class, [EnviarAvisos::class, 'actaPublicada']);
        Event::listen(ActaDevuelta::class, [EnviarAvisos::class, 'actaDevuelta']);
        Event::listen(RevisionCuestionarioPublicada::class, [EnviarAvisos::class, 'revisionCuestionarioPublicada']);
        Event::listen(RevisionSolicitada::class, [EnviarAvisos::class, 'revisionSolicitada']);
        Event::listen(RevisionResuelta::class, [EnviarAvisos::class, 'revisionResuelta']);
        Event::listen(ProrrogaConcedida::class, [EnviarAvisos::class, 'prorrogaConcedida']);
        Event::listen(HorarioCambiado::class, [EnviarAvisos::class, 'horarioCambiado']);
        Event::listen(MatriculaCambiada::class, [EnviarAvisos::class, 'matriculaCambiada']);
        Event::listen(PeriodoReabierto::class, [EnviarAvisos::class, 'periodoReabierto']);
        Event::listen(RespuestaPublicada::class, [EnviarAvisos::class, 'respuestaPublicada']);
        Event::listen(ClaseCancelada::class, [EnviarAvisos::class, 'claseCancelada']);
        Event::listen(AlertaAsistenciaAbierta::class, [EnviarAvisos::class, 'alertaAsistencia']);
        Event::listen(RiesgoAcademicoDetectado::class, [EnviarAvisos::class, 'riesgoAcademico']);
        Event::listen(NotaCorregida::class, [EnviarAvisos::class, 'notaCorregida']);

        // Cuándo salió cada aviso por cada canal: la medición de RNF12.
        Event::listen(NotificationSent::class, [RegistrarEntregas::class, 'enviada']);
        Event::listen(NotificationFailed::class, [RegistrarEntregas::class, 'fallida']);
    }
}
