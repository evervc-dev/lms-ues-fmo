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
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field :etiqueta="__('Estudiante')" para="prorroga-matricula" error="matricula" requerido>
                    <x-ui.select id="prorroga-matricula" wire:model="matricula">
                        <option value="">{{ __('Elija…') }}</option>
                        @foreach ($estudiantes as $est)<option value="{{ $est->id }}">{{ $est->last_name }}, {{ $est->first_name }}</option>@endforeach
                    </x-ui.select>
                </x-ui.field>
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
            <p class="text-xs text-texto-suave">{{ __('Una prórroga por estudiante y tipo: si ya tiene una, se reemplaza. La de entrega cuenta a tiempo, sin penalización.') }}</p>

            <x-ui.table :vacio="__('Sin prórrogas.')">
                <x-slot:cabecera><th>{{ __('Estudiante') }}</th><th>{{ __('Tipo') }}</th><th>{{ __('Hasta') }}</th><th>{{ __('Motivo') }}</th></x-slot:cabecera>
                @foreach ($prorrogas as $p)
                    <tr wire:key="prorroga-{{ $p->id }}"><td>{{ $p->first_name }} {{ $p->last_name }}</td><td>{{ __(\App\Modules\Evaluaciones\Services\ProrrogaService::TIPOS[$p->kind]) }}</td><td>{{ $fecha($p->until) }}</td><td>{{ $p->reason }}</td></tr>
                @endforeach
            </x-ui.table>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'prorrogas-{{ $datosActividad->ulid }}')">{{ __('Cerrar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-prorroga-{{ $datosActividad->ulid }}">{{ __('Guardar prórroga') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
