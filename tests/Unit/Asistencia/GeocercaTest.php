<?php

use App\Modules\Asistencia\Services\GeocercaService;

/**
 * Geometría de las áreas por polígono (RF26). Un cuadrado de unos 200 m de lado alrededor del campus FMO:
 * 0,0009° de latitud son unos 100 m.
 */
function cuadradoFmo(): array
{
    return [[13.4384, -88.1606], [13.4384, -88.1588], [13.4402, -88.1588], [13.4402, -88.1606]];
}

it('da distancia 0 a un punto dentro del polígono y en su borde', function (float $lat, float $lng) {
    expect((new GeocercaService)->distancia($lat, $lng, cuadradoFmo()))->toBe(0.0);
})->with([
    'centro' => [13.4393, -88.1597],
    'cerca de una esquina' => [13.4385, -88.1605],
    'sobre un lado' => [13.4384, -88.1597],
    'sobre un vértice' => [13.4402, -88.1588],
]);

it('mide en metros la distancia al lado más cercano de un punto de afuera', function () {
    // 0,0009° de latitud al norte del lado superior: unos 100 m.
    expect((new GeocercaService)->distancia(13.4411, -88.1597, cuadradoFmo()))->toBeGreaterThan(98.0)->toBeLessThan(102.0);
});

it('distingue el hueco de un polígono cóncavo', function () {
    // Una «U»: el hueco central queda fuera aunque esté entre los dos brazos.
    $u = [[13.4384, -88.1606], [13.4384, -88.1588], [13.4402, -88.1588], [13.4402, -88.1594], [13.4390, -88.1594], [13.4390, -88.1600], [13.4402, -88.1600], [13.4402, -88.1606]];
    $geocerca = new GeocercaService;

    expect($geocerca->distancia(13.4398, -88.1597, $u))->toBeGreaterThan(0.0)
        ->and($geocerca->distancia(13.4398, -88.1603, $u))->toBe(0.0);
});

it('detecta los lados que se cruzan y acepta los polígonos simples', function () {
    $geocerca = new GeocercaService;
    $ocho = [[13.4384, -88.1606], [13.4402, -88.1588], [13.4384, -88.1588], [13.4402, -88.1606]];

    expect($geocerca->seCruza($ocho))->toBeTrue()
        ->and($geocerca->seCruza(cuadradoFmo()))->toBeFalse();
});

it('ubica el centroide en el centro del cuadrado', function () {
    [$lat, $lng] = (new GeocercaService)->centroide(cuadradoFmo());

    expect(round($lat, 4))->toBe(13.4393)->and(round($lng, 4))->toBe(-88.1597);
});
