<?php

namespace App\Modules\Calificaciones\Livewire;

use App\Modules\Calificaciones\Services\RevisionService;
use App\Support\Navegacion\FormularioPorPasos;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Una solicitud de revisión de calificación (RF35, ADR-040): el docente la resuelve ítem por ítem y el
 * Coordinador la consulta. Va en página y no en un modal porque cada ítem revisado suma sus propios campos,
 * así que el formulario crece con los datos (docs/13 Layout/Layout base.md).
 */
#[Layout('layouts::app')]
class Revision extends Component
{
    use FormularioPorPasos;
    use PaginaDeSeccion;

    #[Locked]
    public string $solicitud;

    /** @var array<int|string, array{cambiar?: bool, valor?: float|int|string|null, respuesta?: string}> ítem => decisión */
    public array $decisiones = [];

    public string $respuesta = '';

    /** Solo el docente que puede resolver recorre los pasos; quien consulta la ve entera. */
    #[Locked]
    public bool $puedeResolver = false;

    public function mount(string $revision, RevisionService $revisiones): void
    {
        $this->solicitud = $revision;
        $detalle = $revisiones->detalle(auth()->user(), $this->seccionId(), $revision);
        $this->puedeResolver = $detalle['puedeResolver'];

        foreach ($detalle['items'] as $item) {
            $this->decisiones[$item->id] = ['cambiar' => false, 'valor' => null, 'respuesta' => ''];
        }
    }

    /**
     * Lo que pide el estudiante, la decisión de cada ítem y el resumen para confirmar. Sin pasos cuando solo
     * se consulta: no hay nada que recorrer.
     *
     * @return array<int, array{titulo: string, descripcion?: string, reglas?: array<string, mixed>, atributos?: array<string, string>}>
     */
    public function pasos(): array
    {
        if (! $this->puedeResolver) {
            return [];
        }

        return [
            ['titulo' => __('La solicitud'), 'descripcion' => __('Lo que pide el estudiante.')],
            [
                'titulo' => __('Su decisión'),
                'descripcion' => __('Ítem por ítem.'),
                'reglas' => [
                    'decisiones' => 'array',
                    'decisiones.*.cambiar' => 'nullable|boolean',
                    'decisiones.*.valor' => 'nullable|numeric',
                    'decisiones.*.respuesta' => 'nullable|string|max:2000',
                ],
            ],
            [
                'titulo' => __('Revisar y resolver'),
                'descripcion' => __('Confirme antes de avisar al estudiante.'),
                'reglas' => ['respuesta' => 'nullable|string|max:2000'],
            ],
        ];
    }

    public function resolver(RevisionService $revisiones): void
    {
        $this->validarTodosLosPasos();

        $revisiones->resolver(auth()->user(), $this->seccionId(), $this->solicitud, $this->decisiones, $this->respuesta);

        session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => __('Solicitud resuelta. Se avisó al estudiante.')]);
        $this->redirectRoute('secciones.revisiones', ['seccion' => $this->seccion], navigate: true);
    }

    public function render(RevisionService $revisiones): View
    {
        $detalle = $revisiones->detalle(auth()->user(), $this->seccionId(), $this->solicitud);

        return $this->paginaDeSeccion('modules.calificaciones.revision', [
            'detalle' => $detalle,
            'estados' => RevisionService::ESTADOS,
        ]);
    }
}
