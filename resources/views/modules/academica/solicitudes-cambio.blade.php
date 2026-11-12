{{-- Solicitudes de cambio de grupo (RF09, maqueta 37). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Matrícula')" :subtitulo="__('Al aprobar, se valida el cupo y el horario del grupo destino.')">
        <x-slot:acciones>
            <x-ui.select wire:model.live="estado" :aria-label="__('Estado')"><option value="pending">{{ __('Pendientes') }}</option><option value="resueltas">{{ __('Resueltas') }}</option></x-ui.select>
        </x-slot:acciones>
    </x-ui.encabezado>
    <x-ui.tabs :pestanas="$pestanas" />

    <x-ui.card ajustar>
        <x-ui.table ajustar :vacio="$estado === 'pending' ? __('No hay solicitudes pendientes.') : __('No hay solicitudes resueltas.')">
            <x-slot:cabecera><th>{{ __('Estudiante') }}</th><th>{{ __('Asignatura') }}</th><th>{{ __('Cambio') }}</th><th>{{ __('Justificación') }}</th><th>{{ __('Fecha') }}</th><th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
            @foreach ($solicitudes as $s)
                <tr wire:key="cambio-{{ $s->id }}">
                    <td>{{ $s->first_name }} {{ $s->last_name }}<p class="text-xs text-texto-suave">{{ $s->student_code }}</p></td>
                    <td><span class="font-semibold">{{ $s->code }}</span> · {{ $s->name }}</td>
                    <td>{{ __('Grupo :o → :d', ['o' => $s->grupo_origen, 'd' => $s->grupo_destino]) }}</td>
                    <td class="max-w-xs">{{ $s->justification }}</td>
                    <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($s->created_at)->format('d/m/Y') }}</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($s->status === 'pending')
                            <x-ui.button tamano="sm" variante="peligro" wire:click="confirmarRechazo({{ $s->id }})">{{ __('Rechazar') }}</x-ui.button>
                            <x-ui.button tamano="sm" variante="fantasma" wire:click="aprobar({{ $s->id }})" wire:confirm="{{ __('¿Aprobar el cambio de grupo? El estudiante queda matriculado en la sección nueva y se le avisa.') }}">{{ __('Aprobar') }}</x-ui.button>
                        @else
                            <x-ui.badge :color="$s->status === 'approved' ? 'verde' : 'rojo'">{{ $s->status === 'approved' ? __('Aprobada') : __('Rechazada') }}</x-ui.badge>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.modal nombre="rechazar-cambio" :titulo="__('Rechazar cambio de grupo')">
        <form wire:submit="rechazar" id="form-rechazo" class="flex flex-col gap-4">
            <x-ui.field :etiqueta="__('Motivo')" para="motivoCambio" error="motivo" requerido><x-ui.textarea id="motivoCambio" wire:model="motivo" /></x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'rechazar-cambio')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-rechazo" variante="peligro-solido">{{ __('Rechazar') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
