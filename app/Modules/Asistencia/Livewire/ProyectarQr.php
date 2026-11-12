<?php

namespace App\Modules\Asistencia\Livewire;

use App\Modules\Academica\Services\SeccionService;
use App\Modules\Asistencia\Services\AsistenciaQrService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Proyección del QR (RF20, maqueta 22, ADR-046): pantalla completa para el proyector del aula. Pide el QR cada
 * 2 s, así el token mostrado nunca tiene más de 2 s de atraso frente a la rotación. Nunca muestra nombres. El enlace
 * «Salir de la proyección» (o Esc) vuelve a la sesión (QA sesión 4: no había forma de salir).
 */
#[Layout('layouts::projection')]
class ProyectarQr extends Component
{
    use PaginaDeSeccion;

    #[Locked]
    public string $sesionUlid = '';

    public function mount(string $sesion): void
    {
        $this->sesionUlid = $sesion;
    }

    public function render(AsistenciaQrService $qr): View
    {
        $titulo = app(SeccionService::class)->resumen($this->seccion)['titulo'] ?? abort(404);

        return view('modules.asistencia.proyectar', $qr->proyeccion(auth()->user(), $this->seccionId(), $this->sesionUlid) + [
            'volver' => route('secciones.asistencia.sesion', ['seccion' => $this->seccion, 'sesion' => $this->sesionUlid]),
        ])
            ->title(__(':s · Asistencia', ['s' => $titulo]));
    }
}
