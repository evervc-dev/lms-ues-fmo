<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\MatriculaService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Claves por asignatura (RF29, ADR-050, maqueta 36): el Coordinador (su carrera) y el Jefe (su departamento) escriben
 * o generan la clave de cada asignatura, o aplican una misma a todas. La clave se muestra una sola vez: se guarda con hash.
 */
#[Layout('layouts::app')]
class ClavesMatricula extends Component
{
    use PaginaLivewire;

    public ?string $claveNueva = null;

    public ?string $asignaturaNueva = null;

    public string $vence = '';

    /** Clave escrita a mano para la próxima asignatura; vacía, se genera una. */
    public string $claveManual = '';

    /** Clave que «Aplicar a todas» pone en todas las asignaturas del ámbito. */
    public string $claveComun = '';

    /** Cuántas asignaturas recibieron la clave común; null si la clave mostrada es de una sola. */
    public ?int $aplicadas = null;

    #[Url(except: '')]
    public string $buscar = '';

    public function limpiar(): void
    {
        $this->reset('buscar');
    }

    public function generar(int $asignatura, MatriculaService $matricula): void
    {
        $this->validate([
            'vence' => 'nullable|date|after_or_equal:today',
            'claveManual' => ['nullable', 'string', 'min:6', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
        ], ['claveManual.regex' => __('La clave solo admite letras, números y guiones.')], ['vence' => 'vencimiento', 'claveManual' => 'clave']);
        $this->claveNueva = $matricula->generarClave(auth()->user(), $asignatura, $this->vence ?: null, $this->claveManual ?: null);
        $this->asignaturaNueva = DB::table('subjects')->where('id', $asignatura)->value('code');
        $this->aplicadas = null;
        $this->reset('claveManual');
        $this->dispatch('abrir-modal', 'clave-generada');
    }

    public function aplicarATodas(MatriculaService $matricula): void
    {
        $this->validate([
            'vence' => 'nullable|date|after_or_equal:today',
            'claveComun' => ['required', 'string', 'min:6', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
        ], ['claveComun.regex' => __('La clave solo admite letras, números y guiones.')], ['vence' => 'vencimiento', 'claveComun' => 'clave']);
        $this->aplicadas = $matricula->aplicarClaveATodas(auth()->user(), $this->claveComun, $this->vence ?: null);
        $this->claveNueva = trim($this->claveComun);
        $this->asignaturaNueva = null;
        $this->reset('claveComun');
        $this->dispatch('abrir-modal', 'clave-generada');
    }

    public function desactivar(int $asignatura, MatriculaService $matricula): void
    {
        $matricula->desactivarClave(auth()->user(), $asignatura);
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('La clave quedó desactivada.'));
    }

    public function render(MatriculaService $matricula): View
    {
        return $this->pagina('modules.academica.claves', [
            'claves' => $matricula->claves(auth()->user(), $this->buscar),
            'pestanas' => array_values(array_filter([
                ['etiqueta' => __('Estudiantes'), 'url' => route('matricula'), 'activa' => false],
                ['etiqueta' => __('Claves por asignatura'), 'url' => route('matricula.claves'), 'activa' => true],
                ['etiqueta' => __('Solicitudes de cambio de grupo'), 'url' => route('matricula.solicitudes'), 'activa' => false],
                auth()->user()->hasAnyPermission(['matricula.plazo.gestionar', 'matricula.prorroga.otorgar']) ? ['etiqueta' => __('Plazos'), 'url' => route('matricula.plazos'), 'activa' => false] : null,
            ])),
        ]);
    }
}
