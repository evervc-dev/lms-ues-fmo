{{-- Marcar asistencia (RF20, RF28, maqueta 04). --}}
<div class="mx-auto flex w-full max-w-md flex-col gap-5"
    x-data="lectorAsistencia(@js($qrInicial), @js([
        'ubicacion' => __('Ubicación'),
        'precision' => __('Precisión ±:n m'),
        'coordenadas' => __('Lat :lat, Lng :lng'),
        'ubicacionFallo' => __('Sin ubicación: el intento se registrará como «sin ubicación».'),
        'camara' => __('Cámara'),
        'camaraFallo' => __('No se pudo abrir la cámara. Permita el acceso a la cámara en su navegador o pida un código temporal a su docente.'),
        'tocar' => __('Toque la imagen para iniciar la cámara.'),
        'lectura' => __('Buscando el QR'),
        'cancelado' => __('Cancelado'),
        'envio' => __('Registro en el servidor'),
        'sinResultado' => __('El servidor no devolvió un resultado. Revise los avisos de abajo.'),
        'sinRespuesta' => __('El servidor no respondió en 20 segundos. Revise su conexión e intente otra vez.'),
        'envioFallo' => __('No se pudo enviar al servidor.'),
    ]), @js((bool) config('app.debug')))"
    x-on:dispositivo-registrado.window="guardarToken($event.detail.token)">
    <x-ui.encabezado :titulo="__('Registro de asistencia')" :subtitulo="__('Escanee el QR que proyecta su docente o ingrese el código temporal que le dictó.')" />

    <div class="relative flex aspect-square flex-col items-center justify-center gap-3 overflow-hidden rounded-2xl bg-marca-950 p-6 text-center text-white">
        <video id="lector-qr-video" x-ref="video" x-show="estado === 'leyendo'" x-cloak muted playsinline class="absolute inset-0 size-full object-cover"></video>

        {{-- Visor: dónde poner el QR, y su contorno en verde cuando se detecta. --}}
        <div x-show="estado === 'leyendo'" x-cloak class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
            <div class="relative aspect-square w-2/3 transition-colors" :class="contorno ? 'text-green-400' : 'text-white/90'">
                <span class="absolute left-0 top-0 size-8 rounded-tl-xl border-l-4 border-t-4 border-current"></span>
                <span class="absolute right-0 top-0 size-8 rounded-tr-xl border-r-4 border-t-4 border-current"></span>
                <span class="absolute bottom-0 left-0 size-8 rounded-bl-xl border-b-4 border-l-4 border-current"></span>
                <span class="absolute bottom-0 right-0 size-8 rounded-br-xl border-b-4 border-r-4 border-current"></span>
            </div>
        </div>
        <svg x-show="contorno" x-cloak class="pointer-events-none absolute inset-0 size-full" aria-hidden="true">
            <polygon :points="contorno" fill="rgb(74 222 128 / 0.25)" stroke="rgb(74 222 128)" stroke-width="4" stroke-linejoin="round" />
        </svg>

        <template x-if="estado === 'inicio'">
            <div class="flex flex-col items-center gap-3">
                <x-ui.icon name="qr-code" class="size-16 text-marca-300" />
                <p class="text-sm text-marca-200">{{ __('Use el teléfono que registró en Mi perfil. Se le pedirá permiso para la ubicación y la cámara.') }}</p>
                <x-ui.button icono="qr-code" x-on:click="empezar()">{{ $qrInicial !== '' ? __('Registrar asistencia') : __('Escanear QR') }}</x-ui.button>
            </div>
        </template>
        <template x-if="estado === 'ubicando'">
            <div class="flex flex-col items-center gap-3">
                <x-ui.icon name="map-pin" class="size-16 animate-pulse text-marca-300" />
                <p class="text-sm text-marca-200">{{ __('Obteniendo su ubicación…') }}</p>
            </div>
        </template>
        <template x-if="estado === 'leyendo'">
            <div class="absolute inset-x-0 bottom-0 flex flex-col items-center gap-2 bg-black/50 p-3">
                <p class="text-sm" x-text="contorno ? @js(__('QR detectado')) : (tocar ? @js(__('Toque la imagen para iniciar la cámara.')) : @js(__('Ponga el QR proyectado dentro del recuadro.')))"></p>
                <x-ui.button variante="secundario" tamano="sm" x-on:click="cancelar()">{{ __('Cancelar') }}</x-ui.button>
            </div>
        </template>
        <template x-if="estado === 'enviando'">
            <p class="text-sm text-marca-200">{{ __('Registrando…') }}</p>
        </template>
    </div>

    {{-- Pasos del lector: dónde se detuvo, para el estudiante y para Soporte. --}}
    <ol x-show="pasos.length" x-cloak class="flex flex-col gap-1.5 rounded-xl border border-borde bg-superficie p-3 text-sm" aria-live="polite">
        <template x-for="(p, i) in pasos" :key="i">
            <li class="flex items-start gap-2">
                <span class="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                    :class="{ 'bg-green-100 text-green-800': p.estado === 'ok', 'bg-red-100 text-red-800': p.estado === 'error', 'bg-amber-100 text-amber-800': p.estado === 'aviso', 'animate-pulse bg-marca-100 text-marca-800': p.estado === 'curso' }"
                    x-text="{ ok: '✓', error: '✗', aviso: '!', curso: '…' }[p.estado]"></span>
                <span><span class="font-medium" x-text="p.texto"></span><span x-show="p.detalle" class="block break-all text-xs text-texto-suave" x-text="p.detalle"></span></span>
            </li>
        </template>
    </ol>

    <template x-if="error">
        <x-ui.alert tipo="error"><span x-text="error"></span></x-ui.alert>
    </template>

    <div id="resultado-asistencia" class="flex flex-col gap-3">
        {{-- Todos los errores de formato, no solo el del QR: si alguno quedara oculto, el escaneo parecería no hacer nada. --}}
        @foreach (['qr', 'latitud', 'longitud', 'precision', 'tokenLocal'] as $campo)
            @error($campo)
                <x-ui.alert tipo="error">{{ $message }}</x-ui.alert>
            @enderror
        @endforeach

        @if ($resultado)
            @if ($resultado['resultado'] === 'ok')
                <x-ui.alert tipo="exito" :titulo="__('Asistencia registrada')">{{ $resultado['hora'] }}</x-ui.alert>
            @else
                <x-ui.alert tipo="error" :titulo="__($resultado['mensaje'])">
                    @if ($resultado['intentosRestantes'] > 0 && ! in_array($resultado['resultado'], ['already_recorded', 'session_closed', 'not_enrolled'], true))
                        {{ trans_choice('Le queda :n intento.|Le quedan :n intentos.', $resultado['intentosRestantes'], ['n' => $resultado['intentosRestantes']]) }}
                    @endif
                </x-ui.alert>
            @endif
        @endif
    </div>

    @if ($registrado)
        <x-ui.alert tipo="exito" :titulo="__('Asistencia registrada')">{{ __(':m · :h', ['m' => $registrado, 'h' => now()->timezone('America/El_Salvador')->format('H:i')]) }}</x-ui.alert>
    @endif

    <x-ui.card :titulo="__('Ingresar código temporal')">
        <form wire:submit="usarCodigo" class="flex items-end gap-2">
            <x-ui.field class="flex-1" :etiqueta="__('Código de 6 dígitos')" para="codigo" error="codigo">
                <x-ui.input id="codigo" wire:model="codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" class="font-mono text-lg tracking-widest" />
            </x-ui.field>
            <x-ui.button type="submit">{{ __('Registrar') }}</x-ui.button>
        </form>
        <p class="mt-2 text-xs text-texto-suave">{{ __('El código vence a los 30 segundos y solo sirve para usted.') }}</p>
    </x-ui.card>
</div>
