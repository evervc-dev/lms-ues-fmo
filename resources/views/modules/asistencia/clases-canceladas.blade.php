{{-- Clases canceladas (RF32, maqueta 43). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Clases canceladas')" :subtitulo="__('Las clases canceladas no cuentan para la asistencia.')">
        <x-slot:acciones>
            <x-ui.select wire:model.live="estado" :aria-label="__('Estado')"><option value="requested">{{ __('Por aprobar') }}</option><option value="resueltas">{{ __('Resueltas') }}</option></x-ui.select>
            <x-ui.button icono="x-circle" x-on:click="$dispatch('abrir-modal', 'cancelar-directo')">{{ $alcanceFacultad ? __('Cancelar para la facultad') : __('Cancelar para la carrera') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.encabezado>
    <x-ui.card ajustar>
        <x-ui.table ajustar :vacio="__('No hay registros.')">
            <x-slot:cabecera><th>{{ __('Fecha') }}</th><th>{{ __('Alcance') }}</th><th>{{ __('Tipo') }}</th><th>{{ __('Motivo') }}</th><th>{{ __('Solicitó') }}</th><th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
            @foreach ($cancelaciones as $c)
                <tr wire:key="canc-{{ $c->id }}">
                    <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($c->date)->format('d/m/Y') }}</td>
                    <td>{{ ['section' => $c->code.' · '.$c->grupo, 'career' => __('Carrera :c', ['c' => $c->carrera]), 'faculty' => __('Toda la facultad')][$c->scope] }}</td>
                    <td>{{ $c->kind === 'cancellation' ? __('Cancelación') : __('Pasa a virtual') }}</td>
                    <td class="max-w-xs">{{ $c->reason }}</td>
                    <td>{{ $c->solicitante }}</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($c->status === 'requested')
                            <x-ui.button tamano="sm" variante="peligro" wire:click="resolver({{ $c->id }}, false)">{{ __('Rechazar') }}</x-ui.button>
                            <x-ui.button tamano="sm" wire:click="resolver({{ $c->id }}, true)">{{ __('Aprobar') }}</x-ui.button>
                        @else
                            <x-ui.badge :color="$c->status === 'approved' ? 'verde' : 'rojo'">{{ $c->status === 'approved' ? __('Aprobada') : __('Rechazada') }}</x-ui.badge>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.modal nombre="cancelar-directo" :titulo="$alcanceFacultad ? __('Cancelar clases de la facultad') : __('Cancelar clases de la carrera')">
        <form wire:submit="cancelar" id="form-canc" class="flex flex-col gap-4">
            <x-ui.field :etiqueta="__('Fecha')" para="fechaCanc" error="fecha" requerido><x-ui.input id="fechaCanc" type="date" wire:model="fecha" /></x-ui.field>
            <x-ui.field :etiqueta="__('Motivo')" para="motivoCanc" error="motivo" :ayuda="__('Ej.: asueto nacional.')" requerido><x-ui.textarea id="motivoCanc" wire:model="motivo" /></x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'cancelar-directo')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-canc" variante="peligro-solido">{{ __('Cancelar clases') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
