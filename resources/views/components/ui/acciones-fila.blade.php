{{--
    Menú «⋯» de las acciones de una fila (revisión UX del 22/11/2026): la acción frecuente queda visible al lado y las
    demás, sobre todo las destructivas, van aquí. Una columna de botones rojos competía con los datos y hacía fácil
    retirar o eliminar por error.
    <x-ui.acciones-fila :etiqueta="__('Acciones de :x', ['x' => $fila->code])">
        <x-ui.dropdown.item icono="x-circle" peligro x-on:click="abierto = false" wire:click="…" wire:confirm="…">Eliminar</x-ui.dropdown.item>
    </x-ui.acciones-fila>

    El panel flota con posición fija junto al botón: dentro de una tabla que se desplaza por dentro, un panel absoluto
    quedaba recortado en las últimas filas. Si no cabe abajo, se abre hacia arriba; al desplazar la página se cierra.
--}}
@props(['etiqueta' => null, 'ancho' => 'w-52'])

<div x-data="{
        abierto: false,
        estilo: '',
        alternar() {
            if (this.abierto) { this.abierto = false; return; }
            const r = this.$refs.boton.getBoundingClientRect();
            const derecha = Math.max(8, window.innerWidth - r.right);
            this.estilo = r.bottom + 220 > window.innerHeight
                ? `position:fixed;right:${derecha}px;bottom:${window.innerHeight - r.top + 6}px`
                : `position:fixed;right:${derecha}px;top:${r.bottom + 6}px`;
            this.abierto = true;
        },
    }"
    x-on:click.outside="abierto = false" x-on:keydown.escape="abierto = false"
    x-on:scroll.window.capture.passive="abierto = false" x-on:resize.window="abierto = false"
    {{ $attributes->class(['inline-block text-left']) }}>
    <button type="button" x-ref="boton" x-on:click="alternar()" :aria-expanded="abierto.toString()" aria-haspopup="menu"
        class="inline-flex size-8 items-center justify-center rounded-lg text-texto-suave hover:bg-zinc-100 hover:text-texto" title="{{ __('Más acciones') }}">
        <x-ui.icon name="ellipsis-horizontal" class="size-5" /><span class="sr-only">{{ $etiqueta ?? __('Más acciones') }}</span>
    </button>
    <div x-show="abierto" x-cloak x-transition.opacity :style="estilo" role="menu"
        @class(['z-50 rounded-xl bg-white p-1.5 text-left shadow-lg ring-1 ring-borde', $ancho])>
        {{ $slot }}
    </div>
</div>
