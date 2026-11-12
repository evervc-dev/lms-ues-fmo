<?php

namespace App\Support\Navegacion;

/**
 * Una opción del menú ya resuelta para el usuario. Si la ruta aún no existe, $url es null y se
 * muestra deshabilitada («Pronto»); si es una página provisional del registro de vistas, $pendiente es true.
 * $contador es lo que queda por atender detrás de la opción (0 si no lleva contador).
 * $secundaria la dibuja con sangría bajo la opción de la que depende (p. ej. «Importar estudiantes»
 * bajo «Usuarios»). $externa es una pantalla fuera de la aplicación Livewire, como el panel de Horizon:
 * se abre en otra pestaña y con una carga normal, porque wire:navigate le deja al navegador los estilos del panel
 * y, en la misma pestaña, el panel parecía otra aplicación sin camino de vuelta (QA sesión 4).
 */
final readonly class Opcion
{
    public function __construct(
        public string $clave,
        public string $etiqueta,
        public string $icono,
        public string $ruta,
        public ?string $url,
        public bool $activa,
        public bool $pendiente = false,
        public int $contador = 0,
        public bool $secundaria = false,
        public bool $externa = false,
    ) {}

    public function disponible(): bool
    {
        return $this->url !== null;
    }

    /** La misma opción sin el resaltado de activa. */
    public function inactiva(): self
    {
        return new self($this->clave, $this->etiqueta, $this->icono, $this->ruta, $this->url, false,
            $this->pendiente, $this->contador, $this->secundaria, $this->externa);
    }
}
