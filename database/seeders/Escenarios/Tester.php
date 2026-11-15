<?php

namespace Database\Seeders\Escenarios;

/**
 * Una persona del equipo que prueba, con su DUE y su correo institucional real.
 *
 * Va junto a `Reparto` y `Resultado` porque es parte del vocabulario de los escenarios: el wizard la
 * muestra para repartir los roles y el constructor siembra su cuenta con ese correo, que es de lo que
 * depende que le lleguen las invitaciones de Google Calendar.
 */
final class Tester
{
    public function __construct(
        public readonly int $id,
        public readonly string $due,
        public readonly string $correo,
        public readonly string $nombre,
    ) {}
}
