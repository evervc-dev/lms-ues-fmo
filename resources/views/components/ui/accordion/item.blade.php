{{--
    Elemento de acordeón. Con «recordar», el navegador guarda si quedó abierto o cerrado. El slot opcional
    «resumen» se ve también con el elemento cerrado, debajo del título (p. ej. la barra de avance de la unidad).
    <x-ui.accordion.item titulo="Unidad 1" :abierto="true" recordar="panel.estudio">…</x-ui.accordion.item>
--}}
@props(['titulo', 'abierto' => false, 'recordar' => null])

@php
    $id = 'acordeon-'.\Illuminate\Support\Str::random(8);
    $estado = $recordar ? '$persist('.\Illuminate\Support\Js::from($abierto).').as('.\Illuminate\Support\Js::from('lms.'.$recordar).')' : \Illuminate\Support\Js::from($abierto);
@endphp

<div x-data="{ abierto: {{ $estado }} }" {{ $attributes }}>
    <h3>
        <button
            type="button"
            x-on:click="abierto = ! abierto"
            :aria-expanded="abierto.toString()"
            aria-controls="{{ $id }}"
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left font-medium text-zinc-900 hover:bg-zinc-50 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-marca-600"
        >
            <span>{{ $titulo }}</span>
            <x-ui.icon name="chevron-down" class="size-5 text-zinc-500 transition-transform" ::class="abierto && 'rotate-180'" />
        </button>
    </h3>
    @isset($resumen)
        <div class="px-4 pb-3">{{ $resumen }}</div>
    @endisset
    <div id="{{ $id }}" x-show="abierto" x-collapse x-cloak>
        <div class="px-4 pb-4">{{ $slot }}</div>
    </div>
</div>
