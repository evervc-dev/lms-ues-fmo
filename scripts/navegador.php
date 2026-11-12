<?php

/**
 * Corre las pruebas de navegador (Pest Browser). Pest no acepta flags propios, así que este script traduce
 * `--speed=N` (segundos de pausa antes de cada paso, de 0,1 a 5; por ejemplo 1, 1.5 o 2) a la variable
 * VELOCIDAD_NAVEGADOR y abre la ventana (`--headed`). El resto de los argumentos pasan a Pest tal cual.
 *
 *   php scripts/navegador.php tests/Browser/FlujoExamenTest.php --speed=1.5
 *   composer test:browser -- --filter='ask to review' --speed=2
 */
$argumentos = array_slice($argv, 1);
$velocidad = null;

foreach ($argumentos as $i => $argumento) {
    if (str_starts_with($argumento, '--speed=')) {
        $velocidad = str_replace(',', '.', substr($argumento, strlen('--speed=')));
        unset($argumentos[$i]);
    }
}

if ($velocidad !== null && (! is_numeric($velocidad) || $velocidad < 0.1 || $velocidad > 5)) {
    fwrite(STDERR, "--speed debe ser un número de segundos entre 0.1 y 5 (por ejemplo 1, 1.5 o 2).\n");
    exit(2);
}

$rutas = array_filter($argumentos, fn (string $argumento) => ! str_starts_with($argumento, '-') && (is_file($argumento) || is_dir($argumento)));
if ($rutas === []) {
    $argumentos[] = 'tests/Browser';
}
if ($velocidad !== null && ! in_array('--headed', $argumentos, true)) {
    $argumentos[] = '--headed';
}

$proceso = proc_open(
    [PHP_BINARY, 'vendor/bin/pest', ...array_values($argumentos)],
    [STDIN, STDOUT, STDERR],
    $tuberias,
    dirname(__DIR__),
    $velocidad !== null ? [...getenv(), 'VELOCIDAD_NAVEGADOR' => (string) (float) $velocidad] : null,
);

exit(proc_close($proceso));
