{{-- Intento de cuestionario (RF16, maqueta 10) · Layout Examen. --}}
@php
    $terminado = $intento->status !== 'in_progress';
    $lineal = $cuestionario->navigation === 'linear' && ! $terminado;
    $revision = $terminado && $cuestionario->revision_visible;
    $sinResponder = $preguntas->filter(fn ($p) => empty($opciones[$p->respuesta_id]) && blank($textos[$p->respuesta_id] ?? null))->count();
@endphp
<div class="flex flex-col gap-5"
    @unless ($terminado)
        wire:poll.30s.keep-alive="guardar"
        x-data="{ saliendo: false }"
        x-on:beforeunload.window="if (! saliendo) { $event.preventDefault(); $event.returnValue = '' }"
    @endunless>
    @unless ($terminado)
        <div wire:offline class="rounded-lg bg-amber-50 p-3 text-sm font-medium text-amber-900 ring-1 ring-amber-200" role="alert">
            {{ __('Sin conexión: no cierre esta página. Sus respuestas se guardarán al reconectar y el tiempo sigue corriendo en el servidor.') }}
        </div>
    @endunless
    <div class="flex items-center justify-between gap-3 rounded-xl bg-white p-3 text-sm ring-1 ring-borde">
        <span class="font-medium">{{ __('Pregunta :n de :t', ['n' => $actual + 1, 't' => $preguntas->count()]) }}</span>
        @unless ($terminado)
            {{-- El contador se ancla al servidor y a la hora real: cada respuesta del servidor fija el fin (p. ej. minutos
                 extra del docente) y una pestaña en segundo plano no se atrasa. En 0 pregunta al servidor antes de enviar. --}}
            <span x-data="{ fin: Date.now() + {{ $segundos }} * 1000, s: {{ $segundos }}, pidiendo: false, get t() { const m = Math.floor(this.s / 60), x = this.s % 60; return `${m}:${String(x).padStart(2, '0')}` } }"
                x-init="$wire.$watch('segundos', v => { fin = Date.now() + v * 1000; pidiendo = false });
                    setInterval(() => {
                        s = Math.max(0, Math.round((fin - Date.now()) / 1000));
                        if (s === 0 && ! pidiendo) { pidiendo = true; saliendo = true; $wire.tiempoAgotado().then(() => { pidiendo = false; if ($wire.segundos > 0) saliendo = false }) }
                    }, 1000)"
                :class="s < 60 ? 'text-red-700' : 'text-texto'" class="flex items-center gap-1 font-mono font-semibold" role="timer" aria-live="off">
                <x-ui.icon name="clock" class="size-4" /><span x-text="t"></span>
            </span>
            <span class="text-xs text-texto-suave"><span wire:loading.remove wire:target="guardar, opciones, textos">✓ {{ __('Guardado') }}</span><span wire:loading wire:target="guardar, opciones, textos">{{ __('Guardando…') }}</span></span>
        @else
            <x-ui.badge color="verde">{{ __('Intento enviado') }}</x-ui.badge>
        @endunless
    </div>

    @if ($pregunta)
        <section class="rounded-xl bg-white p-5 ring-1 ring-borde" wire:key="pregunta-{{ $pregunta->respuesta_id }}">
            <p class="text-base font-medium text-texto">{{ $pregunta->statement }}</p>
            <div class="mt-4 flex flex-col gap-2">
                @if (in_array($pregunta->question_type, ['multiple_choice', 'true_false'], true))
                    @foreach ($pregunta->opciones as $o)
                        <label @class(['flex items-center gap-3 rounded-lg p-3 ring-1 transition', 'ring-marca-300 bg-marca-50' => (int) ($opciones[$pregunta->respuesta_id] ?? 0) === (int) $o->id, 'ring-borde hover:bg-zinc-50' => (int) ($opciones[$pregunta->respuesta_id] ?? 0) !== (int) $o->id])>
                            <input type="radio" wire:model.live="opciones.{{ $pregunta->respuesta_id }}" value="{{ $o->id }}" @disabled($terminado) class="text-marca-600 focus:ring-marca-600">
                            <span class="text-sm">{{ $o->text }}</span>
                            @if ($revision && $o->is_correct)
                                <x-ui.badge color="verde" class="ml-auto">{{ __('Correcta') }}</x-ui.badge>
                            @endif
                        </label>
                    @endforeach
                @elseif ($pregunta->question_type === 'short_answer')
                    <x-ui.input wire:model.live.debounce.2000ms="textos.{{ $pregunta->respuesta_id }}" :disabled="$terminado" :aria-label="__('Respuesta')" />
                @else
                    <x-ui.textarea wire:model.live.debounce.2000ms="textos.{{ $pregunta->respuesta_id }}" rows="8" :disabled="$terminado" :aria-label="__('Respuesta')" />
                @endif
            </div>
            @if ($revision)
                <p class="mt-4 text-sm">
                    @if ($pregunta->score === null)
                        <x-ui.badge color="ambar">{{ __('Por calificar') }}</x-ui.badge>
                    @else
                        <x-ui.badge :color="(float) $pregunta->score >= 1 ? 'verde' : ((float) $pregunta->score > 0 ? 'ambar' : 'rojo')">
                            {{ __(':p de 1 punto', ['p' => rtrim(rtrim(number_format((float) $pregunta->score, 2), '0'), '.')]) }}
                        </x-ui.badge>
                    @endif
                </p>
                @if ($pregunta->justification)
                    <x-ui.alert class="mt-3" tipo="info" :titulo="__('Justificación')">{{ $pregunta->justification }}</x-ui.alert>
                @endif
            @endif
        </section>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2">
        @if (! $lineal)<x-ui.button variante="secundario" wire:click="ir({{ $actual - 1 }})" :disabled="$actual === 0">{{ __('◀ Anterior') }}</x-ui.button>@else<span></span>@endif
        <nav class="flex flex-wrap gap-1" aria-label="{{ __('Preguntas') }}">
            @foreach ($preguntas as $i => $p)
                <button type="button" @if (! $lineal) wire:click="ir({{ $i }})" @endif @disabled($lineal)
                    @class(['size-8 rounded-lg text-xs font-semibold', 'bg-marca-600 text-white' => $i === $actual, 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' => $i !== $actual && (! empty($opciones[$p->respuesta_id]) || filled($textos[$p->respuesta_id] ?? null)), 'bg-white ring-1 ring-borde' => $i !== $actual && empty($opciones[$p->respuesta_id]) && blank($textos[$p->respuesta_id] ?? null)])>{{ $i + 1 }}</button>
            @endforeach
        </nav>
        @if ($actual < $preguntas->count() - 1)
            <x-ui.button wire:click="ir({{ $actual + 1 }})">{{ __('Siguiente ▶') }}</x-ui.button>
        @elseif (! $terminado)
            <x-ui.button x-on:click="saliendo = true" wire:click="terminar" wire:confirm="{{ $sinResponder ? __('Le quedan :n preguntas sin responder. ¿Terminar el intento?', ['n' => $sinResponder]) : __('¿Terminar el intento?') }}">{{ __('Terminar intento') }}</x-ui.button>
        @endif
    </div>
</div>
