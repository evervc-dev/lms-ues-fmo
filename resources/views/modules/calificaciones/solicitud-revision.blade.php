{{-- Solicitud de revisión del estudiante (RF35). --}}
@php
    $fecha = fn ($f) => \Illuminate\Support\Carbon::parse($f)->timezone('America/El_Salvador')->format('d/m/Y H:i');
    $num = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp
<div>
    @if ($visible)
        <x-ui.card :titulo="__('Revisión de la nota')">
            <div class="flex flex-col gap-3 text-sm">
                @if ($solicitud)
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <x-ui.badge :color="match ($solicitud->status) { 'pending' => 'ambar', 'resolved_changed' => 'verde', default => 'gris' }">{{ __($estados[$solicitud->status]) }}</x-ui.badge>
                        <span class="text-xs text-texto-suave">{{ __('Solicitada el :f', ['f' => $fecha($solicitud->requested_at)]) }}</span>
                    </div>
                    <p class="whitespace-pre-line text-texto-suave">{{ $solicitud->reason }}</p>
                    <ul class="divide-y divide-borde rounded-lg ring-1 ring-borde">
                        @foreach ($items as $item)
                            <li class="flex flex-col gap-1 p-3" wire:key="item-{{ $item->id }}">
                                <span class="font-medium">{{ $item->etiqueta }}</span>
                                <span class="text-texto-suave">{{ $item->student_comment }}</span>
                                @if ($item->decision)
                                    <span class="mt-1 rounded-md bg-zinc-50 p-2">
                                        <x-ui.badge :color="$item->decision === 'changed' ? 'verde' : 'gris'">{{ $item->decision === 'changed' ? __('Ajustado: :a → :b', ['a' => $num($item->points_before), 'b' => $num($item->points_after)]) : __('Se mantiene') }}</x-ui.badge>
                                        <span class="ml-1">{{ $item->teacher_response }}</span>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($solicitud->status !== 'pending')
                        <p>{{ __('Nota: :a → :b', ['a' => $num($solicitud->score_before), 'b' => $num($solicitud->score_after)]) }}@if ($solicitud->response) · {{ $solicitud->response }}@endif</p>
                    @endif
                @elseif ($noPuede)
                    <p class="text-texto-suave">{{ $noPuede }}</p>
                @else
                    <p class="text-texto-suave">{{ __('Si no está de acuerdo con su nota, puede pedir que el docente revise preguntas o criterios concretos.') }}
                        @if ($vence) {{ __('Plazo: hasta el :f.', ['f' => $vence->timezone('America/El_Salvador')->format('d/m/Y H:i')]) }}@endif</p>
                    <div><x-ui.button tamano="sm" icono="clipboard-document-check" wire:click="abrir">{{ __('Solicitar revisión') }}</x-ui.button></div>
                @endif
            </div>
        </x-ui.card>

        @unless ($noPuede)
            <x-ui.modal nombre="solicitar-revision" :titulo="__('Solicitar revisión')" ancho="lg">
                <form wire:submit="enviar" id="form-solicitar-revision" class="flex flex-col gap-4">
                    @error('solicitud')<x-ui.alert tipo="error">{{ $message }}</x-ui.alert>@enderror
                    <fieldset class="flex flex-col gap-2">
                        <legend class="text-sm font-medium text-zinc-800">{{ __('¿Qué quiere que se revise?') }}</legend>
                        @foreach ($candidatos as $c)
                            <div class="rounded-lg p-3 ring-1 ring-borde" wire:key="cand-{{ $c->clave }}">
                                <label class="flex items-start gap-2 text-sm">
                                    <input type="checkbox" id="cand-{{ $c->clave }}" value="{{ $c->clave }}" wire:model.live="marcados" class="mt-0.5 size-4 rounded border-borde text-marca-600 focus:ring-marca-600">
                                    <span class="flex-1">{{ $c->etiqueta }}</span>
                                    @if ($c->mostrarPuntos)
                                        <span class="text-xs text-texto-suave">{{ $c->clave === 'general' ? $num($c->puntos) : __(':p pts', ['p' => $num($c->puntos)]) }}</span>
                                    @endif
                                </label>
                                @if (in_array($c->clave, $marcados, true))
                                    <x-ui.field class="mt-2" :etiqueta="__('¿Por qué?')" :para="'coment-'.$c->clave" :error="'items.'.$c->clave" requerido>
                                        <x-ui.textarea :id="'coment-'.$c->clave" wire:model="comentarios.{{ $c->clave }}" rows="2" />
                                    </x-ui.field>
                                @endif
                            </div>
                        @endforeach
                        @error('marcados')<p class="text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                        @error('items')<p class="text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                    </fieldset>
                    <x-ui.field :etiqueta="__('Motivo general')" para="motivo" error="motivo" :ayuda="__('Solo puede pedir una revisión por actividad y no se puede retirar.')" requerido>
                        <x-ui.textarea id="motivo" wire:model="motivo" rows="3" />
                    </x-ui.field>
                </form>
                <x-slot:pie>
                    <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'solicitar-revision')">{{ __('Cancelar') }}</x-ui.button>
                    <x-ui.button type="submit" form="form-solicitar-revision">{{ __('Enviar solicitud') }}</x-ui.button>
                </x-slot:pie>
            </x-ui.modal>
        @endunless
    @endif
</div>
