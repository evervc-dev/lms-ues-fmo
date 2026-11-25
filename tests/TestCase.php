<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Las vistas usan @vite; en las pruebas no hay build de frontend.
        $this->withoutVite();

        // Las pruebas abren sesiones de asistencia a cualquier hora; la restricción por horario (ADR-075) la
        // prueban AsistenciaHorarioTest, que la vuelve a encender.
        config(['lms.asistencia.bypass_horario' => true]);
    }
}
