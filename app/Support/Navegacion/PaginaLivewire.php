<?php

namespace App\Support\Navegacion;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;

/**
 * Para los componentes Livewire de página (ADR-035): arma la vista con el título y las migas del
 * registro de vistas (config/vistas.php). El encabezado (título, subtítulo y acciones) lo dibuja la
 * propia vista con <x-ui.encabezado>, para que sus botones funcionen con wire:click.
 */
trait PaginaLivewire
{
    /** Nombre de la ruta de la página; se guarda al montar porque en los re-render la ruta es livewire.update. */
    #[Locked]
    public string $rutaActual = '';

    public function mountPaginaLivewire(): void
    {
        $this->rutaActual = (string) request()->route()?->getName();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<int, array{etiqueta: string, url?: string|null}>  $intermedias
     * @param  string|null  $miga  Última miga, si no es el título de la vista (Vistas::migas()).
     */
    protected function pagina(string $vista, array $datos = [], array $intermedias = [], ?string $titulo = null, ?string $miga = null): View
    {
        $titulo ??= __(Vistas::vista($this->rutaActual)['titulo'] ?? '');

        return view($vista, $datos)
            ->layoutData(['migas' => Vistas::migas($this->rutaActual, $intermedias, $miga), 'encabezado' => false])
            ->title($titulo);
    }
}
