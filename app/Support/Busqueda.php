<?php

namespace App\Support;

use Closure;

/**
 * Búsqueda por nombre de persona. Cada palabra escrita debe coincidir con el **inicio** de alguna palabra
 * del nombre, no con cualquier parte: así «Jan» encuentra a «Jan Carlos Pérez» y ya no a «Alejandra», que
 * solo lo lleva en medio. Varias palabras se exigen todas, en cualquier orden («pérez jan» sirve).
 *
 * Los correos y los carnés se siguen buscando como subcadena, porque ahí sí se busca un fragmento.
 */
final class Busqueda
{
    /**
     * Condición para `where()` u `orWhere()`.
     *
     * @param  array<int, string>  $columnas  columnas o expresiones SQL del código (nunca texto del usuario)
     */
    public static function porNombre(string $texto, array $columnas): Closure
    {
        $palabras = self::palabras($texto);

        return function ($consulta) use ($palabras, $columnas): void {
            if ($palabras === []) {
                $consulta->whereRaw('1 = 0');

                return;
            }

            foreach ($palabras as $palabra) {
                $consulta->where(function ($consulta) use ($palabra, $columnas): void {
                    foreach ($columnas as $columna) {
                        $consulta->orWhereRaw("lower({$columna}) like ? escape '\'", ["{$palabra}%"])
                            ->orWhereRaw("lower({$columna}) like ? escape '\'", ["% {$palabra}%"]);
                    }
                });
            }
        };
    }

    /**
     * Patrón de subcadena para un LIKE con `escape '\'`, como se buscan los correos y los carnés: el texto va
     * en minúsculas y con sus comodines (`%`, `_`) neutralizados, para que no amplíen la búsqueda.
     */
    public static function fragmento(string $texto): string
    {
        return '%'.addcslashes(mb_strtolower(trim($texto)), '%_\\').'%';
    }

    /** Letras con tilde (y la eñe) y la letra sin tilde con la que se comparan. */
    private const TILDES = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n'];

    /**
     * Expresión SQL de la columna en minúsculas y sin tildes, para compararla con fragmentoSinTildes(): así
     * «administracion» encuentra «Administración» (H-10, QA 27/10). Usa replace() anidados, que SQLite y
     * PostgreSQL tienen igual (ADR-033); se cambian también las mayúsculas con tilde porque el lower() de
     * SQLite solo convierte ASCII.
     *
     * @param  string  $columna  columna o expresión SQL del código (nunca texto del usuario)
     */
    public static function sinTildes(string $columna): string
    {
        $expresion = "lower({$columna})";
        foreach (self::TILDES as $con => $sin) {
            $expresion = "replace({$expresion}, '{$con}', '{$sin}')";
        }

        return $expresion;
    }

    /** Como fragmento(), pero sin tildes, para compararlo con sinTildes(). */
    public static function fragmentoSinTildes(string $texto): string
    {
        return strtr(self::fragmento($texto), self::TILDES);
    }

    /**
     * Palabras del texto en minúsculas, ya neutralizados los comodines de LIKE.
     *
     * @return array<int, string>
     */
    private static function palabras(string $texto): array
    {
        $palabras = preg_split('/\s+/', mb_strtolower(trim($texto)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(fn (string $palabra) => addcslashes($palabra, '%_\\'), $palabras);
    }
}
