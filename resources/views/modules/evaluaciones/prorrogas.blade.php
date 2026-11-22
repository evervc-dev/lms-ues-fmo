{{-- Prórrogas de una actividad (RF15, RF35). --}}
@php
    $fecha = fn ($f) => \Illuminate\Support\Carbon::parse($f)->timezone('America/El_Salvador')->format('d/m/Y H:i');
@endphp
<div>
    <x-ui.button tamano="sm" variante="secundario" icono="clock" wire:click="abrir">
        {{ __('Prórrogas') }}@if ($prorrogas->isNotEmpty()) ({{ $prorrogas->count() }})@endif
    </x-ui.button>

    <x-ui.modal :nombre="'prorrogas-'.$datosActividad->ulid" :titulo="__('Prórrogas · :t', ['t' => $datosActividad->title])" ancho="lg">
        <form wire:submit="conceder" id="form-prorroga-{{ $datosActividad->ulid }}" class="flex flex-col gap-4 text-sm">
            {{-- Lista con buscador por nombre o carné (sin distinguir tildes) y casillas: un estudiante sin recorrer los 40,
                 varios o todos los que se ven. El select de una sola opción era tedioso con grupos grandes (QA 22/11/2026). --}}
            <div x-data="{
                    buscar: '',
                    normal(t) { return (t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); },
                    coincide(texto) { return this.normal(texto).includes(this.normal(this.buscar)); },
                    visibles() { return [...this.$refs.lista.querySelectorAll('label[data-texto]')].filter(l => this.coincide(l.dataset.texto)).map(l => Number(l.dataset.id)); },
                    todos() { const ids = this.visibles(); $wire.set('matriculas', [...new Set([...$wire.matriculas.map(Number), ...ids])]); },
                    ninguno() { $wire.set('matriculas', []); },
                }" class="flex flex-col gap-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium text-texto">{{ __('Estudiantes') }} <span class="text-red-600">*</span></span>
                    <span class="text-xs text-texto-suave" x-text="$wire.matriculas.length === 1 ? @js(__('1 elegido')) : $wire.matriculas.length + ' ' + @js(__('elegidos'))"></span>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <x-ui.search class="flex-1" x-model="buscar" :placeholder="__('Buscar por nombre o carné…')" />
                    <div class="flex gap-2">
                        <x-ui.button type="button" tamano="sm" variante="secundario" x-on:click="todos()">{{ __('Elegir los que se ven') }}</x-ui.button>
                        <x-ui.button type="button" tamano="sm" variante="fantasma" x-on:click="ninguno()">{{ __('Quitar todos') }}</x-ui.button>
                    </div>
                </div>
                <div x-ref="lista" class="max-h-56 overflow-y-auto rounded-lg ring-1 ring-borde">
                    @foreach ($estudiantes as $est)
                        <label wire:key="prorroga-est-{{ $est->id }}" data-id="{{ $est->id }}" data-texto="{{ $est->nombre }} {{ $est->carne }}"
                            x-show="coincide($el.dataset.texto)"
                            class="flex cursor-pointer items-center gap-3 border-b border-borde px-3 py-2 last:border-0 hover:bg-zinc-50">
                            <input type="checkbox" value="{{ $est->id }}" wire:model="matriculas" class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600">
                            <span class="min-w-0 flex-1 truncate text-texto">{{ $est->nombre }}</span>
                            @if ($est->carne)<span class="shrink-0 font-mono text-xs text-texto-suave">{{ $est->carne }}</span>@endif
                        </label>
                    @endforeach
                    <p x-show="visibles().length === 0" x-cloak class="px-3 py-4 text-center text-texto-suave">{{ __('Ningún estudiante coincide con la búsqueda.') }}</p>
                </div>
                @error('matriculas')<p class="text-xs font-medium text-red-700">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.field :etiqueta="__('Tipo')" para="prorroga-tipo" error="tipo" requerido>
                    <x-ui.select id="prorroga-tipo" wire:model="tipo">
                        @foreach ($tipos as $valor => $etiqueta)<option value="{{ $valor }}">{{ __($etiqueta) }}</option>@endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field :etiqueta="__('Hasta')" para="prorroga-hasta" error="hasta" requerido>
                    <x-ui.input id="prorroga-hasta" type="datetime-local" wire:model="hasta" />
                </x-ui.field>
                <x-ui.field :etiqueta="__('Motivo')" para="prorroga-motivo" error="motivo" requerido>
                    <x-ui.input id="prorroga-motivo" wire:model="motivo" :placeholder="__('Ej.: constancia médica')" />
                </x-ui.field>
            </div>
            <p class="text-xs text-texto-suave">{{ __('La misma prórroga para todos los elegidos. Una por estudiante y tipo: si ya tiene una, se reemplaza. La de entrega cuenta a tiempo, sin penalización.') }}</p>

            <x-ui.table :vacio="__('Sin prórrogas.')">
                <x-slot:cabecera><th>{{ __('Estudiante') }}</th><th>{{ __('Tipo') }}</th><th>{{ __('Hasta') }}</th><th>{{ __('Motivo') }}</th></x-slot:cabecera>
                @foreach ($prorrogas as $p)
                    <tr wire:key="prorroga-{{ $p->id }}"><td>{{ $p->nombre }}</td><td>{{ \App\Modules\Evaluaciones\Services\ProrrogaService::nombreTipo($p->kind, $datosActividad->assessment_type) }}</td><td>{{ $fecha($p->until) }}</td><td>{{ $p->reason }}</td></tr>
                @endforeach
            </x-ui.table>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'prorrogas-{{ $datosActividad->ulid }}')">{{ __('Cerrar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-prorroga-{{ $datosActividad->ulid }}">{{ __('Guardar prórroga') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
