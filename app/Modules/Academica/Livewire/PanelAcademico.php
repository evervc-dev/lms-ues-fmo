<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\DepartamentoService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Panel académico del Administrador (RF07, ADR-075), en tres niveles según la ruta: departamentos, carreras de un
 * departamento y materias del plan de una carrera con sus grupos teóricos del período, que enlazan a la Vista de
 * sección en solo lectura. Solo navega: los departamentos se gestionan en Estructura. El permiso y el alcance G los
 * aplica DepartamentoService.
 */
#[Layout('layouts::app')]
class PanelAcademico extends Component
{
    use PaginaLivewire;

    #[Locked]
    public ?int $departamentoId = null;

    #[Locked]
    public ?int $carreraId = null;

    /** Materia elegida en el nivel de carrera: muestra sus secciones del período. */
    #[Url(as: 'materia', except: null)]
    public ?int $materiaId = null;

    /**
     * Período del que se ven las secciones; sin elegir, el que está en curso. Sin tipo: la opción vacía del selector
     * llega como «» y un `?periodo=` a mano puede no ser un número; periodo() lo normaliza.
     */
    #[Url(as: 'periodo', except: '')]
    public int|string|null $periodoId = null;

    public function mount(?int $departamento = null, ?int $carrera = null): void
    {
        $this->departamentoId = $departamento;
        $this->carreraId = $carrera;
    }

    public function elegirMateria(?int $id): void
    {
        $this->materiaId = $this->materiaId === $id ? null : $id;
    }

    public function render(DepartamentoService $departamentos): View
    {
        $actor = auth()->user();

        if ($this->carreraId !== null) {
            $datos = $departamentos->materiasDeCarrera($actor, $this->carreraId, $this->departamentoId);
            $materia = $this->materiaId !== null && $datos['materias']->contains('id', $this->materiaId)
                ? $departamentos->seccionesDeMateria($actor, $this->materiaId, $this->periodo())
                : null;

            return $this->pagina('modules.academica.panel-academico', $datos + [
                'nivel' => 'carrera',
                'materia' => $materia,
                'periodos' => $departamentos->periodos($actor),
            ], [['etiqueta' => $datos['carrera']->departamentoCodigo, 'url' => route('catalogo.departamentos.show', ['departamento' => $datos['carrera']->department_id])]],
                titulo: $datos['carrera']->name, miga: $datos['carrera']->code);
        }

        if ($this->departamentoId !== null) {
            $datos = $departamentos->carreras($actor, $this->departamentoId);

            return $this->pagina('modules.academica.panel-academico', $datos + ['nivel' => 'departamento'],
                titulo: $datos['departamento']->name, miga: $datos['departamento']->code);
        }

        return $this->pagina('modules.academica.panel-academico', [
            'nivel' => 'departamentos',
            'departamentos' => $departamentos->listar($actor),
        ]);
    }

    /**
     * El período elegido como entero, o null (el que está en curso) si viene vacío o no es un número.
     */
    public function periodo(): ?int
    {
        return is_numeric($this->periodoId) && (int) $this->periodoId > 0 ? (int) $this->periodoId : null;
    }
}
