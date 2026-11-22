<?php

use App\Support\Busqueda;

/**
 * Regla común de búsqueda: sin mayúsculas, tildes, diéresis ni eñe (Bitácora 21/11/2026).
 */
it('normaliza mayúsculas, tildes, diéresis y la eñe', function () {
    expect(Busqueda::normalizar('  NÚÑEZ Pingüino Ángel  '))->toBe('nunez pinguino angel')
        ->and(Busqueda::normalizar('Programación'))->toBe(Busqueda::normalizar('programacion'));
});

it('neutraliza los comodines de LIKE en el patrón sin tildes', function () {
    expect(Busqueda::fragmentoSinTildes('50%_Ñ'))->toBe('%50\%\_n%');
});
