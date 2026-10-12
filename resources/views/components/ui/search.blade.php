{{-- Buscador. <x-ui.search wire:model.live.debounce.300ms="buscar" placeholder="Buscar por código, materia o docente…" /> --}}
<label {{ $attributes->only('class')->class(['relative block']) }}>
    <span class="sr-only">{{ $attributes->get('placeholder', __('Buscar')) }}</span>
    <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-texto-suave" />
    <input type="search" {{ $attributes->except('class')->class(['block w-full rounded-lg border-0 bg-white py-2 pl-9 pr-3 text-sm text-texto ring-1 ring-inset ring-borde placeholder:text-texto-desactivado focus:ring-2 focus:ring-marca-600']) }} />
</label>
