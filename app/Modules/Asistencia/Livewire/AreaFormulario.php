<?php

namespace App\Modules\Asistencia\Livewire;

use App\Modules\Asistencia\Services\AsistenciaService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Crear o editar un área de asistencia (RF26, ADR-055): por centro y radio o por polígono, dibujada sobre el mapa o
 * escrita a mano. Va en página para que el mapa tenga espacio; antes era un modal donde casi no se podía dibujar.
 */
#[Layout('layouts::app')]
class AreaFormulario extends Component
{
    use PaginaLivewire;

    #[Locked]
    public ?int $areaId = null;

    public string $nombre = '';

    /** circle o polygon (attendance_areas.area_type). */
    public string $tipo = 'circle';

    public string $latitud = '';

    public string $longitud = '';

    public int $radio = 150;

    /** @var array<int, array{0: float|string, 1: float|string}> Vértices [[lat, lng], …] del polígono. */
    public array $vertices = [];

    public function mount(AsistenciaService $asistencia, ?int $area = null): void
    {
        if ($area === null) {
            // Sin esto un usuario sin permiso vería el formulario vacío; la ruta ya lo filtra, el Service también.
            $asistencia->areas(auth()->user());

            return;
        }
        $a = $asistencia->areas(auth()->user())->firstWhere('id', $area) ?? abort(404);
        $this->areaId = $area;
        $this->nombre = $a->name;
        $this->tipo = $a->area_type;
        $this->latitud = (string) $a->center_lat;
        $this->longitud = (string) $a->center_lng;
        $this->radio = (int) ($a->radius_m ?? 150);
        $this->vertices = $a->area_type === 'polygon' ? (json_decode((string) $a->polygon, true) ?? []) : [];
    }

    public function guardar(AsistenciaService $asistencia): void
    {
        $this->validate(['nombre' => 'required|string|max:120', 'tipo' => 'required|in:circle,polygon'], [], ['tipo' => 'forma']);

        if ($this->tipo === 'polygon') {
            $this->validate(['vertices' => 'required|array|min:3', 'vertices.*' => 'array|size:2', 'vertices.*.*' => 'numeric'], [
                'vertices.required' => __('Marque en el mapa al menos 3 vértices.'),
                'vertices.min' => __('Marque en el mapa al menos 3 vértices.'),
            ], ['vertices' => 'vértices']);
            $asistencia->guardarPoligono(auth()->user(), $this->areaId, $this->nombre, $this->vertices);
        } else {
            $this->validate(['latitud' => 'required|numeric', 'longitud' => 'required|numeric', 'radio' => 'required|integer'],
                [], ['latitud' => 'latitud', 'longitud' => 'longitud']);
            $asistencia->guardarArea(auth()->user(), $this->areaId, $this->nombre, (float) $this->latitud, (float) $this->longitud, $this->radio);
        }

        session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => __('Área guardada.')]);
        $this->redirectRoute('areas-asistencia', navigate: true);
    }

    public function render(): View
    {
        $area = config('lms.area_asistencia');

        return $this->pagina('modules.asistencia.area-formulario', [
            'centro' => [(float) $area['lat'], (float) $area['lng']],
        ], titulo: $this->areaId ? __('Editar :n', ['n' => $this->nombre]) : null, miga: $this->areaId ? $this->nombre : null);
    }
}
