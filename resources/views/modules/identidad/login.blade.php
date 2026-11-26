{{-- Inicio de sesión (RF01, ADR-007): solo con Google @ues.edu.sv. Maqueta 01 y Guía de estilo (Layout Invitado). --}}
<x-layouts::guest title="Iniciar sesión">
    <div class="flex flex-col gap-5">
        <p class="text-sm leading-relaxed text-texto-suave">{{ __('Ingrese con su cuenta institucional de la Universidad de El Salvador para acceder a sus asignaturas, evaluaciones y asistencia.') }}</p>

        @error('google')
            <x-ui.alert tipo="error">{{ $message }}</x-ui.alert>
        @enderror

        @php($googleConfigurado = app(\App\Modules\Identidad\Services\GoogleAuthService::class)->configurado())
        @php($logoGoogle = '<svg class="size-5 shrink-0" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>')
        @if ($googleConfigurado)
            <a href="{{ route('auth.google') }}" class="flex min-h-11 w-full items-center justify-center gap-3 rounded-lg bg-white px-4 py-2.5 text-sm font-medium text-texto shadow-sm ring-1 ring-borde transition-colors hover:bg-zinc-50 hover:text-marca-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-marca-500 focus-visible:ring-offset-2">
                {!! $logoGoogle !!}
                {{ __('Iniciar sesión con Google') }}
            </a>
        @else
            <button type="button" disabled class="flex min-h-11 w-full cursor-not-allowed items-center justify-center gap-3 rounded-lg bg-zinc-50 px-4 py-2.5 text-sm font-medium text-texto-desactivado ring-1 ring-borde">
                {!! $logoGoogle !!}
                {{ __('Iniciar sesión con Google') }}
            </button>
            <p class="text-xs text-texto-suave">{{ __('El inicio de sesión con Google no está configurado; contacte al administrador.') }}</p>
        @endif

        <p class="flex items-center justify-center gap-1.5 text-center text-xs text-texto-suave">
            <x-ui.icon name="shield-check" class="size-4 shrink-0 text-marca-600" />
            <span>{{ __('Solo cuentas del dominio institucional') }} <span class="font-semibold text-texto">@ues.edu.sv</span></span>
        </p>

        {{-- ADR-038: una cuenta institucional que no existe se crea sin rol al primer ingreso. --}}
        <div class="flex gap-3 rounded-lg bg-zinc-50 p-3 text-xs leading-relaxed text-texto-suave ring-1 ring-borde">
            <x-ui.icon name="information-circle" class="size-5 shrink-0 text-marca-600" />
            <p>
                <span class="font-semibold text-texto">{{ __('¿Es su primer ingreso?') }}</span>
                {{ __('Su cuenta se crea al entrar con su correo institucional. Si todavía no ve sus materias, comuníquese con la coordinación de su carrera.') }}
            </p>
        </div>

        @if (app(\App\Modules\Identidad\Services\AccesoDesarrolloService::class)->habilitado())
            <x-ui.alert tipo="aviso" titulo="{{ __('Entorno de pruebas') }}">
                <p>
                    <a href="{{ route('dev.escenarios') }}" class="font-medium underline">{{ __('Escenarios de prueba') }}</a>
                    {{ __('— prepare el estado para probar un flujo y obtenga los enlaces y la checklist.') }}
                </p>
                <p class="mt-1">
                    <a href="{{ route('dev.entrar-como') }}" class="font-medium underline">{{ __('Entrar como…') }}</a>
                    {{ __('un usuario sembrado, sin Google.') }}
                </p>
            </x-ui.alert>
        @endif
    </div>
</x-layouts::guest>
