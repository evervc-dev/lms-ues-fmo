<?php

use App\Support\Procesos;
use Symfony\Component\Process\Process;

/**
 * Procesos::detener() mata el árbol entero, no solo al hijo directo: `artisan serve` lanza `php -S` y
 * `bun run dev` lanza `node vite`, y si sobreviven ocupan su puerto y rompen el siguiente `php artisan dev`.
 *
 * Usa `sleep` con un número raro para reconocer a sus procesos sin tocar los de nadie más.
 */
function sleepsVivos(string $marca): int
{
    $busca = new Process(['pgrep', '-xfc', "sleep {$marca}"]);
    $busca->run();

    return (int) trim($busca->getOutput());
}

function arbolConNietos(string $marca): Process
{
    // Un padre (sh) con dos nietos (sleep), como serve → php -S.
    $padre = new Process(['sh', '-c', "sleep {$marca} & sleep {$marca} & wait"]);
    $padre->start();

    // Espera a que los nietos existan de verdad antes de empezar.
    $limite = microtime(true) + 5;
    while (sleepsVivos($marca) < 2 && microtime(true) < $limite) {
        usleep(50_000);
    }

    return $padre;
}

afterEach(fn () => (new Process(['pkill', '-xf', 'sleep 6131']))->run());

it('detiene al proceso y a todos sus descendientes', function () {
    $padre = arbolConNietos('6131');
    expect(sleepsVivos('6131'))->toBe(2);

    Procesos::detener($padre, 2);

    expect($padre->isRunning())->toBeFalse()
        ->and(sleepsVivos('6131'))->toBe(0);
})->skip(PHP_OS_FAMILY === 'Windows', 'Windows mata el árbol por su cuenta.');

it('encuentra los descendientes antes de que el padre muera', function () {
    $padre = arbolConNietos('6131');

    $descendientes = Procesos::descendientes((int) $padre->getPid());

    expect($descendientes)->toHaveCount(2)
        ->and($descendientes)->not->toContain((int) $padre->getPid());

    Procesos::detener($padre, 2);
})->skip(PHP_OS_FAMILY === 'Windows', 'ps no existe en Windows.');

it('nunca devuelve el sistema entero como descendientes de init', function () {
    expect(Procesos::descendientes(0))->toBe([])
        ->and(Procesos::descendientes(1))->toBe([]);
});

it('no falla con un proceso que ya terminó', function () {
    $terminado = new Process(['true']);
    $terminado->run();

    Procesos::detener($terminado, 1);

    expect($terminado->isRunning())->toBeFalse();
});
