{{-- Inicio de sesión (RF01, ADR-007): solo con Google @ues.edu.sv. --}}
<x-layouts::guest title="Iniciar sesión">
    <div class="flex flex-col gap-5">
        <p class="text-sm text-zinc-600">{{ __('Use su cuenta institucional @ues.edu.sv.') }}</p>

        @error('google')
            <x-ui.alert tipo="error">{{ $message }}</x-ui.alert>
        @enderror

        @if (app(\App\Modules\Identidad\Services\GoogleAuthService::class)->configurado())
            <x-ui.button :href="route('auth.google')" :navegar="false" variante="secundario" class="w-full py-2.5">{{ __('Iniciar sesión con Google') }}</x-ui.button>
        @else
            <x-ui.button variante="secundario" class="w-full py-2.5" disabled>{{ __('Iniciar sesión con Google') }}</x-ui.button>
            <p class="text-xs text-zinc-500">{{ __('El inicio de sesión con Google no está configurado; contacte al administrador.') }}</p>
        @endif

        @if (app(\App\Modules\Identidad\Services\AccesoDesarrolloService::class)->habilitado())
            <x-ui.alert tipo="aviso" titulo="{{ __('Entorno de desarrollo') }}">
                <a href="{{ route('dev.entrar-como') }}" class="font-medium underline">{{ __('Entrar como…') }}</a>
                {{ __('un usuario sembrado, sin Google.') }}
            </x-ui.alert>
        @endif
    </div>
</x-layouts::guest>
