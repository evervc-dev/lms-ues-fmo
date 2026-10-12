<?php

use App\Providers\AcademicaServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AsistenciaServiceProvider;
use App\Providers\AuditoriaServiceProvider;
use App\Providers\CalificacionesServiceProvider;
use App\Providers\ComunicacionServiceProvider;
use App\Providers\ContenidosServiceProvider;
use App\Providers\EvaluacionesServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\IdentidadServiceProvider;
use App\Providers\NavegacionServiceProvider;
use App\Providers\ReportesServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    AcademicaServiceProvider::class,
    AsistenciaServiceProvider::class,
    AuditoriaServiceProvider::class,
    CalificacionesServiceProvider::class,
    ComunicacionServiceProvider::class,
    ContenidosServiceProvider::class,
    EvaluacionesServiceProvider::class,
    IdentidadServiceProvider::class,
    ReportesServiceProvider::class,
    // Siempre al final: registra las vistas pendientes del registro.
    NavegacionServiceProvider::class,
];
