<?php

it('lists the server, the workers, the scheduler and vite', function () {
    $this->artisan('dev', ['--dry-run' => true])
        ->expectsOutputToContain('[web]')
        ->expectsOutputToContain('[workers]')
        ->expectsOutputToContain('[scheduler]')
        ->expectsOutputToContain('[vite]')
        ->doesntExpectOutputToContain('[reverb]')
        ->assertSuccessful();
});

it('adds reverb only when asked', function () {
    $this->artisan('dev', ['--dry-run' => true, '--reverb' => true])
        ->expectsOutputToContain('[reverb]')
        ->assertSuccessful();
});

it('uses queue:listen for the workers when the queue is not redis', function () {
    config(['queue.default' => 'sync']);

    $this->artisan('dev', ['--dry-run' => true])
        ->expectsOutputToContain("'queue:listen' 'sync' '--queue=notificaciones,default,google,importaciones,reportes' '--tries=3'")
        ->assertSuccessful();
});

it('uses horizon for the workers on linux with redis', function () {
    config(['queue.default' => 'redis']);

    $this->artisan('dev', ['--dry-run' => true])
        ->expectsOutputToContain('horizon')
        ->assertSuccessful();
})->skip(PHP_OS_FAMILY === 'Windows', 'Horizon necesita pcntl, que Windows no tiene.');

/**
 * Cuidado al tocar `puertoLibre()` en Dev: si la comprobación se rompe, la prueba de abajo deja de fallar rápido y
 * **ejecuta el comando de verdad**, que arranca servidor, Horizon, scheduler y Vite y se queda esperando.
 */
it('no levanta nada y explica por qué si el puerto de Reverb está ocupado', function () {
    // Se ocupa de verdad un puerto, como lo hace el proxy de Coolify con el 8080.
    $ocupado = stream_socket_server('tcp://0.0.0.0:0');
    $puerto = (int) substr(strrchr(stream_socket_get_name($ocupado, false), ':'), 1);
    config(['reverb.servers.reverb.port' => $puerto]);

    $this->artisan('dev', ['--reverb' => true])
        ->expectsOutputToContain("El puerto {$puerto} de Reverb ya está en uso")
        ->assertFailed();

    fclose($ocupado);
});

it('con el puerto de Reverb libre sigue adelante', function () {
    $libre = stream_socket_server('tcp://0.0.0.0:0');
    $puerto = (int) substr(strrchr(stream_socket_get_name($libre, false), ':'), 1);
    fclose($libre);
    config(['reverb.servers.reverb.port' => $puerto]);

    // --dry-run corta antes de la comprobación: aquí solo se ve que el puerto libre no lo estorba.
    $this->artisan('dev', ['--reverb' => true, '--dry-run' => true])
        ->expectsOutputToContain('[reverb]')
        ->assertSuccessful();
});

it('la comprobación del puerto solo aplica con --reverb', function () {
    $ocupado = stream_socket_server('tcp://0.0.0.0:0');
    $puerto = (int) substr(strrchr(stream_socket_get_name($ocupado, false), ':'), 1);
    config(['reverb.servers.reverb.port' => $puerto]);

    $this->artisan('dev', ['--dry-run' => true])->doesntExpectOutputToContain('ya está en uso')->assertSuccessful();

    fclose($ocupado);
});
