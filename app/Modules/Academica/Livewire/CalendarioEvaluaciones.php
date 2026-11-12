<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\CalendarioPeriodoService;
use App\Modules\Asistencia\Services\CalendarConexionService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Calendario de evaluaciones del período (RF06, CP-RF06-04, ADR-068): el Administrador fija las fechas globales,
 * el Jefe las de su departamento y el Coordinador las de su carrera, y cada quien las sincroniza con su propio
 * Google Calendar. Sin período en la ruta se usa el vigente. El alcance lo aplica CalendarioPeriodoService.
 */
#[Layout('layouts::app')]
class CalendarioEvaluaciones extends Component
{
    use PaginaLivewire;

    public int $periodoId;

    public string $tituloFecha = '';

    public string $inicioFecha = '';

    public ?string $finFecha = null;

    public string $ambito = 'global';

    public function mount(CalendarioPeriodoService $calendario, ?string $periodo = null): void
    {
        $this->periodoId = $periodo !== null
            ? (int) $periodo
            : ($calendario->periodoVigente() ?? abort(404, __('No hay ningún período en curso ni planificado.')));

        $ambitos = $calendario->ambitos(auth()->user());
        $this->ambito = $ambitos[0]['valor'] ?? 'global';
    }

    /**
     * Agrega una fecha (CP-RF06-04). El formato se valida aquí; las fechas del período y el ámbito, en el servicio.
     */
    public function agregarFecha(CalendarioPeriodoService $calendario): void
    {
        $this->finFecha = blank($this->finFecha) ? null : $this->finFecha;
        $this->validate([
            'tituloFecha' => 'required|string|min:3|max:255',
            'inicioFecha' => 'required|date',
            'finFecha' => 'nullable|date',
            'ambito' => 'required|string',
        ], [], ['tituloFecha' => 'evento', 'inicioFecha' => 'inicio', 'finFecha' => 'fin', 'ambito' => 'ámbito']);

        try {
            $calendario->agregar(auth()->user(), $this->periodoId, $this->tituloFecha, str_replace('T', ' ', $this->inicioFecha),
                $this->finFecha === null ? null : str_replace('T', ' ', $this->finFecha), $this->ambito);
        } catch (ValidationException $e) {
            $this->setErrorBag(collect($e->errors())->mapWithKeys(fn ($m, $campo) => [
                ['inicio' => 'inicioFecha', 'fin' => 'finFecha', 'ambito' => 'ambito'][$campo] ?? 'tituloFecha' => $m,
            ])->all());

            return;
        }
        $this->reset('tituloFecha', 'inicioFecha', 'finFecha');
        $this->dispatch('cerrar-modal', 'agregar-fecha');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Fecha agregada al calendario de evaluaciones.'));
    }

    public function eliminarFecha(int $evento, CalendarioPeriodoService $calendario): void
    {
        $this->intentar(fn () => $calendario->eliminar(auth()->user(), $evento), __('Fecha eliminada. Si estaba en Google Calendar, se avisará a los invitados.'));
    }

    public function sincronizarCalendario(CalendarioPeriodoService $calendario): void
    {
        $this->intentar(function () use ($calendario): void {
            $n = $calendario->sincronizar(auth()->user(), $this->periodoId);
            $this->dispatch('aviso', tipo: 'exito', mensaje: trans_choice('{1} Se está enviando 1 fecha a su Google Calendar.|[2,*] Se están enviando :count fechas a su Google Calendar.', $n));
        }, null);
    }

    private function intentar(callable $accion, ?string $mensaje): void
    {
        try {
            $accion();
            if ($mensaje !== null) {
                $this->dispatch('aviso', tipo: 'exito', mensaje: $mensaje);
            }
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function render(CalendarioPeriodoService $calendario, CalendarConexionService $conexion): View
    {
        $usuario = auth()->user();
        $periodo = $calendario->periodo($usuario, $this->periodoId);

        return $this->pagina('modules.academica.calendario-evaluaciones', [
            'periodo' => $periodo,
            'fechas' => $calendario->eventos($usuario, $this->periodoId),
            'ambitos' => $calendario->ambitos($usuario),
            'calendarHabilitado' => $conexion->habilitada(),
            'calendarConectado' => $conexion->conectado($usuario),
        ], titulo: __('Calendario de evaluaciones'));
    }
}
