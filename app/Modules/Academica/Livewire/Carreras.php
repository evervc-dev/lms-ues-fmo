<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\CarreraService;
use App\Modules\Academica\Services\CatalogoService;
use App\Support\Alcance;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Carreras del ámbito del actor (RF07, RF08): el Jefe ve las de su departamento y el Coordinador
 * la que coordina. El Administrador las ve todas y llega aquí desde Departamentos, filtradas por uno
 * (ADR-083). El Administrador además crea carreras con su primer plan y agrega planes; lo exige CatalogoService.
 */
#[Layout('layouts::app')]
class Carreras extends Component
{
    use PaginaLivewire;

    /** Departamento por el que filtra el Administrador; para los demás roles se ignora. */
    #[Url(as: 'departamento', except: null)]
    public ?int $departamento = null;

    public ?int $carreraDepartamento = null;

    public string $carreraCodigo = '';

    public string $carreraNombre = '';

    public string $carreraPlan = '';

    /** Carrera a la que se agrega un plan. */
    public ?int $planCarrera = null;

    public string $planNombre = '';

    public function nuevaCarrera(): void
    {
        $this->reset('carreraCodigo', 'carreraNombre', 'carreraPlan');
        $this->carreraDepartamento = $this->departamento;
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'nueva-carrera');
    }

    public function crearCarrera(CatalogoService $catalogo): void
    {
        $this->validate([
            'carreraDepartamento' => 'required|integer',
            'carreraCodigo' => 'required|string|max:20|regex:/^[A-Za-z0-9-]+$/',
            'carreraNombre' => 'required|string|max:255',
            'carreraPlan' => 'required|string|max:100',
        ], ['carreraCodigo.regex' => __('Use solo letras, números y guiones, como ISI o LAE.')], [
            'carreraDepartamento' => __('departamento'), 'carreraCodigo' => __('código'), 'carreraNombre' => __('nombre'), 'carreraPlan' => __('plan de estudios'),
        ]);
        $catalogo->crearCarrera(auth()->user(), (int) $this->carreraDepartamento, $this->carreraCodigo, $this->carreraNombre, $this->carreraPlan);
        $this->reset('carreraCodigo', 'carreraNombre', 'carreraPlan');
        $this->dispatch('cerrar-modal', 'nueva-carrera');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Carrera creada.'));
    }

    public function nuevoPlan(int $carrera): void
    {
        $this->planCarrera = $carrera;
        $this->reset('planNombre');
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'nuevo-plan');
    }

    public function agregarPlan(CatalogoService $catalogo): void
    {
        $this->validate(['planNombre' => 'required|string|max:100'], [], ['planNombre' => __('nombre del plan')]);
        $catalogo->agregarPlan(auth()->user(), (int) $this->planCarrera, $this->planNombre);
        $this->reset('planCarrera', 'planNombre');
        $this->dispatch('cerrar-modal', 'nuevo-plan');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Plan de estudios agregado.'));
    }

    public function render(CarreraService $carreras, Alcance $alcance): View
    {
        $departamento = $carreras->departamentoFiltrable(auth()->user(), $this->departamento);
        // Crear carreras y planes es del Administrador (alcance global del catálogo), no del Jefe ni del Coordinador.
        $gestiona = $alcance->de(auth()->user(), 'asignaturas.gestionar') === 'G';

        return $this->pagina('modules.academica.carreras', [
            'carreras' => $carreras->listar(auth()->user(), $departamento === null ? null : (int) $departamento->id),
            'departamentoElegido' => $departamento,
            'gestiona' => $gestiona,
            'departamentos' => $gestiona ? DB::table('departments')->orderBy('code')->get(['id', 'code', 'name']) : collect(),
            'carreraDelPlan' => $this->planCarrera === null ? null : DB::table('careers')->where('id', $this->planCarrera)->value('code'),
        ], intermedias: $departamento === null ? [] : [['etiqueta' => __('Departamentos'), 'url' => route('departamentos')]],
            miga: $departamento === null ? null : __('Carreras de :d', ['d' => $departamento->code]));
    }
}
