<?php

use App\Modules\Asistencia\Services\ReporteMeetService;

/**
 * Cálculos puros del reporte de Meet (RF21, ADR-003): la duración de cada fila en segundos y la fecha de la reunión.
 * El redondeo a minutos y la suma de reconexiones se prueban en tests/Feature/Asistencia/ReporteMeetTest.php.
 */
it('convierte a segundos la duración en los formatos que exporta Meet', function (string $duracion, int $segundos) {
    expect(ReporteMeetService::duracionEnSegundos($duracion))->toBe($segundos);
})->with([
    'horas y minutos' => ['1 h 23 min', 4980],
    'hr abreviado' => ['1 hr 23 min', 4980],
    'en palabras' => ['1 hora 5 minutos', 3900],
    'solo minutos' => ['45 min', 2700],
    'solo segundos' => ['30 s', 30],
    'h:m:s' => ['1:23:45', 5025],
    'm:s' => ['23:45', 1425],
    'fracción de día de Excel' => ['0.0576388889', 4980],
]);

it('devuelve null si la duración está vacía o no se entiende', function (string $duracion) {
    expect(ReporteMeetService::duracionEnSegundos($duracion))->toBeNull();
})->with(['vacía' => [''], 'texto' => ['sin dato']]);

it('obtiene el día de la reunión en los formatos de fecha del reporte', function (string $valor) {
    expect(ReporteMeetService::fechaDe($valor))->toBe('2026-10-23');
})->with([
    'Y-m-d con hora' => ['2026-10-23 07:02:00'],
    'd/m/Y' => ['23/10/2026'],
    'día y mes abreviado' => ['23 oct 2026'],
    'mes en inglés' => ['Oct 23, 2026'],
    'serie de Excel' => ['46318'],
]);

it('no inventa una fecha a partir de una hora sola', function () {
    expect(ReporteMeetService::fechaDe('07:02'))->toBeNull();
});

it('obtiene el día del nombre con que Google guarda el informe de asistencia', function (string $nombre) {
    expect(ReporteMeetService::fechaDeNombre($nombre))->toBe('2026-10-23');
})->with([
    'con dos puntos' => ['Informe de asistencia a poc-tbuu-xpb (2026-10-23 21:49).xlsx'],
    'guardado en Windows' => ['Informe de asistencia a poc-tbuu-xpb (2026-10-23 21_49).xlsx'],
    'en inglés' => ['Attendance report for abc-defg-hij (2026-10-23 09.05).csv'],
    'fecha suelta en el nombre' => ['asistencia 2026-10-23.csv'],
]);

it('devuelve null si el nombre del archivo no trae fecha', function (string $nombre) {
    expect(ReporteMeetService::fechaDeNombre($nombre))->toBeNull();
})->with([
    'sin marca' => ['reporte.csv'],
    'solo el código de la reunión' => ['Informe de asistencia a poc-tbuu-xpb.xlsx'],
]);
