{{--
    Menú desplegable accesible.
    <x-ui.dropdown alinear="derecha|izquierda" ancho="w-56"><x-slot:disparador>…</x-slot:disparador> <x-ui.dropdown.item …/> </x-ui.dropdown>
--}}
{{-- `fijoEnMovil`: en pantallas angostas el panel se ancla a la cabecera fija (su contenedor posicionado) y ocupa
     su ancho, en vez de colgar del botón y salirse por la izquierda (campana, revisión responsive del 21/11/2026). --}}
@props(['alinear' => 'derecha', 'ancho' => 'w-56', 'fijoEnMovil' => false])

<div x-data="{ abierto: false }" @class(['relative', 'max-sm:static' => $fijoEnMovil]) x-on:click.outside="abierto = false" x-on:keydown.escape="abierto = false" {{ $attributes }}>
    <button type="button" x-on:click="abierto = ! abierto" :aria-expanded="abierto.toString()" aria-haspopup="menu">
        {{ $disparador }}
    </button>
    <div
        x-show="abierto"
        x-transition
        x-cloak
        role="menu"
        @class(['absolute z-40 mt-2 rounded-xl bg-white p-1.5 shadow-lg ring-1 ring-borde', $ancho, $alinear === 'izquierda' ? 'left-0' : 'right-0',
            'max-sm:inset-x-3 max-sm:top-full max-sm:w-auto max-sm:max-w-none' => $fijoEnMovil])
    >
        {{ $slot }}
    </div>
</div>
