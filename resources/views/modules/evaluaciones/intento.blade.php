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
        {{-- Respaldo local sin conexión (RF16): lo que se responde sin red se guarda en el navegador y se reenvía al
             volver la conexión o al recargar la página. Si el tiempo ya venció, el servidor no lo acepta (ADR-042). --}}
        x-data="{
            saliendo: false,
            clave: @js('intento-'.$intento->ulid),
            leer() { try { return JSON.parse(localStorage.getItem(this.clave) || '{}') } catch (e) { return {} } },
            escribir(datos) { try { Object.keys(datos).length ? localStorage.setItem(this.clave, JSON.stringify(datos)) : localStorage.removeItem(this.clave) } catch (e) {} },
            respaldar(evento) {
                const campo = [...evento.target.attributes].find(a => a.name.startsWith('wire:model'))?.value;
                if (! campo || navigator.onLine) { return }
                const datos = this.leer();
                datos[campo] = evento.target.type === 'radio' ? (evento.target.checked ? evento.target.value : datos[campo]) : evento.target.value;
                this.escribir(datos);
            },
            reenviar() {
                const datos = this.leer();
                for (const [campo, valor] of Object.entries(datos)) { if ($wire.get(campo) != valor) { $wire.set(campo, valor) } }
                this.escribir({});
            },
        }"
        x-init="reenviar()"
        x-on:input="respaldar($event)"
        x-on:change="respaldar($event)"
        x-on:online.window="reenviar()"
        x-on:beforeunload.window="if (! saliendo) { $event.preventDefault(); $event.returnValue = '' }"
    @endunless>
    @unless ($terminado)
        <div wire:offline class="rounded-lg bg-amber-50 p-3 text-sm font-medium text-amber-900 ring-1 ring-amber-200" role="alert">
            {{ __('Sin conexión: sus respuestas se guardan en este navegador y se enviarán al reconectar. El tiempo sigue corriendo en el servidor.') }}
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
            <div class="flex items-center gap-2">
                <x-ui.badge color="verde">{{ __('Intento enviado') }}</x-ui.badge>
                <x-ui.button variante="secundario" tamano="sm" :href="route('secciones.cuestionarios.show', ['seccion' => $cuestionario->seccion, 'cuestionario' => $cuestionario->ulid])">
                    {{ __('Volver al cuestionario') }}
                </x-ui.button>
            </div>
        @endunless
    </div>

    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_17rem]">
        <div class="flex min-w-0 flex-col gap-5">
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
                        @php
                            $totalPuntosQuiz = (float) $preguntas->sum(fn ($p) => (float) ($p->points ?? 1.0));
                            $puntosPreguntaConfig = (float) ($pregunta->points ?? 1.0);
                            $valorPregunta = $totalPuntosQuiz > 0 ? round(10 * $puntosPreguntaConfig / $totalPuntosQuiz, 2) : 1.0;
                            $puntosObtenidos = $pregunta->score !== null ? round((float) $pregunta->score * $valorPregunta, 2) : null;
                        @endphp
                        <p class="mt-4 text-sm">
                            @if ($pregunta->score === null)
                                <x-ui.badge color="ambar">{{ __('Por calificar') }}</x-ui.badge>
                            @else
                                <x-ui.badge :color="(float) $pregunta->score >= 1 ? 'verde' : ((float) $pregunta->score > 0 ? 'ambar' : 'rojo')">
                                    {{ __(':p de :max pts', ['p' => rtrim(rtrim(number_format((float) $puntosObtenidos, 2), '0'), '.'), 'max' => rtrim(rtrim(number_format($valorPregunta, 2), '0'), '.')]) }}
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
                @if (! $lineal)<x-ui.button variante="secundario" wire:click="ir({{ $actual - 1 }})" :disabled="$actual === 0">{{ __('Anterior') }}</x-ui.button>@else<span></span>@endif
                @if ($actual < $preguntas->count() - 1)
                    <div class="flex items-center gap-3">
                        @if ($lineal && ! $terminado)
                            <span class="hidden text-xs text-texto-suave sm:inline">{{ __('No podrá volver a esta pregunta.') }}</span>
                        @endif
                        <x-ui.button wire:click="ir({{ $actual + 1 }})">{{ __('Siguiente') }}</x-ui.button>
                    </div>
                @elseif (! $terminado)
                    {{-- Modal propio en vez del confirm() del navegador (QA Estudiante 22/11). --}}
                    <x-ui.button x-on:click="$dispatch('abrir-modal', 'terminar-intento')">{{ __('Terminar intento') }}</x-ui.button>
                @endif
                @if ($terminado)
                    <x-ui.button variante="secundario" :href="route('secciones.cuestionarios.show', ['seccion' => $cuestionario->seccion, 'cuestionario' => $cuestionario->ulid])">
                        {{ __('Volver al cuestionario') }}
                    </x-ui.button>
                @endif
            </div>
        </div>
        {{-- Avance y selector de preguntas a la derecha de la pregunta (arriba en el teléfono), con el modo de navegación
             explicado: en «una a una» no se vuelve atrás (QA Estudiante 22/11). --}}
        <aside class="order-first flex flex-col gap-3 rounded-xl bg-white p-4 ring-1 ring-borde lg:order-none lg:sticky lg:top-0" aria-label="{{ __('Avance del intento') }}">
            @php($respondidas = $preguntas->count() - $sinResponder)
            <div>
                <p class="text-sm font-semibold text-texto">{{ __(':r de :t respondidas', ['r' => $respondidas, 't' => $preguntas->count()]) }}</p>
                <x-ui.progress class="mt-2" :valor="$preguntas->count() ? 100 * $respondidas / $preguntas->count() : 0" :mostrar="false" :etiqueta="__('Preguntas respondidas')" />
            </div>
            <nav class="grid grid-cols-6 gap-1 sm:grid-cols-8 lg:grid-cols-5" aria-label="{{ __('Preguntas') }}">
                @foreach ($preguntas as $i => $p)
                    @php($respondida = ! empty($opciones[$p->respuesta_id]) || filled($textos[$p->respuesta_id] ?? null))
                    <button type="button" @if (! $lineal) wire:click="ir({{ $i }})" @endif @disabled($lineal)
                        aria-label="{{ __('Pregunta :n: :e', ['n' => $i + 1, 'e' => $i === $actual ? __('actual') : ($respondida ? __('respondida') : __('sin responder'))]) }}"
                        @if ($i === $actual) aria-current="step" @endif
                        @class(['size-9 rounded-lg text-xs font-semibold', 'cursor-not-allowed' => $lineal && $i !== $actual,
                            'bg-marca-600 text-white' => $i === $actual,
                            'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' => $i !== $actual && $respondida,
                            'bg-white ring-1 ring-borde' => $i !== $actual && ! $respondida])>{{ $i + 1 }}</button>
                @endforeach
            </nav>
            <ul class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-texto-suave">
                <li class="flex items-center gap-1"><span class="size-3 rounded bg-marca-600"></span>{{ __('Actual') }}</li>
                <li class="flex items-center gap-1"><span class="size-3 rounded bg-emerald-50 ring-1 ring-emerald-200"></span>{{ __('Respondida') }}</li>
                <li class="flex items-center gap-1"><span class="size-3 rounded bg-white ring-1 ring-borde"></span>{{ __('Sin responder') }}</li>
            </ul>
            @unless ($terminado)
                @if ($lineal)
                    <p class="flex gap-2 rounded-lg bg-amber-50 p-2.5 text-xs text-amber-900 ring-1 ring-amber-200">
                        <x-ui.icon name="lock-closed" class="size-4 shrink-0" />
                        <span>{{ __('Una a una: responda en orden. Al pulsar «Siguiente» no podrá volver a las anteriores ni cambiar sus respuestas.') }}</span>
                    </p>
                @else
                    <p class="flex gap-2 rounded-lg bg-sky-50 p-2.5 text-xs text-sky-900 ring-1 ring-sky-200">
                        <x-ui.icon name="arrows-right-left" class="size-4 shrink-0" />
                        <span>{{ __('Navegación libre: toque un número para ir a esa pregunta. Puede cambiar sus respuestas hasta terminar el intento.') }}</span>
                    </p>
                @endif
            @endunless
        </aside>
    </div>
    @unless ($terminado)
        <x-ui.modal nombre="terminar-intento" :titulo="__('¿Terminar el intento?')" ancho="sm">
            <div class="flex flex-col gap-3 text-sm">
                @if ($sinResponder)
                    <x-ui.alert tipo="aviso">{{ trans_choice('{1} Le queda 1 pregunta sin responder.|[2,*] Le quedan :count preguntas sin responder.', $sinResponder) }}</x-ui.alert>
                @else
                    <p class="text-texto">{{ __('Respondió todas las preguntas.') }}</p>
                @endif
                <p class="text-texto-suave">{{ __('Al terminar se envían sus respuestas y ya no podrá cambiarlas.') }}</p>
            </div>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'terminar-intento')">{{ __('Seguir respondiendo') }}</x-ui.button>
                <x-ui.button x-on:click="saliendo = true" wire:click="terminar" wire:loading.attr="disabled">{{ __('Terminar y enviar') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endunless
</div>
