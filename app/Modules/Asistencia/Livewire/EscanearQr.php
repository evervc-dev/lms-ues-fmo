<?php

namespace App\Modules\Asistencia\Livewire;

use App\Modules\Asistencia\Services\AsistenciaQrService;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Marcar asistencia (RF20, RF28, maqueta 04): lee el QR con la cámara del teléfono registrado (ADR-045, ADR-046)
 * o recibe el código temporal que dicta el docente. Si se llegó desde la cámara nativa, el QR viene en `?qr=`.
 */
#[Layout('layouts::app')]
class EscanearQr extends Component
{
    use PaginaLivewire;

    public string $codigo = '';

    public ?string $registrado = null;

    #[Locked]
    public string $qrInicial = '';

    /** @var array{resultado: string, mensaje: string, intentosRestantes: int, hora: string}|null */
    public ?array $resultado = null;

    public string $qr = '';

    public ?float $latitud = null;

    public ?float $longitud = null;

    public ?float $precision = null;

    public ?string $tokenLocal = null;

    public function mount(): void
    {
        $this->qrInicial = mb_substr((string) request()->query('qr', ''), 0, 300);
    }

    /**
     * Devuelve el resultado también a JavaScript, que lo muestra en los pasos del lector.
     *
     * @return array{resultado: string, mensaje: string, intentosRestantes: int, hora: string}
     */
    public function marcar(string $qr, ?float $latitud, ?float $longitud, ?float $precision, ?string $tokenLocal, AsistenciaQrService $asistencia): array
    {
        [$this->qr, $this->latitud, $this->longitud, $this->precision, $this->tokenLocal] = [$qr, $latitud, $longitud, $precision, $tokenLocal];
        $this->validate([
            'qr' => 'required|string|max:300',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'precision' => 'nullable|numeric|min:0|max:100000',
            'tokenLocal' => 'nullable|string|max:128',
        ]);

        $respuesta = $asistencia->marcar(auth()->user(), $this->qr, $this->latitud, $this->longitud, $this->precision,
            request()->cookie('device_token'), $this->tokenLocal);

        // Si solo coincidió una de las dos copias del token de dispositivo, se vuelve a escribir la otra (ADR-045).
        if ($respuesta['cookie'] !== null) {
            cookie()->queue(cookie('device_token', $respuesta['cookie'], 525600, '/', null, true, true, false, 'lax'));
        }
        if ($respuesta['local'] !== null) {
            $this->dispatch('dispositivo-registrado', token: $respuesta['local']);
        }

        $this->resultado = ['resultado' => $respuesta['resultado'], 'mensaje' => $respuesta['mensaje'], 'intentosRestantes' => $respuesta['intentosRestantes'], 'hora' => now()->format('H:i')];
        // qrInicial no se resetea: va en el x-data de la raíz y, si cambia, Alpine reinicia el lector y borra sus pasos.
        $this->reset('qr', 'latitud', 'longitud', 'precision', 'tokenLocal');

        return $this->resultado;
    }

    public function usarCodigo(AsistenciaService $asistencia): void
    {
        $this->validate(['codigo' => 'required|digits:6'], [], ['codigo' => 'código']);
        $this->registrado = $asistencia->usarCodigo(auth()->user(), $this->codigo);
        $this->reset('codigo');
    }

    public function render(): View
    {
        return $this->pagina('modules.asistencia.escanear');
    }
}
