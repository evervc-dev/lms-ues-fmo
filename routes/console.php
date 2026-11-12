<?php

use App\Modules\Asistencia\Jobs\PurgarTokensQr;
use App\Modules\Contenidos\Jobs\PurgarPapelera;
use App\Modules\Evaluaciones\Jobs\AvisarRevisionesDisponibles;
use App\Modules\Evaluaciones\Jobs\CerrarIntentosVencidos;
use App\Modules\Reportes\Jobs\EnviarReportesProgramados;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cierra los intentos de cuestionario vencidos sin envío (RF16, ADR-042).
Schedule::job(new CerrarIntentosVencidos)->everyMinute()->withoutOverlapping();

// Avisa cuando la corrección de un cuestionario queda visible (RF16, ADR-040).
Schedule::job(new AvisarRevisionesDisponibles)->everyMinute()->withoutOverlapping();

// Borra los tokens del QR de asistencia vencidos hace más de 7 días (RF20, ADR-028).
Schedule::job(new PurgarTokensQr)->dailyAt('03:00')->withoutOverlapping();

// Vacía la papelera de contenidos: lo eliminado hace más de 30 días y su archivo del disco (RF11).
Schedule::job(new PurgarPapelera)->dailyAt('03:30')->withoutOverlapping();

// Envía por correo los reportes programados que ya tocan: semanales los lunes y mensuales el día 1, a las 7:00 (RF25).
Schedule::job(new EnviarReportesProgramados)->hourly()->withoutOverlapping();
