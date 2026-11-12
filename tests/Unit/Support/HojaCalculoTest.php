<?php

use App\Support\HojaCalculo;
use Tests\Support\HojaXlsx;

/**
 * Lector de hojas del reporte de Meet (RF21, ADR-048): CSV con cualquier separador y la primera hoja de un xlsx.
 * Sin framework: solo PHP, ZipArchive y XMLReader.
 */
$temporales = [];

afterEach(function () use (&$temporales) {
    foreach ($temporales as $ruta) {
        @unlink($ruta);
    }
    $temporales = [];
});

$archivo = function (string $contenido, string $extension) use (&$temporales): string {
    $base = (string) tempnam(sys_get_temp_dir(), 'leer');
    @unlink($base);
    $ruta = $base.'.'.$extension;
    file_put_contents($ruta, $contenido);

    return $temporales[] = $ruta;
};

it('lee un CSV separado por punto y coma, quita el BOM y recorta las celdas', function () use ($archivo) {
    $ruta = $archivo("\xEF\xBB\xBFNombre;Correo;Duración\r\n Ana López ; ana@ues.edu.sv ;45 min", 'csv');

    expect(HojaCalculo::leer($ruta, 'csv'))->toBe([
        ['Nombre', 'Correo', 'Duración'],
        ['Ana López', 'ana@ues.edu.sv', '45 min'],
    ]);
});

it('lee un CSV separado por comas con un campo entre comillas que contiene comas', function () use ($archivo) {
    $ruta = $archivo("Nombre,Correo,Duración\n\"López, Ana\",ana@ues.edu.sv,1 h 5 min", 'csv');

    expect(HojaCalculo::leer($ruta, 'csv'))->toBe([
        ['Nombre', 'Correo', 'Duración'],
        ['López, Ana', 'ana@ues.edu.sv', '1 h 5 min'],
    ]);
});

it('lee un archivo de texto separado por tabuladores', function () use ($archivo) {
    $ruta = $archivo("Nombre\tCorreo\tDuración\nAna\tana@ues.edu.sv\t1:05:00", 'txt');

    expect(HojaCalculo::leer($ruta, 'txt'))->toBe([
        ['Nombre', 'Correo', 'Duración'],
        ['Ana', 'ana@ues.edu.sv', '1:05:00'],
    ]);
});

it('lee la primera hoja de un xlsx con sharedStrings, inlineStr y números', function () use (&$temporales) {
    $ruta = $temporales[] = HojaXlsx::crear([
        [
            ['Nombre', 'Correo', 'Duración'],
            [['inline' => 'Ana López'], 'ana@ues.edu.sv', 0.0576388889],
            ['Mario Rivas', 'mr21011@ues.edu.sv', 45],
        ],
        [['Otra hoja que no se lee']],
    ]);

    expect(HojaCalculo::leer($ruta, 'xlsx'))->toBe([
        ['Nombre', 'Correo', 'Duración'],
        ['Ana López', 'ana@ues.edu.sv', '0.0576388889'],
        ['Mario Rivas', 'mr21011@ues.edu.sv', '45'],
    ]);
});

it('respeta la columna de cada celda cuando el xlsx omite una celda vacía', function () use (&$temporales) {
    $ruta = $temporales[] = HojaXlsx::crear([[
        ['Nombre', 'Correo', 'Duración'],
        ['Invitado', null, '50 min'],
    ]]);

    expect(HojaCalculo::leer($ruta, 'xlsx')[1])->toBe(['Invitado', '', '50 min']);
});

it('lanza RuntimeException si el archivo no se puede leer o no existe', function () use ($archivo) {
    $corrupto = $archivo('esto no es un libro de cálculo', 'xlsx');

    expect(fn () => HojaCalculo::leer($corrupto, 'xlsx'))->toThrow(RuntimeException::class)
        ->and(fn () => HojaCalculo::leer(sys_get_temp_dir().'/no-existe-'.uniqid().'.csv', 'csv'))->toThrow(RuntimeException::class);
});
