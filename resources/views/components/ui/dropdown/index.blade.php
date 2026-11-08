{{--
    Menú desplegable accesible.
    <x-ui.dropdown alinear="derecha|izquierda" ancho="w-56"><x-slot:disparador>…</x-slot:disparador> <x-ui.dropdown.item …/> </x-ui.dropdown>
--}}
@props(['alinear' => 'derecha', 'ancho' => 'w-56'])

<div x-data="{ abierto: false }" class="relative" x-on:click.outside="abierto = false" x-on:keydown.escape="abierto = false" {{ $attributes }}>
    <button type="button" x-on:click="abierto = ! abierto" :aria-expanded="abierto.toString()" aria-haspopup="menu">
        {{ $disparador }}
    </button>
    <div
        x-show="abierto"
        x-transition
        x-cloak
        role="menu"
        @class(['absolute z-40 mt-2 rounded-xl bg-white p-1.5 shadow-lg ring-1 ring-borde', $ancho, $alinear === 'izquierda' ? 'left-0' : 'right-0'])
    >
        {{ $slot }}
    </div>
</div>
