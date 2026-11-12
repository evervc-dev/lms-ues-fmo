<?php

use App\Support\Nombre;
use Tests\TestCase;

/* Los mensajes pasan por __(): necesitan la aplicación arrancada. */
uses(TestCase::class);

/**
 * Formato de los nombres de las cuentas (ADR-060, QA sesión 4): nombre propio, con las partículas en minúscula, y
 * sin palabras de más de 25 letras ni más de 60 caracteres por campo.
 */
it('CP-RF04-23 guarda los nombres como nombre propio, con las partículas en minúscula', function (string $escrito, string $guardado) {
    expect(Nombre::normalizar($escrito))->toBe($guardado);
})->with([
    'minúsculas' => ['ana maría', 'Ana María'],
    'mayúsculas y tildes' => ['ÁNGELES ÑÚÑEZ', 'Ángeles Ñúñez'],
    'partículas' => ['maría DE LOS ángeles', 'María de los Ángeles'],
    'partícula al inicio' => ['del cid', 'Del Cid'],
    'espacios de más' => ['  josé    antonio ', 'José Antonio'],
    'guion' => ['ana-lucía', 'Ana-Lucía'],
]);

it('CP-RF04-23 rechaza una palabra de más de 25 letras o un campo de más de 60 caracteres', function () {
    expect(Nombre::error('María de los Ángeles'))->toBeNull()
        ->and(Nombre::error(str_repeat('a', 25)))->toBeNull()
        ->and(Nombre::error('Rocío '.str_repeat('m', 80)))->toContain('más de 25 letras')
        ->and(Nombre::error(implode(' ', array_fill(0, 13, 'Juan'))))->not->toBeNull()
        ->and(Nombre::error(implode(' ', array_fill(0, 12, 'Ana'))))->toBeNull();
});
