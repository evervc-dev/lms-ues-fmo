{{-- Bancos de preguntas (RF16). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Bancos de preguntas')" :subtitulo="__('Las preguntas se sortean desde aquí al crear cuestionarios.')">
        <x-slot:acciones><x-ui.button icono="plus" x-on:click="$dispatch('abrir-modal', 'nuevo-banco')">{{ __('Nuevo banco') }}</x-ui.button></x-slot:acciones>
    </x-ui.encabezado>
    <x-ui.table ajustar :vacio="__('Aún no tiene bancos.')">
        <x-slot:cabecera><th>{{ __('Banco') }}</th><th>{{ __('Asignatura') }}</th><th>{{ __('Dueño') }}</th><th class="text-right">{{ __('Preguntas') }}</th></x-slot:cabecera>
        @foreach ($bancos as $b)
            <tr wire:key="banco-{{ $b->id }}">
                <td><a href="{{ route('bancos-de-preguntas.show', $b->id) }}" wire:navigate class="font-medium hover:text-marca-700 hover:underline">{{ $b->name }}</a>@if ((int) $b->owner_id !== auth()->id()) <x-ui.badge>{{ __('Compartido') }}</x-ui.badge>@endif</td>
                <td>{{ $b->code ?? '—' }}</td><td>{{ $b->dueno }}</td><td class="text-right">{{ $b->preguntas }}</td>
            </tr>
        @endforeach
    </x-ui.table>

    <x-ui.modal nombre="nuevo-banco" :titulo="__('Nuevo banco')">
        <form wire:submit="crear" id="form-banco" class="flex flex-col gap-4">
            <x-ui.field :etiqueta="__('Nombre')" para="nombreBanco" error="nombre" requerido><x-ui.input id="nombreBanco" wire:model="nombre" /></x-ui.field>
            <x-ui.field :etiqueta="__('Asignatura')" para="asignaturaBanco"><x-ui.select id="asignaturaBanco" wire:model="asignatura"><option value="">{{ __('Ninguna') }}</option>@foreach ($asignaturas as $a)<option value="{{ $a->id }}">{{ $a->code }} · {{ $a->name }}</option>@endforeach</x-ui.select></x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'nuevo-banco')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-banco">{{ __('Crear banco') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
