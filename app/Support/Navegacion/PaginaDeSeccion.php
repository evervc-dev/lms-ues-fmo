<?php

namespace App\Support\Navegacion;

use App\Modules\Academica\Services\SeccionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

/**
 * Para las pestañas de la Vista de sección (ADR-035): cabecera del curso, pestañas según el permiso
 * y migas «Inicio › PRN115 · 01 › Pestaña»; al estudiante, «Inicio › Mis materias › PRN115 · 01 › Pestaña», porque llega
 * desde ahí (revisión UX del rol Estudiante). La ruta pasa el ulid en {seccion}.
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
        $intermedias = auth()->user()->hasRole('estudiante') && Route::has('materias')
            ? [['etiqueta' => __('Mis materias'), 'url' => route('materias')]]
            : [];

        $pestanas = Vistas::pestanas(auth()->user(), $this->seccion, $this->rutaActual);

        // La miga del grupo lleva a la primera pestaña que el usuario puede abrir: el Jefe y el Administrador no tienen
        // contenidos.ver, y enlazar siempre a Contenidos les daba 403.
        return $this->pagina($vista, $datos + [
            'resumen' => $resumen,
            'pestanas' => $pestanas,
        ], [...$intermedias, ['etiqueta' => $resumen['titulo'], 'url' => $pestanas[0]['url'] ?? null]]);
    }
}
