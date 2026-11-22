<?php

use App\Support\Nombre;
use Tests\TestCase;

/* Los mensajes pasan por __(): necesitan la aplicación arrancada. */
uses(TestCase::class);

/**
 * Formato de los nombres de las cuentas (ADR-060, ADR-084, QA sesión 4): nombre propio si vienen todo en mayúsculas
 * o todo en minúsculas, con las partículas en minúscula; respetados si traen mayúsculas mezcladas; y sin palabras de
 * más de 25 letras ni más de 60 caracteres por campo.
 */
it('CP-RF04-23 guarda los nombres como nombre propio, con las partículas en minúscula', function (string $escrito, string $guardado) {
    expect(Nombre::normalizar($escrito))->toBe($guardado);
})->with([
    'minúsculas' => ['ana maría', 'Ana María'],
    'mayúsculas y tildes' => ['ÁNGELES ÑÚÑEZ', 'Ángeles Ñúñez'],
    'partículas' => ['MARÍA DE LOS ÁNGELES', 'María de los Ángeles'],
    'partícula al inicio' => ['del cid', 'Del Cid'],
    'espacios de más' => ['  josé    antonio ', 'José Antonio'],
    'guion' => ['ana-lucía', 'Ana-Lucía'],
]);

it('CP-RF04-23 respeta los nombres escritos con mayúsculas mezcladas', function (string $escrito, string $guardado) {
    expect(Nombre::normalizar($escrito))->toBe($guardado);
})->with([
    'sigla' => ['Revisión UX', 'Revisión UX'],
    'apellido compuesto' => ['McDonald', 'McDonald'],
    'partícula elegida' => ['María De Los Ángeles', 'María De Los Ángeles'],
    'espacios de más' => ['  Ana   McKay ', 'Ana McKay'],
]);

it('CP-RF04-23 rechaza una palabra de más de 25 letras o un campo de más de 60 caracteres', function () {
    expect(Nombre::error('María de los Ángeles'))->toBeNull()
        ->and(Nombre::error(str_repeat('a', 25)))->toBeNull()
        ->and(Nombre::error('Rocío '.str_repeat('m', 80)))->toContain('más de 25 letras')
        ->and(Nombre::error(implode(' ', array_fill(0, 13, 'Juan'))))->not->toBeNull()
        ->and(Nombre::error(implode(' ', array_fill(0, 12, 'Ana'))))->toBeNull();
});
