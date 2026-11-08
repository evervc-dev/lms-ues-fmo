{{--
    Modal accesible (foco atrapado, Escape cierra).
    Abrir:  $dispatch('abrir-modal', 'nombre')   ·  Livewire: $this->dispatch('abrir-modal', 'nombre')
    Cerrar: $dispatch('cerrar-modal', 'nombre')
    <x-ui.modal nombre="inscribirme" titulo="Inscribirme con clave">…<x-slot:pie>…</x-slot:pie></x-ui.modal>
--}}
@props(['nombre', 'titulo', 'ancho' => 'md', 'abierto' => false])

@php
    $idTitulo = 'modal-'.$nombre.'-titulo';
@endphp

{{-- El nombre llega como texto desde Alpine ($dispatch) y como arreglo desde Livewire ($this->dispatch con
     parámetro posicional): se aceptan las dos formas. --}}
<div
    x-data="{ abierto: @js($abierto), esMio(detalle) { return (Array.isArray(detalle) ? detalle[0] : detalle) === @js($nombre) } }"
    x-on:abrir-modal.window="if (esMio($event.detail)) abierto = true"
    x-on:cerrar-modal.window="if (esMio($event.detail)) abierto = false"
    x-on:keydown.escape.window="abierto = false"
    x-show="abierto"
    x-cloak
    class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $idTitulo }}"
    {{ $attributes }}
>
    <div x-show="abierto" x-transition.opacity class="fixed inset-0 bg-zinc-900/50" x-on:click="abierto = false"></div>

    <div
        x-show="abierto"
        x-transition
        x-trap.noscroll="abierto"
        @class([
            'relative flex max-h-[90vh] w-full flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl',
            match ($ancho) {
                'sm' => 'sm:max-w-sm',
                'lg' => 'sm:max-w-2xl',
                'xl' => 'sm:max-w-4xl',
                default => 'sm:max-w-lg',
            },
        ])
    >
        <header class="flex items-center justify-between gap-4 border-b border-zinc-100 px-5 py-4">
            <h2 id="{{ $idTitulo }}" class="text-lg font-semibold text-zinc-900">{{ $titulo }}</h2>
            <button type="button" x-on:click="abierto = false" class="rounded-md p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700" aria-label="{{ __('Cerrar') }}">
                <x-ui.icon name="x-mark" />
            </button>
        </header>
        <div class="overflow-y-auto px-5 py-4">{{ $slot }}</div>
        @isset($pie)
            <footer class="flex flex-col-reverse gap-2 border-t border-zinc-100 px-5 py-4 sm:flex-row sm:justify-end">{{ $pie }}</footer>
        @endisset
    </div>
</div>
