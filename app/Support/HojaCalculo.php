<?php

namespace App\Support;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Lector mínimo de hojas de cálculo (ADR-048): CSV y la primera hoja de un .xlsx, sin dependencias.
 *
 * Devuelve las filas como listas de celdas de texto (con trim). Las filas vacías se omiten. Las fechas y las
 * duraciones que Excel guarda como número de serie se devuelven tal cual: las interpreta quien las usa.
 */
final class HojaCalculo
{
    private const NS_RELACIONES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @return list<list<string>>
     *
     * @throws RuntimeException si el archivo no se puede leer.
     */
    public static function leer(string $ruta, string $extension): array
    {
        if (! is_file($ruta) || ! is_readable($ruta)) {
            throw new RuntimeException("No se puede leer el archivo {$ruta}.");
        }

        return match (strtolower(ltrim($extension, '.'))) {
            'csv', 'txt' => self::csv($ruta),
            'xlsx' => self::xlsx($ruta),
            default => throw new RuntimeException("Formato no admitido: {$extension}."),
        };
    }

    /**
     * @return list<list<string>>
     */
    private static function csv(string $ruta): array
    {
        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            throw new RuntimeException("No se puede leer el archivo {$ruta}.");
        }
        $contenido = self::aUtf8($contenido);

        $muestra = implode("\n", array_slice(preg_split('/\r\n|\r|\n/', $contenido) ?: [], 0, 20));
        $conteos = [',' => substr_count($muestra, ','), ';' => substr_count($muestra, ';'), "\t" => substr_count($muestra, "\t")];
        arsort($conteos);
        $separador = (string) array_key_first($conteos);

        $flujo = fopen('php://temp', 'r+');
        if ($flujo === false) {
            throw new RuntimeException('No se pudo abrir un flujo temporal.');
        }
        fwrite($flujo, $contenido);
        rewind($flujo);

        $filas = [];
        while (($linea = fgetcsv($flujo, null, $separador, '"', '')) !== false) {
            $celdas = array_map(fn ($c) => trim((string) $c), $linea);
            if (self::vacia($celdas)) {
                continue;
            }
            $filas[] = $celdas;
        }
        fclose($flujo);

        return $filas;
    }

    /**
     * @return list<list<string>>
     */
    private static function xlsx(string $ruta): array
    {
        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException("El archivo {$ruta} no es un libro de Excel (.xlsx) válido.");
        }

        try {
            $hoja = self::rutaPrimeraHoja($zip);
            $xml = $zip->getFromName($hoja);
            if ($xml === false) {
                throw new RuntimeException("El libro no tiene la hoja {$hoja}.");
            }
            $compartidas = self::cadenasCompartidas($zip);

            return self::filasDeHoja($xml, $compartidas);
        } finally {
            $zip->close();
        }
    }

    /**
     * Ruta de la primera hoja según xl/workbook.xml y sus relaciones; si faltan, xl/worksheets/sheet1.xml.
     */
    private static function rutaPrimeraHoja(ZipArchive $zip): string
    {
        $porDefecto = 'xl/worksheets/sheet1.xml';
        $libro = $zip->getFromName('xl/workbook.xml');
        $relaciones = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($libro === false || $relaciones === false) {
            return $porDefecto;
        }

        $id = null;
        $lector = self::lector($libro);
        while ($lector->read()) {
            if ($lector->nodeType === XMLReader::ELEMENT && $lector->localName === 'sheet') {
                $id = $lector->getAttributeNs('id', self::NS_RELACIONES);
                break;
            }
        }
        $lector->close();
        if (! $id) {
            return $porDefecto;
        }

        $lector = self::lector($relaciones);
        while ($lector->read()) {
            if ($lector->nodeType === XMLReader::ELEMENT && $lector->localName === 'Relationship' && $lector->getAttribute('Id') === $id) {
                $destino = (string) $lector->getAttribute('Target');
                $lector->close();

                return str_starts_with($destino, '/') ? ltrim($destino, '/') : 'xl/'.ltrim($destino, './');
            }
        }
        $lector->close();

        return $porDefecto;
    }

    /**
     * @return list<string>
     */
    private static function cadenasCompartidas(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $cadenas = [];
        $actual = null;
        $fonetica = 0;
        $lector = self::lector($xml);
        while ($lector->read()) {
            if ($lector->nodeType === XMLReader::ELEMENT) {
                if ($lector->localName === 'si') {
                    $actual = '';
                    if ($lector->isEmptyElement) {
                        $cadenas[] = '';
                        $actual = null;
                    }
                } elseif ($lector->localName === 'rPh' && ! $lector->isEmptyElement) {
                    $fonetica++;
                } elseif ($lector->localName === 't' && $actual !== null && $fonetica === 0 && ! $lector->isEmptyElement) {
                    $actual .= $lector->readString();
                }
            } elseif ($lector->nodeType === XMLReader::END_ELEMENT) {
                if ($lector->localName === 'rPh') {
                    $fonetica = max(0, $fonetica - 1);
                } elseif ($lector->localName === 'si' && $actual !== null) {
                    $cadenas[] = $actual;
                    $actual = null;
                }
            }
        }
        $lector->close();

        return $cadenas;
    }

    /**
     * @param  list<string>  $compartidas
     * @return list<list<string>>
     */
    private static function filasDeHoja(string $xml, array $compartidas): array
    {
        $filas = [];
        $fila = null;
        $celda = null;
        $lector = self::lector($xml);

        while ($lector->read()) {
            if ($lector->nodeType === XMLReader::ELEMENT) {
                switch ($lector->localName) {
                    case 'row':
                        $fila = [];
                        if ($lector->isEmptyElement) {
                            $fila = null;
                        }
                        break;
                    case 'c':
                        if ($fila === null) {
                            break;
                        }
                        $referencia = (string) $lector->getAttribute('r');
                        $celda = ['col' => $referencia !== '' ? self::columna($referencia) : count($fila), 'tipo' => (string) $lector->getAttribute('t'), 'valor' => ''];
                        if ($lector->isEmptyElement) {
                            $fila[$celda['col']] = '';
                            $celda = null;
                        }
                        break;
                    case 'v':
                        if ($celda !== null && ! $lector->isEmptyElement) {
                            $celda['valor'] = $lector->readString();
                        }
                        break;
                    case 't':
                        // Cadena en línea (t="inlineStr"): <is><t>…</t></is>, quizá en varios fragmentos <r>.
                        if ($celda !== null && $celda['tipo'] === 'inlineStr' && ! $lector->isEmptyElement) {
                            $celda['valor'] .= $lector->readString();
                        }
                        break;
                }
            } elseif ($lector->nodeType === XMLReader::END_ELEMENT) {
                if ($lector->localName === 'c' && $celda !== null && $fila !== null) {
                    $fila[$celda['col']] = $celda['tipo'] === 's' ? ($compartidas[(int) $celda['valor']] ?? '') : $celda['valor'];
                    $celda = null;
                } elseif ($lector->localName === 'row' && $fila !== null) {
                    if ($fila !== []) {
                        $completa = array_fill(0, max(array_keys($fila)) + 1, '');
                        foreach ($fila as $indice => $valor) {
                            $completa[$indice] = trim($valor);
                        }
                        if (! self::vacia($completa)) {
                            $filas[] = $completa;
                        }
                    }
                    $fila = null;
                }
            }
        }
        $lector->close();

        return $filas;
    }

    /**
     * Índice (desde 0) de la columna de una referencia como «AB12».
     */
    private static function columna(string $referencia): int
    {
        $letras = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $referencia));
        $indice = 0;
        foreach (str_split($letras) as $letra) {
            $indice = $indice * 26 + (ord($letra) - 64);
        }

        return max(0, $indice - 1);
    }

    private static function lector(string $xml): XMLReader
    {
        $lector = XMLReader::XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);
        if (! $lector instanceof XMLReader) {
            throw new RuntimeException('El libro de Excel tiene un XML ilegible.');
        }

        return $lector;
    }

    /**
     * Quita la marca BOM y pasa a UTF-8 los CSV en UTF-16 o Windows-1252.
     */
    private static function aUtf8(string $contenido): string
    {
        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            return substr($contenido, 3);
        }
        if (str_starts_with($contenido, "\xFF\xFE")) {
            return mb_convert_encoding(substr($contenido, 2), 'UTF-8', 'UTF-16LE');
        }
        if (str_starts_with($contenido, "\xFE\xFF")) {
            return mb_convert_encoding(substr($contenido, 2), 'UTF-8', 'UTF-16BE');
        }

        return mb_check_encoding($contenido, 'UTF-8') ? $contenido : mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
    }

    /**
     * @param  array<int, string>  $celdas
     */
    private static function vacia(array $celdas): bool
    {
        return array_filter($celdas, fn (string $c) => $c !== '') === [];
    }
}
