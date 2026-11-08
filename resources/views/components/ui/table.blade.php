{{--
    Tabla con desplazamiento horizontal en pantallas angostas.
    <x-ui.table vacio="No hay solicitudes pendientes.">
        <x-slot:cabecera><th>Estudiante</th><th>Motivo</th></x-slot:cabecera>
        <tr>…</tr>
    </x-ui.table>

    Con `ajustar`, y desde 1024 px, la tabla ocupa el alto que queda en la pantalla y se desplaza por dentro,
    con los nombres de columna fijos arriba; así el título y los indicadores siempre quedan a la vista
    (docs/13 Layout/Layout base.md). Nunca baja de 26 rem: antes, en una pantalla de portátil con la barra de
    marcadores abierta, quedaban dos filas a la vista (revisión de diseño del 25/10/2026). Requiere un
    contenedor en columna con alto definido: la tarjeta con `ajustar` o el div raíz de la pantalla con
    `lg:h-full`. En teléfono y tableta no cambia nada.

    Mientras el componente responde, la tabla se atenúa y muestra «Cargando…»: una tabla que tarda ya no
    se confunde con una tabla vacía.

    Acciones dentro de una fila: `<x-ui.button tamano="sm" variante="fantasma">` para las neutras y
    `variante="peligro"` para las destructivas. La acción principal de la pantalla vive en el encabezado.
--}}
@props(['vacio' => null, 'ajustar' => false, 'cargando' => true])

<div {{ $attributes->class([
    'relative rounded-xl bg-white ring-1 ring-zinc-200',
    'overflow-x-auto' => ! $ajustar,
    'overflow-auto lg:min-h-104 lg:flex-1' => $ajustar,
]) }}>
    @if ($cargando)
        <div wire:loading.delay.longer.flex class="pointer-events-none absolute inset-0 z-20 hidden items-start justify-center rounded-xl bg-white/70 pt-20" aria-hidden="true">
            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-sm font-medium text-texto-suave shadow-sm ring-1 ring-borde">
                <span class="size-3 animate-spin rounded-full border-2 border-zinc-300 border-t-marca-600"></span>
                {{ __('Cargando…') }}
            </span>
        </div>
    @endif
    <table class="min-w-full divide-y divide-zinc-200 text-sm">
        @isset($cabecera)
            <thead @class([
                'bg-zinc-50 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 [&_th]:px-4 [&_th]:py-3',
                // El borde va en cada th: el de la tabla no acompaña a la cabecera fija al desplazarse.
                'lg:sticky lg:top-0 lg:z-10 lg:[&_th]:border-b lg:[&_th]:border-zinc-200' => $ajustar,
            ])>
                <tr>{{ $cabecera }}</tr>
            </thead>
        @endisset
        <tbody class="divide-y divide-zinc-100 text-zinc-800 [&_td]:px-4 [&_td]:py-3">
            {{-- hasActualContent() y no isEmpty(): un @foreach sin elementos deja saltos de línea y sangría,
                 que isEmpty() cuenta como contenido; el aviso de tabla vacía nunca llegaba a salir. --}}
            @if ($vacio && ! $slot->hasActualContent())
                <tr><td colspan="100" class="px-4 py-10 text-center text-zinc-500">{{ $vacio }}</td></tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>
