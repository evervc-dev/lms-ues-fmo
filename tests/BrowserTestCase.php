<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base de las pruebas de navegador (Pest Browser): a diferencia de TestCase, usa el build real de Vite
 * (public/build) para que el navegador cargue los estilos y el JavaScript de la app.
 * Requiere `bun run build` antes de correrlas.
 */
abstract class BrowserTestCase extends BaseTestCase {}
