<?php

namespace Database\Seeders\Escenarios;

use InvalidArgumentException;

/**
 * Quién juega qué en un escenario: rol => usuarios, más cuántos estudiantes de relleno sembrar.
 *
 * Los usuarios son personas reales del equipo (database/seeders/data/testers.php) y su cuenta lleva
 * su correo institucional, porque de eso depende que les lleguen las invitaciones de Google Calendar.
 * El relleno son estudiantes inventados que dan volumen a las listas, los reportes y las alertas.
 */
final class Reparto
{
    /**
     * @param  array<string, array<int, int>>  $actores  rol => id de usuario
     */
    public function __construct(
        private readonly array $actores,
        public readonly int $relleno = 0,
    ) {}

    /**
     * @param  array<string, array<int, int>|int>  $actores
     */
    public static function de(array $actores, int $relleno = 0): self
    {
        return new self(array_map(fn ($ids) => array_values(array_unique((array) $ids)), $actores), $relleno);
    }

    public function uno(string $rol): int
    {
        $ids = $this->varios($rol);

        if ($ids === []) {
            throw new InvalidArgumentException("El escenario necesita un usuario con el rol «{$rol}».");
        }

        return $ids[0];
    }

    public function unoONulo(string $rol): ?int
    {
        return $this->varios($rol)[0] ?? null;
    }

    /**
     * @return array<int, int>
     */
    public function varios(string $rol): array
    {
        return $this->actores[$rol] ?? [];
    }

    /**
     * @return array<string, array<int, int>>
     */
    public function todos(): array
    {
        return $this->actores;
    }

    /**
     * Todos los id de usuario del reparto, sin repetir.
     *
     * @return array<int, int>
     */
    public function usuarios(): array
    {
        return array_values(array_unique(array_merge(...array_values($this->actores) ?: [[]])));
    }
}
