<?php

namespace App\Modules\Contenidos\Livewire;

use App\Modules\Contenidos\Services\ContenidoService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Importar de un período anterior (RF14, maqueta 39): unidades, recursos y evaluaciones; estas llegan sin programar.
 */
#[Layout('layouts::app')]
class Importar extends Component
{
    use PaginaDeSeccion;

    public ?int $origen = null;

    public function importar(ContenidoService $contenidos): void
    {
        $this->validate(['origen' => 'required|integer'], [], ['origen' => 'sección de origen']);
        $copiado = $contenidos->importar(auth()->user(), (int) $this->origen, $this->seccionId());
        $mensaje = trans_choice('{0} No había recursos para copiar.|{1} Se copió 1 recurso.|[2,*] Se copiaron :count recursos.', $copiado['recursos']);
        if ($copiado['evaluaciones'] > 0) {
            $mensaje .= ' '.trans_choice('{1} Se copió 1 evaluación: prográmela en Actividades.|[2,*] Se copiaron :count evaluaciones: prográmelas en Actividades.', $copiado['evaluaciones']);
        }
        session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => $mensaje]);
        $this->redirectRoute('secciones.contenidos', ['seccion' => $this->seccion], navigate: true);
    }

    public function render(ContenidoService $contenidos): View
    {
        return $this->paginaDeSeccion('modules.contenidos.importar', ['origenes' => $contenidos->origenesParaImportar(auth()->user(), $this->seccionId())]);
    }
}
