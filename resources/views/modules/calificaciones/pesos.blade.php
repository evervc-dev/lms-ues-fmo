{{-- Vista de sección › Notas › Pesos (RF17, CP-RF17-01). --}}
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    @if ($bloqueo)
        <x-ui.alert tipo="info">{{ $bloqueo }}</x-ui.alert>
    @elseif ($problemas)
        <x-ui.alert tipo="aviso" :titulo="__('Los pesos aún no cuadran: no podrá publicar el acta')">
            <ul class="list-disc pl-4">
                @foreach ($problemas as $problema)
                    <li>{{ $problema }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form wire:submit="guardar" class="flex flex-col gap-5">
        <x-ui.card :titulo="__('Pesos de los cómputos')" :descripcion="__('Los cómputos deben sumar 100 %, y las actividades de cada cómputo también. Puede guardar mientras no haya notas publicadas.')">
            <x-slot:acciones>
                <x-ui.button tamano="sm" variante="secundario" :href="route('secciones.notas', ['seccion' => $seccion])">{{ __('Volver a Notas') }}</x-ui.button>
                @unless ($bloqueo)
                    <x-ui.button tamano="sm" type="submit">{{ __('Guardar pesos') }}</x-ui.button>
                @endunless
            </x-slot:acciones>

            @error('computos')<x-ui.alert class="mb-4" tipo="error">{{ $message }}</x-ui.alert>@enderror
            @error('actividades')<x-ui.alert class="mb-4" tipo="error">{{ $message }}</x-ui.alert>@enderror

            <div class="flex flex-col gap-4">
                @forelse ($estructura as $c)
                    <div wire:key="computo-{{ $c->id }}" class="rounded-lg ring-1 ring-borde">
                        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-borde p-3">
                            <p class="font-semibold">{{ $c->name }}</p>
                            <x-ui.field class="w-36" :etiqueta="__('Peso del cómputo (%)')" para="computo-{{ $c->id }}" error="computos.{{ $c->id }}" requerido>
                                <x-ui.input id="computo-{{ $c->id }}" type="number" step="0.01" min="0.01" max="100" wire:model.live.blur="computos.{{ $c->id }}" :disabled="(bool) $bloqueo" />
                            </x-ui.field>
                        </div>
                        @if ($c->items->isEmpty())
                            <p class="p-3 text-sm text-texto-suave">{{ __('Este cómputo aún no tiene actividades.') }}</p>
                        @else
                            <ul class="divide-y divide-borde">
                                @foreach ($c->items as $i)
                                    <li wire:key="actividad-{{ $i->id }}" class="flex flex-wrap items-center justify-between gap-3 px-3 py-2 text-sm">
                                        <label for="actividad-{{ $i->id }}">{{ $i->title }}@if ($i->is_published) <span class="text-xs text-emerald-700">· {{ __('publicada') }}</span>@endif</label>
                                        <div class="w-36">
                                            <x-ui.input id="actividad-{{ $i->id }}" type="number" step="0.01" min="0.01" max="100" wire:model.live.blur="actividades.{{ $i->id }}" :disabled="(bool) $bloqueo" :aria-label="__('Peso de :t (%)', ['t' => $i->title])" />
                                            @error('actividades.'.$i->id)<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            @php($sumaActividades = $sumasActividades[$c->id])
                            <p @class(['px-3 py-2 text-right text-xs', 'text-texto-suave' => (float) $sumaActividades === 100.0, 'text-amber-700' => (float) $sumaActividades !== 100.0])>
                                {{ __('Actividades: :s % de 100 %', ['s' => $sumaActividades]) }}
                            </p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-texto-suave">{{ __('La sección no tiene cómputos.') }}</p>
                @endforelse

                <p @class(['text-right text-sm font-semibold', 'text-emerald-700' => (float) $sumaComputos === 100.0, 'text-red-700' => (float) $sumaComputos !== 100.0])>
                    {{ __('Total de los cómputos: :s % de 100 %', ['s' => $sumaComputos]) }}
                </p>
            </div>
        </x-ui.card>
    </form>
</div>
