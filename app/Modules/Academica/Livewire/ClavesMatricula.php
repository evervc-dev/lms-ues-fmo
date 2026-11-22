<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\MatriculaService;
use App\Modules\Academica\Services\PeriodoService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Claves por asignatura (RF29, ADR-050, maqueta 36): el Coordinador (su carrera) y el Jefe (su departamento) escriben
 * o generan la clave de cada asignatura, o aplican una misma a todas. La vigente se puede volver a consultar (ADR-087).
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

    /** Lo que se escribe para confirmar «Aplicar a todas» (CAMBIAR). */
    public string $confirmacion = '';

    /** Cuántas asignaturas recibieron la clave común; null si la clave mostrada es de una sola. */
    public ?int $aplicadas = null;

    /** La clave mostrada es una que ya existía (consultada), no una recién generada. */
    public bool $consultada = false;

    #[Url(except: '')]
    public string $buscar = '';

    /** Período de las claves: el que está en curso o uno en preparación. */
    #[Url(as: 'periodo')]
    public ?int $periodoId = null;

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
        $this->claveNueva = $matricula->generarClave(auth()->user(), $asignatura, $this->vence ?: null, $this->claveManual ?: null, $this->periodoId);
        $this->asignaturaNueva = DB::table('subjects')->where('id', $asignatura)->value('code');
        $this->aplicadas = null;
        $this->consultada = false;
        $this->reset('claveManual');
        $this->dispatch('abrir-modal', 'clave-generada');
    }

    /**
     * Primer paso de «Aplicar a todas»: valida la clave y abre la confirmación, que dice cuántas asignaturas afecta y pide
     * escribir CAMBIAR. Antes bastaba un confirm() del navegador para reemplazar todas las claves (QA Jefe 21/11).
     */
    public function prepararAplicarATodas(): void
    {
        $this->validarClaveComun();
        $this->reset('confirmacion');
        $this->dispatch('abrir-modal', 'confirmar-clave-comun');
    }

    public function aplicarATodas(MatriculaService $matricula): void
    {
        $this->validarClaveComun();
        if (mb_strtoupper(trim($this->confirmacion)) !== 'CAMBIAR') {
            $this->addError('confirmacion', __('Escriba CAMBIAR para confirmar.'));

            return;
        }
        $this->dispatch('cerrar-modal', 'confirmar-clave-comun');
        $this->aplicadas = $matricula->aplicarClaveATodas(auth()->user(), $this->claveComun, $this->vence ?: null, $this->periodoId);
        $this->claveNueva = trim($this->claveComun);
        $this->asignaturaNueva = null;
        $this->consultada = false;
        $this->reset('claveComun', 'confirmacion');
        $this->dispatch('abrir-modal', 'clave-generada');
    }

    private function validarClaveComun(): void
    {
        $this->validate([
            'vence' => 'nullable|date|after_or_equal:today',
            'claveComun' => ['required', 'string', 'min:6', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
        ], ['claveComun.regex' => __('La clave solo admite letras, números y guiones.')], ['vence' => 'vencimiento', 'claveComun' => 'clave']);
    }

    /**
     * Muestra la clave vigente de la asignatura para copiarla de nuevo, sin cambiarla (ADR-087).
     */
    public function ver(int $asignatura, MatriculaService $matricula): void
    {
        $clave = $matricula->verClave(auth()->user(), $asignatura, $this->periodoId);
        if ($clave === null) {
            $this->dispatch('aviso', tipo: 'error', mensaje: __('Esta clave se creó antes de que se pudieran consultar. Cámbiela para tener una que pueda volver a ver.'));

            return;
        }
        $this->claveNueva = $clave;
        $this->asignaturaNueva = DB::table('subjects')->where('id', $asignatura)->value('code');
        $this->aplicadas = null;
        $this->consultada = true;
        $this->dispatch('abrir-modal', 'clave-generada');
    }

    public function desactivar(int $asignatura, MatriculaService $matricula): void
    {
        $matricula->desactivarClave(auth()->user(), $asignatura, $this->periodoId);
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('La clave quedó desactivada.'));
    }

    public function render(MatriculaService $matricula): View
    {
        return $this->pagina('modules.academica.claves', [
            'claves' => $matricula->claves(auth()->user(), $this->buscar, $this->periodoId),
            // Para la confirmación de «Aplicar a todas»: todo el ámbito, sin el filtro de búsqueda.
            'ambito' => $matricula->claves(auth()->user(), '', $this->periodoId),
            'periodos' => PeriodoService::preparables(),
            'periodoActual' => PeriodoService::preparableOActivo($this->periodoId),
            'pestanas' => array_values(array_filter([
                ['etiqueta' => __('Estudiantes'), 'url' => route('matricula'), 'activa' => false],
                ['etiqueta' => __('Claves por asignatura'), 'url' => route('matricula.claves'), 'activa' => true],
                auth()->user()->checkPermissionTo('matricula.cambio.resolver') ? ['etiqueta' => __('Solicitudes de cambio de grupo'), 'url' => route('matricula.solicitudes'), 'activa' => false] : null,
                auth()->user()->hasAnyPermission(['matricula.plazo.gestionar', 'matricula.prorroga.otorgar']) ? ['etiqueta' => __('Plazos'), 'url' => route('matricula.plazos'), 'activa' => false] : null,
            ])),
        ]);
    }
}
