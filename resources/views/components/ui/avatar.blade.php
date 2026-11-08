{{-- Avatar con iniciales (o foto). <x-ui.avatar iniciales="KR" tamano="sm|md|lg" :foto="$url" />
    La foto de Google se enlaza sin referrer (ADR-039); si no carga, se muestran las iniciales. --}}
@props(['iniciales' => '', 'foto' => null, 'tamano' => 'md'])

@php
    $medida = match ($tamano) { 'sm' => 'size-7 text-[10px]', 'lg' => 'size-12 text-base', default => 'size-9 text-xs' };
    $clasesIniciales = 'grid shrink-0 place-items-center rounded-full bg-marca-100 font-semibold text-marca-700';
@endphp

@if ($foto)
    <span x-data="{ fallo: false }" {{ $attributes->class(['shrink-0', $medida]) }}>
        <img src="{{ $foto }}" alt="" referrerpolicy="no-referrer" x-show="! fallo" x-on:error="fallo = true" class="size-full rounded-full object-cover">
        <span x-show="fallo" x-cloak aria-hidden="true" class="{{ $clasesIniciales }} size-full">{{ $iniciales }}</span>
    </span>
@else
    <span aria-hidden="true" {{ $attributes->class([$clasesIniciales, $medida]) }}>{{ $iniciales }}</span>
@endif
