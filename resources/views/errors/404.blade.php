<x-layouts::guest title="{{ __('No encontramos lo que busca') }}">
    <x-ui.empty-state icono="magnifying-glass" titulo="{{ __('Error 404') }}" class="py-4">
        {{ __('La dirección no existe o fue movida.') }}
        <x-slot:acciones>
            <x-ui.button :href="url('/')">{{ __('Volver a Inicio') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.empty-state>
</x-layouts::guest>
