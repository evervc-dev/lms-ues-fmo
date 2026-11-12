{{-- Mensajear a estudiantes que… (RF31). Formulario por pasos: condición · destinatarios · mensaje y revisión. --}}
@php($actividad = $actividades->firstWhere('id', $item))

<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    <x-ui.formulario accion="enviar" :volver="route('secciones.notas', ['seccion' => $seccion])" :enviar="__('Enviar mensaje')" :pasos="$this->pasos()" :paso="$paso">
        <x-ui.formulario.paso :numero="1">
            <x-ui.card :titulo="__('Mensajear a estudiantes que…')" :descripcion="__('Es correo, no mensajería interna: cada estudiante recibe el suyo y un aviso en la plataforma.')">
                <div class="flex flex-col gap-4">
                    <x-ui.field :etiqueta="__('Actividad')" para="item" error="item" requerido>
                        <x-ui.select id="item" wire:model.live="item">
                            <option value="">{{ __('Elija…') }}</option>
                            @foreach ($actividades as $a)
                                <option value="{{ $a->id }}">{{ $a->title }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field :etiqueta="__('Condición')" para="condicion" error="condicion" requerido>
                            <x-ui.select id="condicion" wire:model.live="condicion">
                                @foreach ($condiciones as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ __($etiqueta) }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>

                        @if ($condicion === 'score_below')
                            <x-ui.field :etiqueta="__('Nota')" para="umbral" error="umbral" :ayuda="__('Solo cuenta la nota ya publicada.')" requerido>
                                <x-ui.input id="umbral" type="number" step="0.1" min="0" max="10" wire:model.live.debounce.400ms="umbral" />
                            </x-ui.field>
                        @endif
                    </div>

                    @if ($item !== null)
                        <p class="text-sm text-texto-suave">
                            {{ trans_choice('{0} Con esta condición no hay estudiantes.|{1} Con esta condición hay 1 estudiante.|[2,*] Con esta condición hay :count estudiantes.', $this->candidatos->count()) }}
                        </p>
                    @endif
                </div>
            </x-ui.card>
        </x-ui.formulario.paso>

        <x-ui.formulario.paso :numero="2">
            <x-ui.card :titulo="__('Destinatarios')" :descripcion="trans_choice('{0} No queda nadie.|{1} Lo recibirá 1 estudiante.|[2,*] Lo recibirán :count estudiantes.', $this->destinatarios->count())">
                @error('excluidos')<x-ui.alert class="mb-3" tipo="error">{{ $message }}</x-ui.alert>@enderror

                @if ($this->candidatos->isEmpty())
                    <x-ui.empty-state icono="users" :titulo="__('Nadie cumple la condición')">{{ __('Vuelva al paso anterior y cambie la actividad o la condición.') }}</x-ui.empty-state>
                @else
                    <ul class="divide-y divide-borde">
                        @foreach ($this->candidatos as $d)
                            <li wire:key="cand-{{ $d->id }}">
                                <label class="flex cursor-pointer items-center gap-3 py-2 text-sm">
                                    <input
                                        type="checkbox"
                                        @checked(! in_array($d->id, array_map('intval', $excluidos), true))
                                        wire:click="alternar({{ $d->id }})"
                                        class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600"
                                        aria-label="{{ __('Enviar a :n', ['n' => $d->nombre]) }}"
                                    >
                                    <span class="min-w-0 flex-1">
                                        <span class="font-medium text-texto">{{ $d->nombre }}</span>
                                        <span class="block truncate text-xs text-texto-suave">{{ $d->correo }}</span>
                                    </span>
                                    @if ($d->nota !== null)
                                        <span class="text-sm font-semibold text-texto">{{ number_format($d->nota, 1) }}</span>
                                    @endif
                                </label>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </x-ui.formulario.paso>

        <x-ui.formulario.paso :numero="3">
            <x-ui.card :titulo="__('Mensaje')" :descripcion="__('Cada estudiante recibe su propio correo; nadie ve a los demás. Puede responderle a usted.')">
                <div class="flex flex-col gap-4">
                    <x-ui.field :etiqueta="__('Asunto')" para="asunto" error="asunto" requerido>
                        <x-ui.input id="asunto" wire:model="asunto" />
                    </x-ui.field>
                    <x-ui.field :etiqueta="__('Mensaje')" para="texto" error="texto" requerido>
                        <x-ui.textarea id="texto" wire:model="texto" rows="8" />
                    </x-ui.field>
                </div>
            </x-ui.card>

            <x-ui.card :titulo="__('Revisar y enviar')" :descripcion="__('Si algo no está bien, vuelva al paso correspondiente desde el indicador de arriba.')">
                @error('destinatarios')<x-ui.alert class="mb-3" tipo="error">{{ $message }}</x-ui.alert>@enderror

                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Actividad') }}</dt>
                        <dd class="mt-1 text-texto">{{ $actividad?->title ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Condición') }}</dt>
                        <dd class="mt-1 text-texto">{{ __($condiciones[$condicion] ?? '') }}@if ($condicion === 'score_below') {{ $umbral }}@endif</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Destinatarios') }} ({{ $this->destinatarios->count() }})</dt>
                        <dd class="mt-1 text-texto">{{ $this->destinatarios->pluck('nombre')->join(', ', ' y ') ?: '—' }}</dd>
                    </div>
                </dl>

                <x-ui.alert class="mt-4" tipo="aviso">{{ __('El correo no se puede silenciar: les llegará aunque hayan apagado los avisos por correo.') }}</x-ui.alert>
            </x-ui.card>
        </x-ui.formulario.paso>
    </x-ui.formulario>
</div>
