<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descargas de las pantallas que exportan (ADR-049): CSV, Excel y PDF con la misma forma de llamarse.
 *
 * Los tres reciben las columnas y las filas ya resueltas por el Service de cada módulo: aquí no hay consultas
 * ni permisos. Quien exporta ya pasó por el alcance al armar los datos.
 *
 *     return Exportacion::xlsx('notas-PRN115.xlsx', $columnas, $filas, titulo: 'Libro de calificaciones', subtitulo: 'PRN115 · 01');
 *     return Exportacion::pdf('notas-PRN115.pdf', 'exportaciones.notas', ['libro' => $libro]);
 */
final class Exportacion
{
    /**
     * Filas como máximo en un PDF de tabla (H-01). dompdf arma en memoria toda la tabla antes de escribir: con las
     * 822 alertas de «Estudiantes en riesgo» pasaba de los 256 MB de PHP en staging, la petición moría y Livewire
     * tapaba la pantalla con su recuadro de error. Con 500 filas (unas 23 páginas) usa menos de 140 MB. El Excel y el
     * CSV llevan siempre todas las filas.
     */
    public const FILAS_PDF = 500;

    /**
     * CSV con BOM: Excel lo abre con los acentos bien y sin el asistente de importación de texto.
     *
     * @param  array<int, string>  $columnas
     * @param  iterable<int, array<int, string|int|float|null>>  $filas
     */
    public static function csv(string $nombre, array $columnas, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($columnas, $filas): void {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $columnas);
            foreach ($filas as $fila) {
                fputcsv($salida, $fila);
            }
            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Filas de cabecera de todo Excel antes de los encabezados de columna: facultad, reporte y fecha. */
    public const FILAS_DE_CABECERA = 3;

    /**
     * Libro de Excel de una hoja, escrito en streaming: openspout no arma el archivo entero en memoria, así
     * que un acta de mil filas pesa lo mismo que una de diez.
     *
     * Formato común a todas las exportaciones (informe de QA del rol Jefe, 31/10/2026), con los mismos textos
     * que el membrete del PDF: la facultad; el reporte con su período o sus filtros; y quién lo generó y cuándo.
     * Después van los encabezados de columna en negrita y los datos.
     *
     * @param  string|null  $titulo  Nombre del reporte; también da nombre a la hoja.
     * @param  string|null  $subtitulo  Período, sección o filtros con que se exportó (ver `filtros()`).
     * @param  array<int, string>  $columnas
     * @param  iterable<int, array<int, string|int|float|null>>  $filas
     */
    public static function xlsx(string $nombre, array $columnas, iterable $filas, ?string $titulo = null, ?string $subtitulo = null): StreamedResponse
    {
        $cabecera = [
            __('Universidad de El Salvador · Facultad Multidisciplinaria Oriental'),
            implode(' · ', array_filter([$titulo, $subtitulo])),
            __('Generado el :f por :u', ['f' => now()->format('d/m/Y H:i'), 'u' => auth()->user()?->fullName() ?? '—']),
        ];

        return response()->streamDownload(function () use ($columnas, $filas, $titulo, $cabecera): void {
            $libro = new Writer;
            $libro->openToFile('php://output');
            if ($titulo !== null) {
                // El nombre de la hoja no admite : \ / ? * [ ] y se corta en 31 caracteres.
                $libro->getCurrentSheet()->setName(mb_substr(str_replace([':', '\\', '/', '?', '*', '[', ']'], '·', $titulo), 0, 31));
            }
            $libro->addRow(Row::fromValues([$cabecera[0]], (new Style)->setFontBold()));
            $libro->addRow(Row::fromValues([$cabecera[1]], (new Style)->setFontBold()));
            $libro->addRow(Row::fromValues([$cabecera[2]]));
            $libro->addRow(Row::fromValues($columnas, (new Style)->setFontBold()));
            foreach ($filas as $fila) {
                $libro->addRow(Row::fromValues($fila));
            }
            $libro->close();
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Los filtros de una exportación en una línea, para el subtítulo: «Desde: 01/10/2026 · Búsqueda: Jan».
     * Las fechas AAAA-MM-DD se escriben DD/MM/AAAA (RNF11); los vacíos no salen.
     *
     * @param  array<string, string|int|null>  $filtros  Etiqueta ya traducida => valor.
     */
    public static function filtros(array $filtros): string
    {
        $partes = collect($filtros)->reject(fn ($valor) => $valor === null || $valor === '')
            ->map(fn ($valor, string $etiqueta) => $etiqueta.': '.(preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $valor) ? Carbon::parse((string) $valor)->format('d/m/Y') : $valor));

        return $partes->isEmpty() ? __('Sin filtros') : $partes->implode(' · ');
    }

    /**
     * PDF a partir de una vista Blade de `resources/views/exportaciones`, que usa la hoja común con el
     * membrete de la facultad.
     *
     * Va como descarga en streaming, igual que los otros dos formatos: Livewire solo reconoce como descarga
     * un `StreamedResponse` o un `BinaryFileResponse`, y con la respuesta normal de dompdf intentaba meter
     * el PDF dentro del JSON de la respuesta.
     *
     * El número de página («Página 2 de 7») lo dibuja dompdf sobre cada hoja ya armada: el contador `pages` de CSS
     * no lo soporta y salía «1 / 0».
     *
     * @param  array<string, mixed>  $datos
     */
    public static function pdf(string $nombre, string $vista, array $datos, string $orientacion = 'portrait'): StreamedResponse
    {
        $pdf = Pdf::loadView($vista, self::recortarParaPdf($datos))->setPaper('letter', $orientacion);
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $hoja = $dompdf->getCanvas();
        $hoja->page_text($hoja->get_width() - 115, $hoja->get_height() - 30, __('Página {PAGE_NUM} de {PAGE_COUNT}'),
            $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 7.5, [0.41, 0.41, 0.41]);

        return response()->streamDownload(fn () => print ($pdf->output()), $nombre, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Si la tabla pasa de `FILAS_PDF`, deja las primeras y agrega una `nota` que lo dice y manda al Excel o al CSV.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function recortarParaPdf(array $datos): array
    {
        $filas = $datos['filas'] ?? null;
        if (! is_iterable($filas)) {
            return $datos;
        }
        $filas = collect($filas);
        if ($filas->count() <= self::FILAS_PDF) {
            return $datos;
        }

        return ['filas' => $filas->take(self::FILAS_PDF)->values()->all(), 'nota' => __('Se muestran las primeras :n de :total filas. Para la lista completa, descargue el Excel o el CSV.', [
            'n' => number_format(self::FILAS_PDF), 'total' => number_format($filas->count()),
        ])] + $datos;
    }

    /** Deja un nombre de archivo sin acentos, espacios ni signos: `PRN115 · 01` → `prn115-01`. */
    public static function nombre(string $texto): string
    {
        return trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(
            iconv('UTF-8', 'ASCII//TRANSLIT', $texto) ?: $texto
        ))), '-') ?: 'exportacion';
    }
}
