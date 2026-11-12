<?php

namespace App\Support\Navegacion;

use App\Modules\Academica\Services\SeccionService;
use Illuminate\Contracts\View\View;

/**
 * Para las pestañas de la Vista de sección (ADR-035): cabecera del curso, pestañas según el permiso
 * y migas «Inicio › PRN115 · 01 › Pestaña». La ruta pasa el ulid en {seccion}.
 */
trait PaginaDeSeccion
{
    use PaginaLivewire;

    public string $seccion;

    protected function seccionId(): int
    {
        return app(SeccionService::class)->idPorUlid($this->seccion);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected function paginaDeSeccion(string $vista, array $datos = []): View
    {
        $resumen = app(SeccionService::class)->resumen($this->seccion) ?? abort(404);

        return $this->pagina($vista, $datos + [
            'resumen' => $resumen,
            'pestanas' => Vistas::pestanas(auth()->user(), $this->seccion, $this->rutaActual),
        ], [['etiqueta' => $resumen['titulo'], 'url' => route('secciones.contenidos', ['seccion' => $this->seccion])]]);
    }
}
