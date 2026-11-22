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
    | Escenarios de prueba
    |--------------------------------------------------------------------------
    |
    | Preparar un escenario siembra datos y asigna roles, así que en staging no
    | puede quedar al alcance de cualquiera que abra la URL. Con esta clave
    | puesta, el wizard la pide una vez por sesión; sin ella, solo un
    | Administrador con sesión iniciada puede preparar o limpiar escenarios.
    | Ver la página del escenario (enlaces y checklist) nunca pide clave: el
    | código del escenario, que es largo y no se adivina, hace de permiso.
    |
    */

    'escenarios_clave' => (string) env('LMS_ESCENARIOS_CLAVE', ''),

    /*
    |--------------------------------------------------------------------------
    | Perfil de siembra
    |--------------------------------------------------------------------------
    |
    | «demo» (por omisión): personas inventadas con volumen realista y datos de
    | uso (notas, asistencia, foros). «equipo»: la misma estructura, pero las
    | cuentas son las del equipo (seeders/data/testers.php) con su correo real,
    | más admin.dev; los roles se reparten al azar con LMS_SEED_SEMILLA y no hay
    | notas ni asistencia inventadas. La cuenta de emergencia (ADR-007) solo se
    | siembra si el .env trae su correo y su contraseña. Fuera de producción.
    |
    */

    'seed_perfil' => (string) env('LMS_SEED_PERFIL', 'demo'),

    'seed_semilla' => (int) env('LMS_SEED_SEMILLA', 2026),

    'emergencia' => [
        'correo' => (string) env('LMS_EMERGENCIA_CORREO', ''),
        'clave' => (string) env('LMS_EMERGENCIA_CLAVE', ''),
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Horario de la asistencia (RF19, ADR-075)
    |--------------------------------------------------------------------------
    |
    | La sesión se abre sin más dentro del horario de la sección, con este
    | margen antes del inicio y después del fin; fuera de él, el docente debe
    | dar un motivo, que queda en la auditoría. ASIS_BYPASS_WEEKEND=true quita
    | la restricción para probar en fin de semana o fuera de horario; en
    | producción se ignora.
    |
    */

    'asistencia' => [
        'margen_horario_minutos' => (int) env('ASIS_MARGEN_HORARIO', 15),
        'bypass_horario' => (bool) env('ASIS_BYPASS_WEEKEND', false),
    ],

];
