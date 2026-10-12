{{--
    Botón o enlace con aspecto de botón (docs/13 Layout/Guía de estilo para Stitch.md, §7).
    <x-ui.button variante="primario|secundario|peligro|peligro-solido|fantasma" tamano="sm|md" icono="plus" href="…">Texto</x-ui.button>
    Los enlaces internos navegan con wire:navigate (ADR-035); :navegar="false" lo desactiva.
--}}
@props(['variante' => 'primario', 'tamano' => 'md', 'icono' => null, 'href' => null, 'type' => 'button', 'navegar' => true])

@php
    $clases = [
        'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marca-600 disabled:cursor-not-allowed disabled:opacity-50 data-loading:opacity-60',
        match ($tamano) {
            'sm' => 'px-3 py-1.5 text-sm',
            default => 'px-4 py-2 text-sm',
        },
        match ($variante) {
            'secundario' => 'bg-white text-texto ring-1 ring-inset ring-borde hover:bg-zinc-50',
            'peligro' => 'bg-white text-red-700 ring-1 ring-inset ring-red-700 hover:bg-red-50',
            'peligro-solido' => 'bg-red-700 text-white hover:bg-red-800',
            'fantasma' => 'text-texto hover:bg-zinc-100',
            default => 'bg-marca-600 text-white hover:bg-marca-700',
        },
    ];
    $interno = $href && (str_starts_with($href, '/') || str_starts_with($href, url('/')));
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($navegar && $interno) wire:navigate @endif {{ $attributes->class($clases) }}>
        @if ($icono)
            <x-ui.icon :name="$icono" class="size-4" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($clases) }}>
        @if ($icono)
            <x-ui.icon :name="$icono" class="size-4" />
        @endif
        {{ $slot }}
    </button>
@endif
