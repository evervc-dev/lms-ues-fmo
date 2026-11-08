{{--
    Encabezado de una pantalla Livewire: título, subtítulo y acciones (dentro del componente, así funcionan con wire:click).
    <x-ui.encabezado titulo="Usuarios" subtitulo="…"><x-slot:acciones>…</x-slot:acciones></x-ui.encabezado>
--}}
@props(['titulo', 'subtitulo' => null])

<div {{ $attributes->class(['flex flex-wrap items-end justify-between gap-3']) }}>
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight text-texto">{{ $titulo }}</h1>
        @if ($subtitulo)
            <p class="mt-1 text-sm text-texto-suave">{{ $subtitulo }}</p>
        @endif
        {{ $slot }}
    </div>
    @isset($acciones)
        <div class="flex flex-wrap items-center gap-2">{{ $acciones }}</div>
    @endisset
</div>
