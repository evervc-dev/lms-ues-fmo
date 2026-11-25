{{--
    Indicador (KPI) de las maquetas de panel.
    <x-ui.stat etiqueta="Secciones planificadas" valor="32" detalle="100 % de la oferta" icono="squares-2x2" tono="exito|aviso|error|neutro" />

    Las tarjetas de una misma fila se alinean entre sí: la etiqueta reserva dos renglones (hay rótulos que
    envuelven, como «Jefe de departamento») y el detalle se pega abajo, de modo que todos los números
    quedan a la misma altura (revisión de diseño del 25/10/2026).

    Como atajo (QA 24/11/2026): con `href` toda la tarjeta lleva a la lista ya filtrada, y con `accion` muestra
    abajo el botón de lo que hay que hacer («Asignar», «Revisar»). El número toma el color del tono: verde si está
    bien, ámbar o rojo si pide atención. `externo` abre el enlace sin wire:navigate (Horizon).
    <x-ui.stat etiqueta="Sin docente" valor="43" tono="error" :href="route('carga-academica')" accion="Asignar" />
--}}
@props(['etiqueta', 'valor', 'detalle' => null, 'icono' => null, 'tono' => 'neutro', 'href' => null, 'accion' => null, 'externo' => false])

@php
    $colorDetalle = match ($tono) { 'exito' => 'text-emerald-700', 'aviso' => 'text-amber-700', 'error' => 'text-red-700', default => 'text-texto-suave' };
    $colorValor = match ($tono) { 'exito' => 'text-emerald-700', 'aviso' => 'text-amber-700', 'error' => 'text-red-700', default => 'text-texto' };
    $etiquetaRaiz = $href ? 'a' : 'div';
@endphp

<{{ $etiquetaRaiz }} @if ($href) href="{{ $href }}" @unless ($externo) wire:navigate @else target="_blank" rel="noopener" @endunless @endif
    {{ $attributes->class(['flex h-full flex-col gap-1 rounded-xl bg-white p-4 ring-1 ring-borde', 'group transition hover:ring-2 hover:ring-marca-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-marca-400' => $href]) }}>
    <div class="flex items-start justify-between gap-3">
        {{-- Sin hyphens-auto: partía los rótulos con guion («MATRÍCULAS ACTI- / VAS», QA Jefe 21/11). --}}
        <p class="min-h-8 min-w-0 break-words text-xs font-semibold uppercase leading-4 tracking-wide text-texto-suave">{{ $etiqueta }}</p>
        @if ($icono)
            <span class="shrink-0 rounded-lg bg-marca-50 p-2 text-marca-600"><x-ui.icon :name="$icono" /></span>
        @endif
    </div>
    <p class="text-3xl font-semibold leading-none {{ $colorValor }}">{{ $valor }}</p>
    @if ($detalle)
        <p class="mt-auto pt-2 text-xs font-medium {{ $colorDetalle }}">{{ $detalle }}</p>
    @endif
    {{ $slot }}
    @if ($href && $accion)
        <span @class(['mt-2 inline-flex w-fit items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold', 'bg-red-50 text-red-700 group-hover:bg-red-100' => $tono === 'error', 'bg-amber-50 text-amber-800 group-hover:bg-amber-100' => $tono === 'aviso', 'bg-marca-50 text-marca-700 group-hover:bg-marca-100' => ! in_array($tono, ['error', 'aviso'], true)])>
            {{ $accion }}<x-ui.icon name="chevron-right" class="size-3.5" />
        </span>
    @endif
</{{ $etiquetaRaiz }}>
