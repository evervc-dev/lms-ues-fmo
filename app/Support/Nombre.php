<?php

namespace App\Support;

use Closure;

/**
 * Formato de los nombres y apellidos de las cuentas (ADR-060, QA sesión 4): como mucho 60 caracteres por campo,
 * ninguna palabra de más de 25 letras (un apellido de 80 «m» seguidas estiraba la tabla de Usuarios y ocultaba sus
 * acciones) y guardados como nombre propio: «maría DE LOS ángeles» pasa a «María de los Ángeles».
 *
 * Lo usan el alta, la edición y la carga masiva (que rechazan lo que no cumple) y el ingreso con Google (que solo
 * normaliza: no se le niega la entrada a nadie por cómo escribió su nombre en Google).
 */
final class Nombre
{
    public const MAXIMO = 60;

    public const PALABRA_MAXIMA = 25;

    /** Partículas que van en minúscula salvo al inicio: «María de los Ángeles», «Del Cid». */
    private const PARTICULAS = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'van', 'von'];

    public static function normalizar(string $valor): string
    {
        $palabras = preg_split('/\s+/u', trim($valor), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(function (string $palabra, int $i): string {
            $minuscula = mb_strtolower($palabra);

            return $i > 0 && in_array($minuscula, self::PARTICULAS, true) ? $minuscula : mb_convert_case($minuscula, MB_CASE_TITLE);
        }, $palabras, array_keys($palabras)));
    }

    /**
     * El problema de formato del valor, o null si cumple. Un valor vacío lo resuelve la regla `required`.
     */
    public static function error(string $valor): ?string
    {
        // Primero la palabra larga: su mensaje dice qué corregir; el del largo total no.
        $valor = trim($valor);
        foreach (preg_split('/\s+/u', $valor, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $palabra) {
            if (mb_strlen($palabra) > self::PALABRA_MAXIMA) {
                return __('Separe las palabras con espacios: «:palabra» tiene más de :n letras.', ['palabra' => mb_strimwidth($palabra, 0, 20, '…'), 'n' => self::PALABRA_MAXIMA]);
            }
        }
        if (mb_strlen($valor) > self::MAXIMO) {
            return __('Use como máximo :n caracteres.', ['n' => self::MAXIMO]);
        }

        return null;
    }

    /**
     * Regla de validación de Laravel para los formularios: `['required', 'string', Nombre::regla()]`.
     */
    public static function regla(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar): void {
            if (is_string($valor) && ($error = self::error($valor)) !== null) {
                $fallar($error);
            }
        };
    }
}
