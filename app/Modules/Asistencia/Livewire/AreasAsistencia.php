<?php

namespace App\Modules\Asistencia\Livewire;

use App\Modules\Asistencia\Services\AsistenciaService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Áreas de asistencia (RF26, maqueta 34): por centro y radio o por polígono, dibujados sobre el mapa
 * (Leaflet, ADR-055) o escritos a mano.
 */
#[Layout('layouts::app')]
class AreasAsistencia extends Component
{
    use PaginaLivewire;

    public ?int $areaId = null;

    public string $nombre = '';

    /** circle o polygon (attendance_areas.area_type). */
    public string $tipo = 'circle';

    public string $latitud = '';

    public string $longitud = '';

    public int $radio = 150;

    /** @var array<int, array{0: float|string, 1: float|string}> Vértices [[lat, lng], …] del polígono. */
    public array $vertices = [];

    public function editar(int $id, AsistenciaService $asistencia): void
    {
        $a = $asistencia->areas(auth()->user())->firstWhere('id', $id) ?? abort(404);
        $this->areaId = $id;
        $this->nombre = $a->name;
        $this->tipo = $a->area_type;
        $this->latitud = (string) $a->center_lat;
        $this->longitud = (string) $a->center_lng;
        $this->radio = (int) ($a->radius_m ?? 150);
        $this->vertices = $a->area_type === 'polygon' ? (json_decode((string) $a->polygon, true) ?? []) : [];
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'area');
    }

    public function nueva(): void
    {
        $this->reset('areaId', 'nombre', 'tipo', 'latitud', 'longitud', 'radio', 'vertices');
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'area');
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

        $this->dispatch('cerrar-modal', 'area');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Área guardada.'));
    }

    public function alternar(int $id, AsistenciaService $asistencia): void
    {
        $asistencia->alternarArea(auth()->user(), $id);
    }

    public function render(AsistenciaService $asistencia): View
    {
        $area = config('lms.area_asistencia');

        return $this->pagina('modules.asistencia.areas', [
            'areas' => $asistencia->areas(auth()->user()),
            'centro' => [(float) $area['lat'], (float) $area['lng']],
        ]);
    }
}
