{{-- Notas › Mensajes enviados (RF31, ADR-034). --}}
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    <x-ui.card :titulo="__('Mensajes enviados')" :descripcion="__('Lo que ha mandado desde el libro de calificaciones y a quién.')">
        <x-slot:acciones>
            <x-ui.button tamano="sm" variante="secundario" :href="route('secciones.notas', ['seccion' => $seccion])">{{ __('Volver a Notas') }}</x-ui.button>
            <x-ui.button tamano="sm" icono="plus" :href="route('secciones.mensajes.crear', ['seccion' => $seccion])">{{ __('Mensajear a estudiantes que…') }}</x-ui.button>
        </x-slot:acciones>

        @if ($mensajes->isEmpty())
            <x-ui.empty-state icono="inbox-stack" :titulo="__('Aún no ha enviado mensajes')">
                {{ __('Desde el libro de calificaciones puede escribirle a quienes no entregaron una actividad o sacaron menos de cierta nota.') }}
            </x-ui.empty-state>
        @else
            <x-ui.accordion>
                @foreach ($mensajes as $m)
                    <x-ui.accordion.item :titulo="$m->asunto" wire:key="mensaje-{{ $m->id }}">
                        <div class="flex flex-col gap-3 text-sm">
                            <p class="text-xs text-texto-suave">
                                {{ \Illuminate\Support\Carbon::parse($m->cuando)->timezone('America/El_Salvador')->translatedFormat('j \d\e F, H:i') }}
                                · {{ $m->actividad ?? '—' }}
                                · {{ __($condiciones[$m->condicion['type']] ?? '') }}@if (($m->condicion['type'] ?? '') === 'score_below') {{ $m->condicion['threshold'] }}@endif
                            </p>

                            <p class="whitespace-pre-line rounded-md bg-zinc-50 p-3 text-texto">{{ $m->texto }}</p>

                            <div>
                                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-texto-suave">
                                    {{ __('Destinatarios') }} · {{ __(':e de :t entregados', ['e' => $m->entregados, 't' => $m->destinatarios->count()]) }}
                                </p>
                                <ul class="divide-y divide-borde">
                                    @foreach ($m->destinatarios as $d)
                                        <li class="flex items-center justify-between gap-3 py-1.5">
                                            <span>{{ $d->nombre }} <span class="text-xs text-texto-suave">{{ $d->correo }}</span></span>
                                            <x-ui.badge :color="$d->entregado ? 'verde' : 'gris'">{{ $d->entregado ? __('Entregado') : __('En cola') }}</x-ui.badge>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </x-ui.accordion.item>
                @endforeach
            </x-ui.accordion>
        @endif
    </x-ui.card>
</div>
