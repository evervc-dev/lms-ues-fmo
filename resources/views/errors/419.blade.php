<x-layouts::guest title="{{ __('Su sesión expiró') }}">
    <x-ui.empty-state icono="clock" titulo="{{ __('Error 419') }}" class="py-4">
        {{ __('Por seguridad, la sesión se cierra tras 30 minutos sin actividad. Vuelva a iniciar sesión.') }}
        <x-slot:acciones>
            <x-ui.button :href="route('login')">{{ __('Iniciar sesión') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.empty-state>
</x-layouts::guest>
