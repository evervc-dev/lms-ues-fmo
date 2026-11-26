{{--
    Tarjeta con título, descripción y acciones opcionales.
    <x-ui.card titulo="…" descripcion="…"><x-slot:acciones>…</x-slot:acciones>contenido</x-ui.card>

    Con `ajustar`, y desde 1024 px, la tarjeta ordena su contenido en columna para los listados
    (docs/13 Layout/Layout base.md): la cabecera y los filtros arriba, la tabla con `ajustar` y la paginación abajo.
    Toma el alto de su contenido: antes se estiraba hasta el final de la pantalla y una tabla de una fila (o vacía)
    quedaba en un lienzo blanco (revisión UX del 22/11/2026). La tabla larga se desplaza por dentro.
--}}
@props(['titulo' => null, 'descripcion' => null, 'ajustar' => false])

<section {{ $attributes->class([
    'rounded-xl bg-white shadow-sm ring-1 ring-zinc-200',
    'lg:flex lg:flex-col' => $ajustar,
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
                {{-- Las acciones envuelven: con varios botones, en el teléfono empujaban la página a lo ancho. --}}
                <div class="flex max-w-full flex-wrap items-center gap-2">{{ $acciones }}</div>
            @endisset
        </header>
    @endif
    <div @class(['px-4 py-4 sm:px-5', 'lg:flex lg:min-h-0 lg:flex-1 lg:flex-col' => $ajustar])>{{ $slot }}</div>
</section>
