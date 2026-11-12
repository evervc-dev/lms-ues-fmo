{{-- Mi perfil · Google Calendar (RF21, ADR-018, maqueta 52). --}}
<div class="flex flex-col gap-5">
    @include('modules.identidad.partials.perfil-encabezado')

    <p class="text-sm text-texto-suave">{{ __('El LMS crea en su calendario los eventos de sus clases virtuales, con enlace de Meet, o del calendario de evaluaciones del período, e invita a los involucrados.') }}</p>

    @error('google')
        <x-ui.alert tipo="error">{{ $message }}</x-ui.alert>
    @enderror

    @if (! $habilitada)
        <x-ui.alert tipo="info" :titulo="__('Integración no disponible')">
            {{ __('La conexión con Google Calendar aún no está habilitada en la plataforma. Puede programar sus clases virtuales escribiendo el enlace de Meet.') }}
        </x-ui.alert>
    @else
        <x-ui.card :titulo="__('Su cuenta')" :descripcion="__('Solo se aceptan cuentas institucionales @:dominio.', ['dominio' => $dominio])">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <x-ui.badge :color="$conectado ? 'verde' : 'gris'">{{ $conectado ? __('Conectado') : __('Sin conectar') }}</x-ui.badge>
                    <span class="text-sm text-texto">{{ $usuario->institutional_email }}</span>
                </div>
                @if ($conectado)
                    <x-ui.button variante="peligro" wire:click="desconectar" wire:confirm="{{ __('¿Desconectar Google Calendar? Sus próximas clases virtuales no crearán eventos.') }}">{{ __('Desconectar') }}</x-ui.button>
                @else
                    <x-ui.button icono="calendar" :href="route('perfil.google-calendar.conectar')" :navegar="false">{{ __('Conectar con Google') }}</x-ui.button>
                @endif
            </div>
            <p class="mt-4 text-sm text-texto-suave">
                {{ $conectado
                    ? __('Los eventos nuevos se crearán en su calendario. En una clase virtual, si escribe su propio enlace de Meet, se conserva.')
                    : __('Google le pedirá permiso para crear y editar eventos de su calendario. Sin conexión, sus clases virtuales se programan igual, sin evento.') }}
            </p>
        </x-ui.card>
    @endif
</div>
