<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\CalendarioPeriodoService;
use App\Modules\Asistencia\Services\CalendarConexionService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Calendario de evaluaciones del período (RF06, CP-RF06-04, ADR-068): el Administrador fija las fechas globales,
 * el Jefe las de su departamento y el Coordinador las de su carrera, y cada quien las sincroniza con su propio
 * Google Calendar. Sin período en la ruta se usa el vigente. El alcance lo aplica CalendarioPeriodoService.
 * Se ve como un calendario de mes, dentro de las fechas del período; debajo queda la lista con la sincronización.
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

    /** Mes que se muestra (Y-m), siempre dentro del período. */
    #[Url(except: '')]
    public string $mes = '';

    public function mount(CalendarioPeriodoService $calendario, ?string $periodo = null): void
    {
        $this->periodoId = $periodo !== null
            ? (int) $periodo
            : ($calendario->periodoVigente() ?? abort(404, __('No hay ningún período en curso ni planificado.')));

        $ambitos = $calendario->ambitos(auth()->user());
        $this->ambito = $ambitos[0]['valor'] ?? 'global';
    }

    public function mesAnterior(CalendarioPeriodoService $calendario): void
    {
        $this->moverMes(-1, $calendario);
    }

    public function mesSiguiente(CalendarioPeriodoService $calendario): void
    {
        $this->moverMes(1, $calendario);
    }

    /**
     * Pulsar un día del calendario abre «Agregar fecha» con ese día como inicio.
     */
    public function elegirDia(string $fecha): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return;
        }
        $this->resetValidation();
        $this->inicioFecha = $fecha.'T08:00';
        $this->finFecha = null;
        $this->dispatch('abrir-modal', 'agregar-fecha');
    }

    private function moverMes(int $paso, CalendarioPeriodoService $calendario): void
    {
        [$primero, $ultimo] = $this->limitesDelPeriodo($calendario);
        $destino = $this->mesVisible($calendario)->addMonthsNoOverflow($paso);
        $this->mes = ($destino->lt($primero) ? $primero : ($destino->gt($ultimo) ? $ultimo : $destino))->format('Y-m');
    }

    /**
     * @return array{0: Carbon, 1: Carbon} Primer y último mes del período.
     */
    private function limitesDelPeriodo(CalendarioPeriodoService $calendario): array
    {
        $periodo = $calendario->periodo(auth()->user(), $this->periodoId);

        return [Carbon::parse($periodo->start_date)->startOfMonth(), Carbon::parse($periodo->end_date)->startOfMonth()];
    }

    /**
     * El mes elegido, o el de hoy si cae dentro del período, o el primero del período.
     */
    private function mesVisible(CalendarioPeriodoService $calendario): Carbon
    {
        [$primero, $ultimo] = $this->limitesDelPeriodo($calendario);
        $mes = preg_match('/^\d{4}-\d{2}$/', $this->mes) ? Carbon::createFromFormat('Y-m-d', $this->mes.'-01')->startOfDay() : now()->startOfMonth();

        return $mes->lt($primero) ? $primero : ($mes->gt($ultimo) ? $ultimo : $mes);
    }

    /**
     * Semanas del mes (de lunes a domingo) con las fechas de cada día. Un evento de varios días aparece en todos.
     *
     * @param  Collection<int, \stdClass>  $fechas
     * @return list<list<array{fecha: string, dia: int, delMes: bool, enPeriodo: bool, hoy: bool, eventos: Collection<int, \stdClass>}>>
     */
    private function semanas(Carbon $mes, object $periodo, Collection $fechas): array
    {
        $zona = config('app.timezone');
        $inicioPeriodo = Carbon::parse($periodo->start_date)->toDateString();
        $finPeriodo = Carbon::parse($periodo->end_date)->toDateString();
        $dia = $mes->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $fin = $mes->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $semanas = [];
        while ($dia->lte($fin)) {
            $semana = [];
            for ($i = 0; $i < 7; $i++) {
                $fecha = $dia->toDateString();
                $semana[] = [
                    'fecha' => $fecha, 'dia' => $dia->day, 'delMes' => $dia->month === $mes->month, 'hoy' => $dia->isToday(),
                    'enPeriodo' => $fecha >= $inicioPeriodo && $fecha <= $finPeriodo,
                    'eventos' => $fechas->filter(function (object $f) use ($fecha, $zona) {
                        $desde = Carbon::parse($f->starts_at)->timezone($zona)->toDateString();
                        $hasta = $f->ends_at ? Carbon::parse($f->ends_at)->timezone($zona)->toDateString() : $desde;

                        return $fecha >= $desde && $fecha <= $hasta;
                    })->values(),
                ];
                $dia->addDay();
            }
            $semanas[] = $semana;
        }

        return $semanas;
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
        $fechas = $calendario->eventos($usuario, $this->periodoId);
        $mes = $this->mesVisible($calendario);
        [$primero, $ultimo] = $this->limitesDelPeriodo($calendario);

        return $this->pagina('modules.academica.calendario-evaluaciones', [
            'periodo' => $periodo,
            'fechas' => $fechas,
            'mesVisible' => $mes,
            'semanas' => $this->semanas($mes, $periodo, $fechas),
            'hayAnterior' => $mes->gt($primero),
            'haySiguiente' => $mes->lt($ultimo),
            'ambitos' => $calendario->ambitos($usuario),
            'calendarHabilitado' => $conexion->habilitada(),
            'calendarConectado' => $conexion->conectado($usuario),
        ], titulo: __('Calendario de evaluaciones'));
    }
}
