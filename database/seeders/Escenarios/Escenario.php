<?php

namespace Database\Seeders\Escenarios;

/**
 * Un escenario de prueba: deja la base en el estado exacto para probar un flujo con personas concretas.
 *
 * Cada escenario declara qué roles necesita (eso es lo que el wizard pregunta) y qué RF cubre (de ahí
 * sale la checklist, de docs/10 Calidad/Casos de prueba). `preparar()` siembra y devuelve los enlaces.
 *
 * Reglas que todo escenario cumple:
 * - **Aditivo**: no toca ni borra nada del demo; crea sus propias materias, secciones y matrículas.
 * - **Repetible**: prepararlo dos veces da dos escenarios independientes, sin choques de unicidad.
 * - **Relativo a ahora**: las fechas se calculan desde el momento de prepararlo, nunca fijas.
 */
abstract class Escenario
{
    /** Clave del flujo en la URL y en los comandos (p. ej. «asistencia-qr»). */
    abstract public static function clave(): string;

    abstract public static function titulo(): string;

    /** Qué se prueba, en una línea, para el wizard. */
    abstract public static function descripcion(): string;

    /**
     * Roles que hay que repartir, en el orden en que se preguntan.
     *
     * @return array<string, array{etiqueta: string, minimo: int, maximo: int}>
     */
    abstract public static function roles(): array;

    /**
     * RF que cubre, para armar la checklist con sus casos de prueba.
     *
     * @return array<int, string>
     */
    abstract public static function rfs(): array;

    /** Cuántos estudiantes de relleno sugiere, además de los del equipo. */
    public static function rellenoSugerido(): int
    {
        return 20;
    }

    abstract public function preparar(Reparto $reparto, Esqueleto $esqueleto): Resultado;

    /**
     * Ayuda para declarar un rol en `roles()`.
     *
     * @return array{etiqueta: string, minimo: int, maximo: int}
     */
    protected static function rol(string $etiqueta, int $minimo = 1, int $maximo = 1): array
    {
        return ['etiqueta' => $etiqueta, 'minimo' => $minimo, 'maximo' => $maximo];
    }
}
