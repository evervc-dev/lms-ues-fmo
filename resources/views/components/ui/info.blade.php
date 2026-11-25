{{--
    Ícono (i) con una nota que aparece al pasar el puntero, al enfocarlo con el teclado o al tocarlo en el teléfono.
    Para aclaraciones que no deben ocupar espacio fijo en la pantalla (QA 24/11/2026: la «Fórmula provisional» de Oferta).
    <x-ui.info :titulo="__('Fórmula provisional')">Texto de la nota…</x-ui.info>
--}}
@props(['titulo' => null, 'etiqueta' => null])

<span x-data="{ abierta: false }" class="relative inline-flex align-middle" @mouseenter="abierta = true" @mouseleave="abierta = false" @keydown.escape="abierta = false">
    <button type="button" class="rounded-full text-texto-suave transition hover:text-marca-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-marca-300"
        @click="abierta = ! abierta" @focus="abierta = true" @blur="abierta = false"
        :aria-expanded="abierta.toString()" aria-label="{{ $etiqueta ?? $titulo ?? __('Más información') }}">
        <x-ui.icon name="information-circle" class="size-5" />
    </button>
    <span x-show="abierta" x-cloak x-transition.opacity role="tooltip"
        {{ $attributes->class(['absolute left-0 top-full z-30 mt-2 w-[min(22rem,80vw)] rounded-lg bg-white p-3 text-left text-sm font-normal normal-case tracking-normal text-texto shadow-lg ring-1 ring-borde']) }}>
        @if ($titulo)
            <span class="mb-1 block font-semibold">{{ $titulo }}</span>
        @endif
        {{ $slot }}
    </span>
</span>
