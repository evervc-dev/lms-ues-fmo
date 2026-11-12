<?php

namespace App\Support\Navegacion;

use App\Modules\Academica\Services\SeccionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Página provisional de una vista del registro que aún no se implementa (ADR-035).
 * Muestra su RF, su tarea en OpenProject, el equipo, el hito y la maqueta de referencia.
 */
class EnConstruccion
{
    public function __invoke(Request $request, SeccionService $secciones): View
    {
        $nombre = (string) $request->route()?->getName();
        $vista = Vistas::vista($nombre);
        abort_if($vista === null, 404);

        $seccion = null;
        $intermedias = [];
        if ($ulid = $request->route('seccion')) {
            $seccion = $secciones->resumen((string) $ulid);
            abort_if($seccion === null, 404);
            $intermedias[] = ['etiqueta' => $seccion['titulo'], 'url' => route('secciones.contenidos', ['seccion' => $ulid])];
        }

        return view('navegacion.en-construccion', [
            'nombre' => $nombre,
            'vista' => $vista,
            'layout' => $vista['layout'] ?? 'app',
            'equipo' => Vistas::equipo($nombre),
            'hito' => Vistas::hito($nombre),
            'migas' => Vistas::migas($nombre, $intermedias),
            'seccion' => $seccion,
            'pestanas' => $seccion ? Vistas::pestanas($request->user(), (string) $ulid, $nombre) : [],
            'maqueta' => isset($vista['maqueta']) && app()->environment('local', 'staging', 'testing') ? route('dev.maqueta', ['archivo' => $vista['maqueta']]) : null,
        ]);
    }
}
