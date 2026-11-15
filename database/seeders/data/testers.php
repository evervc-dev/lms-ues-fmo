<?php

/*
|--------------------------------------------------------------------------
| Testers del equipo (escenarios de prueba)
|--------------------------------------------------------------------------
|
| Los 20 integrantes del script de la migración colaborativa
| (`scripts/plan_migracion.json` del repositorio de origen), con su DUE y su
| correo institucional. El wizard de escenarios reparte los roles entre estas
| personas y el motor siembra sus cuentas con el correo REAL, porque de eso
| depende que les lleguen las invitaciones de Google Calendar y Meet.
|
| Las cuentas no se siembran de entrada: `EscenarioService::testers()` las crea
| la primera vez que alguien reparte un rol, y reutiliza la que ya exista por
| su correo. Solo se usa fuera de producción.
|
*/

return [
    'cp22010' => ['nombre' => 'Luis Alejandro', 'apellido' => 'Cruz Portillo'],
    'da22015' => ['nombre' => 'Gabriel Enrique', 'apellido' => 'De la O Aguirre'],
    'gp22006' => ['nombre' => 'Kevin Nathanael', 'apellido' => 'Granados Pérez'],
    'hp19012' => ['nombre' => 'German Daniel', 'apellido' => 'Hernández Pinto'],
    'jo21003' => ['nombre' => 'Yoselin Aracely', 'apellido' => 'Joya Ortéz'],
    'll22017' => ['nombre' => 'Rafael Humberto', 'apellido' => 'Lino Lovo'],
    'mc18055' => ['nombre' => 'Jesús Steven', 'apellido' => 'Medrano Carballo'],
    'mj21008' => ['nombre' => 'José Antonio', 'apellido' => 'Martínez Jovel'],
    'mp20002' => ['nombre' => 'Denis Alexander', 'apellido' => 'Martel Portillo'],
    'mp20049' => ['nombre' => 'Jesse Antonio', 'apellido' => 'Miranda Perez'],
    'qa21002' => ['nombre' => 'Adán Omar', 'apellido' => 'Quevedo Argueta'],
    'rg22016' => ['nombre' => 'Cristian Geovanny', 'apellido' => 'Rubio García'],
    'rm21077' => ['nombre' => 'Alexander Antolino', 'apellido' => 'Retana Medina'],
    'ro22024' => ['nombre' => 'Santos Josue', 'apellido' => 'Romero Ochoa'],
    'rq22004' => ['nombre' => 'Meybell Jacqueline', 'apellido' => 'Ramirez Quintanilla'],
    'rr19118' => ['nombre' => 'Kevin Israel', 'apellido' => 'Rodríguez Rivera'],
    'sa16028' => ['nombre' => 'Eduardo Alexis', 'apellido' => 'Sandoval Aparicio'],
    'te22003' => ['nombre' => 'Arturo Elías', 'apellido' => 'Torres Esperanza'],
    'vc21033' => ['nombre' => 'Ever', 'apellido' => 'Vásquez'],
    'vg22007' => ['nombre' => 'Cristina Raquel', 'apellido' => 'Ventura González'],
];
