<?php

namespace Tests\Support;

use RuntimeException;
use ZipArchive;

/**
 * Genera en las pruebas un libro .xlsx mínimo, como el que exporta Google Sheets, sin fixtures en el repositorio.
 *
 * Celdas: un string va a sharedStrings; un int o float, como número; `['inline' => 'texto']`, como inlineStr;
 * null deja la celda sin escribir (el xlsx omite las celdas vacías). La primera hoja del libro se guarda en el
 * archivo de mayor número (`sheetN.xml`), así que solo la encuentra quien sigue workbook.xml y sus rels.
 */
final class HojaXlsx
{
    /**
     * @param  list<list<list<string|int|float|array{inline: string}|null>>>  $hojas  hojas en el orden del libro
     * @return string ruta del archivo temporal creado
     */
    public static function crear(array $hojas): string
    {
        $base = (string) tempnam(sys_get_temp_dir(), 'hoja');
        @unlink($base);
        $ruta = $base.'.xlsx';
        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el xlsx de prueba.');
        }

        $compartidos = [];
        $total = count($hojas);
        $libro = $rels = $tipos = '';
        foreach (array_values($hojas) as $i => $filas) {
            $numero = $total - $i;
            $libro .= '<sheet name="Hoja'.($i + 1).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
            $rels .= '<Relationship Id="rId'.($i + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$numero.'.xml"/>';
            $tipos .= '<Override PartName="/xl/worksheets/sheet'.$numero.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $zip->addFromString('xl/worksheets/sheet'.$numero.'.xml', self::hoja($filas, $compartidos));
        }
        $rels .= '<Relationship Id="rId'.($total + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .$tipos.'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$libro.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($compartidos).'" uniqueCount="'.count($compartidos).'">'
            .implode('', array_map(fn (string $texto) => '<si><t xml:space="preserve">'.self::xml($texto).'</t></si>', array_keys($compartidos)))
            .'</sst>');
        $zip->close();

        return $ruta;
    }

    /**
     * @param  list<list<string|int|float|array{inline: string}|null>>  $filas
     * @param  array<string, int>  $compartidos
     */
    private static function hoja(array $filas, array &$compartidos): string
    {
        $xml = '';
        foreach (array_values($filas) as $f => $celdas) {
            $fila = $f + 1;
            $xml .= '<row r="'.$fila.'">';
            foreach (array_values($celdas) as $c => $valor) {
                $ref = self::columna($c).$fila;
                if ($valor === null) {
                    continue;
                }
                if (is_array($valor)) {
                    $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.self::xml($valor['inline']).'</t></is></c>';
                } elseif (is_int($valor) || is_float($valor)) {
                    $xml .= '<c r="'.$ref.'"><v>'.$valor.'</v></c>';
                } else {
                    $compartidos[$valor] ??= count($compartidos);
                    $xml .= '<c r="'.$ref.'" t="s"><v>'.$compartidos[$valor].'</v></c>';
                }
            }
            $xml .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xml.'</sheetData></worksheet>';
    }

    private static function columna(int $indice): string
    {
        $letras = '';
        for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letras = chr(65 + ($n - 1) % 26).$letras;
        }

        return $letras;
    }

    private static function xml(string $texto): string
    {
        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
