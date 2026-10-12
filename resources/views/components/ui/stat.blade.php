{{--
    Indicador (KPI) de las maquetas de panel.
    <x-ui.stat etiqueta="Secciones planificadas" valor="32" detalle="100 % de la oferta" icono="squares-2x2" tono="exito|aviso|error|neutro" />

    Las tarjetas de una misma fila se alinean entre sí: la etiqueta reserva dos renglones (hay rótulos que
    envuelven, como «Jefe de departamento») y el detalle se pega abajo, de modo que todos los números
    quedan a la misma altura (revisión de diseño del 25/10/2026).
--}}
@props(['etiqueta', 'valor', 'detalle' => null, 'icono' => null, 'tono' => 'neutro'])

@php
    $colorDetalle = match ($tono) { 'exito' => 'text-emerald-700', 'aviso' => 'text-amber-700', 'error' => 'text-red-700', default => 'text-texto-suave' };
@endphp

<div {{ $attributes->class(['flex h-full flex-col gap-1 rounded-xl bg-white p-4 ring-1 ring-borde']) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="min-h-8 text-xs font-semibold uppercase leading-4 tracking-wide text-texto-suave">{{ $etiqueta }}</p>
        @if ($icono)
            <span class="shrink-0 rounded-lg bg-marca-50 p-2 text-marca-600"><x-ui.icon :name="$icono" /></span>
        @endif
    </div>
    <p class="text-3xl font-semibold leading-none text-texto">{{ $valor }}</p>
    @if ($detalle)
        <p class="mt-auto pt-2 text-xs font-medium {{ $colorDetalle }}">{{ $detalle }}</p>
    @endif
    {{ $slot }}
</div>
