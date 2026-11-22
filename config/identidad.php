<?php

/*
|--------------------------------------------------------------------------
| Identidad: cuenta superadministradora (ADR-082)
|--------------------------------------------------------------------------
|
| La contraseña nunca va en el código: se lee de SUPERADMIN_PASSWORD. Sin ella,
| `php artisan superadmin:crear` no crea la cuenta.
|
*/

return [

    'superadmin' => [
        'email' => env('SUPERADMIN_EMAIL', 'superadmin@ues.edu.sv'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

];
