{{-- Revisión detallada de respuestas del intento para el docente (RF16). --}}
@php
    $fecha = fn ($f) => $f ? \Illuminate\Support\Carbon::parse($f)->timezone('America/El_Salvador')->format('d/m/Y H:i') : null;
    $totalPuntos = (float) collect($preguntas)->sum(fn ($p) => (float) ($p->points ?? 1.0));
    $numFmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $estadoIntento = $estados[$intento->status] ?? ['gris', $intento->status];
@endphp

<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    {{-- Encabezado con datos del estudiante y nota --}}
    <x-ui.card>
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-borde pb-4">
                <div class="flex items-center gap-3">
                    <x-ui.button variante="secundario" tamano="sm" icono="arrow-left" :href="route('secciones.cuestionarios.show', ['seccion' => $seccion, 'cuestionario' => $cuestionarioUlid])">
                        {{ __('Volver al cuestionario') }}
                    </x-ui.button>
                    <div>
                        <h2 class="text-lg font-bold text-texto">{{ $estudiante->first_name }} {{ $estudiante->last_name }}</h2>
                        <p class="text-xs text-texto-suave">{{ $estudiante->email }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <span class="text-xs text-texto-suave block">{{ __('Estado del intento') }}</span>
                        <x-ui.badge :color="$estadoIntento[0]">{{ $estadoIntento[1] }}</x-ui.badge>
                    </div>

                    <div class="rounded-xl bg-marca-50 px-4 py-2 ring-1 ring-marca-200 text-center min-w-24">
                        <span class="text-xs font-semibold uppercase tracking-wider text-marca-800">{{ __('Nota final') }}</span>
                        <p class="text-2xl font-black text-marca-700 leading-tight">{{ number_format($notaCalculada, 1) }}</p>
                        <span class="text-[10px] text-marca-600 font-medium">{{ __('sobre 10.0') }}</span>
                    </div>
                </div>
            </div>

            <dl class="grid gap-3 text-xs sm:grid-cols-3 text-texto-suave">
                <div>
                    <dt class="font-medium text-texto">{{ __('Cuestionario') }}</dt>
                    <dd>{{ $cuestionario->title }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-texto">{{ __('Inicio') }}</dt>
                    <dd>{{ $fecha($intento->started_at) ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-texto">{{ __('Envío') }}</dt>
                    <dd>{{ $fecha($intento->submitted_at) ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    </x-ui.card>

    {{-- Formulario con todas las preguntas y respuestas --}}
    <x-ui.card :titulo="__('Respuestas del examen')" :descripcion="__('Listado completo de preguntas resueltas por el estudiante, con su ponderación y corrección.')">
        <div class="flex flex-col gap-6">
            @foreach ($preguntas as $idx => $p)
                @php
                    $puntosConfig = (float) ($p->points ?? 1.0);
                    $maxPorPregunta = $totalPuntos > 0 ? round(10 * $puntosConfig / $totalPuntos, 2) : 1.0;
                    $ptsReales = round(($p->puntos_estimados / max(0.01, $puntosConfig)) * $maxPorPregunta, 2);
                    $esCorrecta = $p->puntos_estimados >= $puntosConfig;
                    $esParcial = $p->puntos_estimados > 0 && ! $esCorrecta;
                    $esEnsayoPendiente = $p->question_type === 'essay' && $p->score === null;
                @endphp

                <div class="rounded-2xl border border-borde bg-white p-5 shadow-xs flex flex-col gap-4" wire:key="pregunta-rev-{{ $p->respuesta_id }}">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="grid size-7 place-items-center rounded-lg bg-zinc-100 text-xs font-bold text-zinc-700">
                                {{ $idx + 1 }}
                            </span>
                            <span class="text-xs font-semibold uppercase tracking-wider text-texto-suave">
                                {{ __(match ($p->question_type) {
                                    'multiple_choice' => 'Opción múltiple',
                                    'true_false' => 'Verdadero / Falso',
                                    'short_answer' => 'Respuesta corta',
                                    'essay' => 'Ensayo',
                                    default => 'Pregunta',
                                }) }}
                            </span>
                        </div>

                        <div>
                            @if ($esCorrecta)
                                <x-ui.badge color="verde">
                                    <x-ui.icon name="check" class="size-3.5 mr-1" />
                                    {{ __('Correcta (:pts de :max pts)', ['pts' => $numFmt($maxPorPregunta), 'max' => $numFmt($maxPorPregunta)]) }}
                                </x-ui.badge>
                            @elseif ($esParcial)
                                <x-ui.badge color="ambar">
                                    {{ __('Parcial (:pts de :max pts)', ['pts' => $numFmt($ptsReales), 'max' => $numFmt($maxPorPregunta)]) }}
                                </x-ui.badge>
                            @elseif ($esEnsayoPendiente)
                                <x-ui.badge color="ambar">
                                    <x-ui.icon name="clock" class="size-3.5 mr-1" />
                                    {{ __('Ensayo por calificar (:max pts)', ['max' => $numFmt($maxPorPregunta)]) }}
                                </x-ui.badge>
                            @else
                                <x-ui.badge color="rojo">
                                    <x-ui.icon name="x-mark" class="size-3.5 mr-1" />
                                    {{ __('Incorrecta (0 de :max pts)', ['max' => $numFmt($maxPorPregunta)]) }}
                                </x-ui.badge>
                            @endif
                        </div>
                    </div>

                    {{-- Enunciado --}}
                    <div class="text-sm font-medium text-zinc-900 leading-relaxed">
                        {{ $p->statement }}
                    </div>

                    {{-- Desglose de respuesta según tipo --}}
                    @if (in_array($p->question_type, ['multiple_choice', 'true_false'], true))
                        <div class="flex flex-col gap-2">
                            <span class="text-xs font-medium text-texto-suave">{{ __('Opciones:') }}</span>
                            @foreach ($p->opciones as $opc)
                                @php
                                    $seleccionada = (int) $p->selected_option_id === (int) $opc->id;
                                    $opcCorrecta = (bool) $opc->is_correct;
                                @endphp
                                <div @class([
                                    'flex items-center justify-between rounded-xl px-4 py-2.5 text-sm transition',
                                    'bg-emerald-50 text-emerald-950 ring-1 ring-emerald-300 font-semibold' => $opcCorrecta,
                                    'bg-red-50 text-red-950 ring-1 ring-red-300 font-semibold' => $seleccionada && ! $opcCorrecta,
                                    'bg-zinc-50/70 text-zinc-700 ring-1 ring-borde/50' => ! $seleccionada && ! $opcCorrecta,
                                ])>
                                    <div class="flex items-center gap-3">
                                        <span @class([
                                            'grid size-5 place-items-center rounded-full text-xs',
                                            'bg-emerald-600 text-white' => $opcCorrecta,
                                            'bg-red-600 text-white' => $seleccionada && ! $opcCorrecta,
                                            'border border-zinc-400 bg-white' => ! $seleccionada && ! $opcCorrecta,
                                        ])>
                                            @if ($opcCorrecta)
                                                <x-ui.icon name="check" class="size-3" />
                                            @elseif ($seleccionada)
                                                <x-ui.icon name="x-mark" class="size-3" />
                                            @endif
                                        </span>
                                        <span>{{ $opc->text }}</span>
                                    </div>

                                    <div>
                                        @if ($seleccionada && $opcCorrecta)
                                            <span class="text-xs font-semibold text-emerald-800">{{ __('Respuesta del estudiante (Correcta)') }}</span>
                                        @elseif ($seleccionada)
                                            <span class="text-xs font-semibold text-red-800">{{ __('Respuesta del estudiante') }}</span>
                                        @elseif ($opcCorrecta)
                                            <span class="text-xs font-medium text-emerald-800">{{ __('Opción correcta') }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @elseif ($p->question_type === 'short_answer')
                        <div class="flex flex-col gap-3 rounded-xl bg-zinc-50 p-4 ring-1 ring-borde">
                            <div>
                                <span class="text-xs font-medium text-texto-suave block">{{ __('Respuesta enviada por el estudiante:') }}</span>
                                <p @class([
                                    'mt-1 font-mono text-sm px-3 py-2 rounded-lg font-semibold inline-block',
                                    'bg-emerald-100 text-emerald-900 ring-1 ring-emerald-300' => $esCorrecta,
                                    'bg-red-100 text-red-900 ring-1 ring-red-300' => ! $esCorrecta && filled($p->answer_text),
                                    'bg-zinc-200 text-zinc-600 italic' => blank($p->answer_text),
                                ])>
                                    {{ filled($p->answer_text) ? $p->answer_text : __('Sin respuesta') }}
                                </p>
                            </div>

                            @php
                                $aceptadas = json_decode((string) $p->accepted_answers, true) ?: [];
                            @endphp
                            @if ($aceptadas !== [])
                                <div class="border-t border-borde/70 pt-2 text-xs text-texto-suave">
                                    <span class="font-medium text-texto">{{ __('Respuestas aceptadas por el sistema:') }}</span>
                                    <span class="font-mono ml-1">{{ implode(' · ', $aceptadas) }}</span>
                                </div>
                            @endif
                        </div>
                    @elseif ($p->question_type === 'essay')
                        <div class="flex flex-col gap-2 rounded-xl bg-zinc-50 p-4 ring-1 ring-borde">
                            <span class="text-xs font-medium text-texto-suave">{{ __('Ensayo escrito por el estudiante:') }}</span>
                            @if (filled($p->answer_text))
                                <div class="rounded-lg bg-white p-3 text-sm text-zinc-800 whitespace-pre-wrap ring-1 ring-borde">
                                    {{ $p->answer_text }}
                                </div>
                            @else
                                <p class="text-sm italic text-zinc-500">{{ __('El estudiante no escribió respuesta para este ensayo.') }}</p>
                            @endif
                        </div>
                    @endif

                    {{-- Justificación si existe --}}
                    @if ($p->justification)
                        <div class="rounded-xl bg-blue-50/80 p-3 text-xs text-blue-900 ring-1 ring-blue-200">
                            <p class="font-semibold text-blue-950">{{ __('Justificación / Solución:') }}</p>
                            <p class="mt-0.5 whitespace-pre-line">{{ $p->justification }}</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-ui.card>

    <div class="flex justify-end pb-8">
        <x-ui.button variante="secundario" icono="arrow-left" :href="route('secciones.cuestionarios.show', ['seccion' => $seccion, 'cuestionario' => $cuestionarioUlid])">
            {{ __('Volver al cuestionario') }}
        </x-ui.button>
    </div>
</div>
