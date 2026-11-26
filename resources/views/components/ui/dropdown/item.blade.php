{{-- Opción de x-ui.dropdown: enlace (href) o botón (type="submit"). Con `peligro`, en rojo (retirar, eliminar). --}}
@props(['href' => null, 'icono' => null, 'type' => 'button', 'peligro' => false])

@php
    $clases = 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm '.($peligro ? 'text-red-700 hover:bg-red-50' : 'text-texto hover:bg-zinc-100');
@endphp

@if ($href)
    <a role="menuitem" href="{{ $href }}" wire:navigate {{ $attributes->class($clases) }}>
        @if ($icono)<x-ui.icon :name="$icono" class="size-4 text-texto-suave" />@endif
        {{ $slot }}
    </a>
@else
    <button role="menuitem" type="{{ $type }}" {{ $attributes->class($clases) }}>
        @if ($icono)<x-ui.icon :name="$icono" @class(['size-4', 'text-red-600' => $peligro, 'text-texto-suave' => ! $peligro]) />@endif
        {{ $slot }}
    </button>
@endif
