<x-layouts::guest title="{{ __('Tuvimos un problema') }}">
    <x-ui.empty-state icono="exclamation-triangle" titulo="{{ __('Error 500') }}" class="py-4">
        {{ __('Ya quedó registrado. Intente de nuevo en unos minutos.') }}
        <x-slot:acciones>
            <x-ui.button :href="url('/')">{{ __('Volver a Inicio') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.empty-state>
</x-layouts::guest>
