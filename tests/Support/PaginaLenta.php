<?php

namespace Tests\Support;

use function Amp\delay;

/**
 * Envuelve la página de Pest Browser y espera unos segundos antes de cada paso (clic, texto, aserción), para
 * alcanzar a ver lo que hace la prueba con `--headed`. Playwright no expone `slowMo` en este plugin.
 *
 * La pausa usa Amp\delay (amphp/amp, que ya trae el plugin) y no sleep(): la app corre en este mismo proceso y
 * un sleep dejaría sin atender las peticiones pendientes de Livewire, mientras que delay() cede el control al
 * bucle de eventos. Tampoco se espera con un temporizador del navegador, que Chrome frena si la ventana no
 * tiene el foco.
 */
final class PaginaLenta
{
    public function __construct(private readonly mixed $pagina, private readonly float $segundos) {}

    /**
     * @param  array<int, mixed>  $argumentos
     */
    public function __call(string $metodo, array $argumentos): mixed
    {
        // script() es plomería de las pruebas (confirmaciones, esperas), no un paso que haya que ver.
        if ($metodo !== 'script') {
            delay($this->segundos);
        }
        $resultado = $this->pagina->{$metodo}(...$argumentos);

        // Visitar crea otra página y los pasos devuelven la página: se vuelve a envolver para no perder la pausa.
        return is_object($resultado) && str_starts_with($resultado::class, 'Pest\\Browser\\Api\\')
            ? new self($resultado, $this->segundos)
            : $resultado;
    }
}
