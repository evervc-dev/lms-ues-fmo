<x-layouts::guest title="{{ __('No tiene permiso para ver esta página') }}">
    <x-ui.empty-state icono="lock-closed" titulo="{{ __('Error 403') }}" class="py-4">
        {{ __('Si cree que es un error, comuníquese con la coordinación de su carrera.') }}
        <x-slot:acciones>
            <x-ui.button :href="url('/')">{{ __('Volver a Inicio') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.empty-state>
</x-layouts::guest>
