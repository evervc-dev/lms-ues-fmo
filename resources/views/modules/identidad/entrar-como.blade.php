{{-- «Entrar como…» (solo local y staging, LMS_DEV_LOGIN=true). --}}
<div class="flex flex-col gap-6">
    <x-ui.alert tipo="aviso">{{ __('Solo para desarrollo y staging. En producción esta pantalla no existe.') }}</x-ui.alert>

    <section class="flex flex-col gap-3">
        <h2 class="text-sm font-semibold text-zinc-800">{{ __('Cuentas de desarrollo') }}</h2>
        @forelse ($cuentas as $cuenta)
            <div wire:key="cuenta-{{ $cuenta->id }}" class="flex items-center justify-between gap-3 rounded-lg px-3 py-2 ring-1 ring-zinc-200">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-zinc-900">{{ $cuenta->institutional_email }}</p>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @foreach ($cuenta->roles as $rol)
                            <x-ui.badge color="marca">{{ __('roles.'.$rol->name) }}</x-ui.badge>
                        @endforeach
                    </div>
                </div>
                <x-ui.button tamano="sm" variante="fantasma" wire:click="entrar({{ $cuenta->id }})" wire:confirm="{{ __('¿Entrar como :n? Todo lo que haga quedará registrado a su nombre en la auditoría.', ['n' => $cuenta->fullName()]) }}">{{ __('Entrar') }}</x-ui.button>
            </div>
        @empty
            <x-ui.empty-state icono="users" titulo="{{ __('No hay cuentas de desarrollo') }}">{{ __('Corra composer fresh para sembrar los datos de prueba.') }}</x-ui.empty-state>
        @endforelse
    </section>

    <section class="flex flex-col gap-3">
        <x-ui.field etiqueta="{{ __('Otro usuario') }}" para="buscar" ayuda="{{ __('Busque por correo o nombre (mínimo 2 letras).') }}">
            <x-ui.input id="buscar" type="search" wire:model.live.debounce.300ms="buscar" placeholder="ej. gs21004" />
        </x-ui.field>
        @foreach ($resultados as $resultado)
            <div wire:key="resultado-{{ $resultado->id }}" class="flex items-center justify-between gap-3 rounded-lg px-3 py-2 ring-1 ring-zinc-200">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-zinc-900">{{ $resultado->fullName() }}</p>
                    <p class="truncate text-xs text-zinc-500">{{ $resultado->institutional_email }} · {{ $resultado->roles->map(fn ($rol) => __('roles.'.$rol->name))->join(', ') }}</p>
                </div>
                <x-ui.button tamano="sm" variante="fantasma" wire:click="entrar({{ $resultado->id }})" wire:confirm="{{ __('¿Entrar como :n? Todo lo que haga quedará registrado a su nombre en la auditoría.', ['n' => $resultado->fullName()]) }}">{{ __('Entrar') }}</x-ui.button>
            </div>
        @endforeach
    </section>
</div>
