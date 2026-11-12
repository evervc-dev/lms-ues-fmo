<x-layouts::guest title="{{ __('Demasiados intentos') }}">
    <x-ui.empty-state icono="clock" titulo="{{ __('Error 429') }}" class="py-4">
        {{ __('Espere un momento y vuelva a intentarlo.') }}
        <x-slot:acciones>
            <x-ui.button :href="url('/')">{{ __('Volver a Inicio') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.empty-state>
</x-layouts::guest>
