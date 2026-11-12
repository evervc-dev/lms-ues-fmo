{{-- Proyección del QR (RF20, maqueta 22): solo el QR, el tiempo y los presentes; nunca nombres ni rechazos. --}}
<div class="flex w-full flex-col items-center gap-8" @if ($abierta) wire:poll.2s @endif>
    {{-- QA sesión 4: salida discreta, para que no distraiga en el proyector; Esc hace lo mismo. --}}
    <a href="{{ $volver }}" x-data x-on:keydown.escape.window="window.location.href = $el.href"
        class="fixed left-4 top-4 inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-white/50 ring-1 ring-white/15 hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-white">
        <x-ui.icon name="arrow-left" class="size-4" />{{ __('Salir de la proyección') }}
    </a>
    @if ($abierta)
        <div class="aspect-square w-full max-w-[min(80vh,36rem)] rounded-3xl bg-white p-6 [&_svg]:size-full" aria-label="{{ __('Código QR de asistencia') }}">
            {!! $svg !!}
        </div>
        <div class="flex flex-wrap items-center justify-center gap-8 text-center">
            <div>
                <p class="text-sm uppercase tracking-wide text-white/60">{{ __('Cierra en') }}</p>
                {{-- La hora de fin se fija al crear el elemento: el poll no lo recrea y el contador no se desvía. --}}
                <p class="font-mono text-4xl font-semibold"
                    x-data="{ fin: Date.now() + {{ $segundos }} * 1000, s: {{ $segundos }}, reloj: null, init() { this.reloj = setInterval(() => this.s = Math.max(0, Math.round((this.fin - Date.now()) / 1000)), 1000) }, destroy() { clearInterval(this.reloj) } }"
                    x-text="`${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`"></p>
            </div>
            <div>
                <p class="text-sm uppercase tracking-wide text-white/60">{{ __('Presentes') }}</p>
                <p class="font-mono text-4xl font-semibold">{{ $presentes }}</p>
            </div>
        </div>
        <p class="max-w-xl text-center text-lg text-white/80">{{ __('Abra «Marcar asistencia» en el teléfono que registró y escanee este código. El código cambia cada :n segundos.', ['n' => $sesion->qr_rotation_seconds]) }}</p>
    @else
        <x-ui.icon name="lock-closed" class="size-20 text-white/60" />
        <p class="text-3xl font-semibold">{{ __('La sesión de asistencia está cerrada') }}</p>
        <p class="text-lg text-white/70">{{ __('Presentes: :n', ['n' => $presentes]) }}</p>
    @endif
</div>
