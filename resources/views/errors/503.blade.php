<x-layouts::guest title="{{ __('En mantenimiento') }}">
    <x-ui.empty-state icono="exclamation-triangle" titulo="{{ __('Error 503') }}" class="py-4">
        {{ __('Volvemos en unos minutos.') }}
        <x-slot:acciones>
            <x-ui.button :href="url('/')">{{ __('Volver a Inicio') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.empty-state>
</x-layouts::guest>
