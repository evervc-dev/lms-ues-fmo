{{-- Nueva pregunta de un banco (RF16, ADR-009, maqueta 32). Formulario por pasos. --}}
@php($dificultades = ['easy' => __('Fácil'), 'medium' => __('Media'), 'hard' => __('Difícil')])

<div class="flex flex-col gap-5">
    <x-ui.encabezado
        :titulo="__('Nueva pregunta')"
        :subtitulo="__('Banco :b. El formulario cambia según el tipo de pregunta.', ['b' => $banco->name])"
    />

    <x-ui.formulario accion="agregar" :volver="route('bancos-de-preguntas.show', ['banco' => $banco->id])" :enviar="__('Agregar pregunta')" :pasos="$this->pasos()" :paso="$paso">
        <x-ui.formulario.paso :numero="1">
            <x-ui.card :titulo="__('Enunciado')">
                <div class="flex flex-col gap-4">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.field :etiqueta="__('Tipo')" para="tipoP" error="tipo">
                            <x-ui.select id="tipoP" wire:model.live="tipo">@foreach ($tipos as $v => $e)<option value="{{ $v }}">{{ __($e) }}</option>@endforeach</x-ui.select>
                        </x-ui.field>
                        <x-ui.field :etiqueta="__('Tema')" para="temaP"><x-ui.input id="temaP" wire:model="tema" /></x-ui.field>
                        <x-ui.field :etiqueta="__('Dificultad')" para="dificultadP">
                            <x-ui.select id="dificultadP" wire:model="dificultad">@foreach ($dificultades as $v => $e)<option value="{{ $v }}">{{ $e }}</option>@endforeach</x-ui.select>
                        </x-ui.field>
                    </div>

                    <x-ui.field :etiqueta="__('Enunciado')" para="enunciado" error="enunciado" requerido>
                        <x-ui.textarea id="enunciado" wire:model="enunciado" rows="4" />
                    </x-ui.field>
                </div>
            </x-ui.card>
        </x-ui.formulario.paso>

        <x-ui.formulario.paso :numero="2">
            <x-ui.card :titulo="__('Respuesta')" :descripcion="__('Pregunta de tipo :t.', ['t' => __($tipos[$tipo])])">
                <div class="flex flex-col gap-4">
                    @if ($tipo === 'multiple_choice')
                        <fieldset class="flex flex-col gap-2">
                            <legend class="mb-1 text-sm font-medium">{{ __('Opciones (marque la correcta)') }}</legend>
                            @foreach ($opciones as $i => $o)
                                <label class="flex items-center gap-2" wire:key="opcion-{{ $i }}">
                                    <input type="radio" wire:model="correcta" value="{{ $i }}" class="text-marca-600">
                                    <x-ui.input wire:model="opciones.{{ $i }}" :placeholder="__('Opción :l', ['l' => chr(65 + $i)])" />
                                </label>
                            @endforeach
                        </fieldset>
                        @error('opciones')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                    @elseif ($tipo === 'true_false')
                        <x-ui.field :etiqueta="__('Respuesta correcta')" para="correctaTf">
                            <x-ui.select id="correctaTf" wire:model="correcta"><option value="0">{{ __('Verdadero') }}</option><option value="1">{{ __('Falso') }}</option></x-ui.select>
                        </x-ui.field>
                    @elseif ($tipo === 'short_answer')
                        <x-ui.field :etiqueta="__('Respuestas aceptadas')" para="aceptadas" :ayuda="__('Separadas por coma; no distingue mayúsculas.')">
                            <x-ui.input id="aceptadas" wire:model="aceptadas" />
                        </x-ui.field>
                    @else
                        <x-ui.alert tipo="info">{{ __('Los ensayos los califica usted en la bandeja de pendientes, así que no llevan respuesta correcta.') }}</x-ui.alert>
                    @endif

                    <x-ui.field :etiqueta="__('Justificación (retroalimentación diferida)')" para="justificacion">
                        <x-ui.textarea id="justificacion" wire:model="justificacion" rows="2" />
                    </x-ui.field>
                </div>
            </x-ui.card>
        </x-ui.formulario.paso>

        <x-ui.formulario.paso :numero="3">
            <x-ui.card :titulo="__('Revisar y agregar')" :descripcion="__('Si algo no está bien, vuelva al paso correspondiente desde el indicador de arriba.')">
                <dl class="flex flex-col gap-4 text-sm">
                    <div class="flex flex-wrap gap-1">
                        <x-ui.badge color="marca">{{ __($tipos[$tipo]) }}</x-ui.badge>
                        @if ($tema)<x-ui.badge>{{ $tema }}</x-ui.badge>@endif
                        <x-ui.badge>{{ $dificultades[$dificultad] }}</x-ui.badge>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Enunciado') }}</dt>
                        <dd class="mt-1 whitespace-pre-line text-texto">{{ $enunciado }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Respuesta correcta') }}</dt>
                        <dd class="mt-1 text-texto">
                            @switch($tipo)
                                @case('multiple_choice') {{ $opciones[$correcta] ?? '—' }} @break
                                @case('true_false') {{ (int) $correcta === 0 ? __('Verdadero') : __('Falso') }} @break
                                @case('short_answer') {{ $aceptadas ?: '—' }} @break
                                @default {{ __('La califica el docente.') }}
                            @endswitch
                        </dd>
                    </div>

                    @if ($justificacion)
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Justificación') }}</dt>
                            <dd class="mt-1 whitespace-pre-line text-texto">{{ $justificacion }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>
        </x-ui.formulario.paso>
    </x-ui.formulario>
</div>
