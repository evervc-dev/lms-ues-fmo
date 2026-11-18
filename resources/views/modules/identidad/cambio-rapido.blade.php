{{-- «Entrar como…» desde el menú de usuario (solo local y staging, LMS_DEV_LOGIN=true). --}}
<div>
    <x-ui.modal :nombre="\App\Modules\Identidad\Livewire\CambioRapido::MODAL" :titulo="__('Entrar como…')">
        <div class="flex flex-col gap-4">
            <x-ui.alert tipo="aviso">{{ __('Solo para desarrollo y staging. Todo lo que haga quedará registrado a nombre de la cuenta con la que entre.') }}</x-ui.alert>

            @if ($previos)
                <x-ui.alert tipo="info">
                    {{ __('Los roles de :correo siguen reemplazados (tenía: :roles).', [
                        'correo' => $previos['correo'],
                        'roles' => $previos['roles'] === [] ? __('Sin rol') : collect($previos['roles'])->map(fn ($rol) => __('roles.'.$rol))->join(', '),
                    ]) }}
                    <button type="button" wire:click="restaurar" class="font-semibold underline">{{ __('Restaurar sus roles') }}</button>
                </x-ui.alert>
            @endif

            <x-ui.field :etiqueta="__('Cuenta')" para="cambio-rapido-buscar" :ayuda="__('Busque por correo o nombre (mínimo 2 letras).')">
                <x-ui.search id="cambio-rapido-buscar" wire:model.live.debounce.300ms="buscar" :placeholder="__('ej. rr19118')" />
            </x-ui.field>

            @forelse ($resultados as $resultado)
                <button type="button" wire:key="rapido-{{ $resultado->id }}" wire:click="seleccionar({{ $resultado->id }})"
                    @class([
                        'flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-left ring-1',
                        'bg-marca-50 ring-2 ring-marca-600' => $seleccionado === $resultado->id,
                        'ring-zinc-200 hover:bg-zinc-50' => $seleccionado !== $resultado->id,
                    ])>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-zinc-900">{{ $resultado->fullName() }}</span>
                        <span class="block truncate text-xs text-zinc-500">{{ $resultado->institutional_email }} · {{ $resultado->roles->map(fn ($rol) => __('roles.'.$rol->name))->join(', ') ?: __('Sin rol') }}</span>
                    </span>
                    <x-ui.icon name="chevron-right" class="size-4 shrink-0 text-texto-suave" />
                </button>
            @empty
                @if (mb_strlen(trim($buscar)) >= 2)
                    <p class="text-sm text-texto-suave">{{ __('Ninguna cuenta activa coincide con la búsqueda.') }}</p>
                @endif
            @endforelse

            @if ($elegida)
                <div class="border-t border-zinc-100 pt-4">
                    @include('modules.identidad.partials.cambio-de-rol', ['cuenta' => $elegida, 'roles' => $roles])
                </div>
            @endif
        </div>

        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', '{{ \App\Modules\Identidad\Livewire\CambioRapido::MODAL }}')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button wire:click="confirmar" :disabled="! $elegida">{{ __('Entrar') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
