<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inicio de sesión de desarrollo
    |--------------------------------------------------------------------------
    |
    | «Entrar como…» permite elegir un usuario sembrado sin Google. Solo existe
    | en local y staging; en producción se ignora aunque esté activo
    | (docs/12 Desarrollo/Setup del entorno.md).
    |
    */

    'dev_login' => (bool) env('LMS_DEV_LOGIN', false),

    /*
    |--------------------------------------------------------------------------
    | Área de asistencia de los seeders
    |--------------------------------------------------------------------------
    |
    | El área circular que siembra InstitutionSeeder (RF20, RF26). Por defecto
    | es el campus de la FMO; para probar el QR desde otro lugar, ponga sus
    | coordenadas en su .env (no en el repositorio) y vuelva a sembrar.
    |
    */

    'area_asistencia' => [
        'nombre' => env('LMS_AREA_NOMBRE', 'Campus UES FMO (San Miguel)'),
        'lat' => (float) env('LMS_AREA_LAT', 13.4393),
        'lng' => (float) env('LMS_AREA_LNG', -88.1597),
        'radio' => (int) env('LMS_AREA_RADIO', 450),
    ],

];
