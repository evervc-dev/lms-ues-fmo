{{--
    Tarjeta con título, descripción y acciones opcionales.
    <x-ui.card titulo="…" descripcion="…"><x-slot:acciones>…</x-slot:acciones>contenido</x-ui.card>

    Con `ajustar`, y desde 1024 px, la tarjeta ocupa el alto que queda en la pantalla y ordena su contenido en
    columna: la cabecera y los filtros quedan arriba, la tabla con `ajustar` se estira y la paginación queda
    abajo, sin moverse. Pensada para los listados (docs/13 Layout/Layout base.md). No baja de 36 rem: si la
    ventana es baja, antes de encogerse hasta dejar dos filas a la vista, el contenido crece y se desplaza
    la página (revisión de diseño del 25/10/2026).
--}}
@props(['titulo' => null, 'descripcion' => null, 'ajustar' => false])

<section {{ $attributes->class([
    'rounded-xl bg-white shadow-sm ring-1 ring-zinc-200',
    'lg:flex lg:min-h-144 lg:flex-1 lg:flex-col' => $ajustar,
]) }}>
    @if ($titulo || isset($acciones))
        <header @class(['flex flex-wrap items-start justify-between gap-3 border-b border-zinc-100 px-4 py-3 sm:px-5', 'lg:shrink-0' => $ajustar])>
            <div class="min-w-0">
                @if ($titulo)
                    <h2 class="text-base font-semibold text-zinc-900">{{ $titulo }}</h2>
                @endif
                @if ($descripcion)
                    <p class="mt-0.5 text-sm text-zinc-600">{{ $descripcion }}</p>
                @endif
            </div>
            @isset($acciones)
                <div class="flex shrink-0 items-center gap-2">{{ $acciones }}</div>
            @endisset
        </header>
    @endif
    <div @class(['px-4 py-4 sm:px-5', 'lg:flex lg:min-h-0 lg:flex-1 lg:flex-col' => $ajustar])>{{ $slot }}</div>
</section>
