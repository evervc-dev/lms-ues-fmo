{{--
    Layout Examen (RF16): sin menú ni campana. La cabecera muestra el título, el temporizador
    del servidor y el estado del autoguardado (slot «estado»); el pie, la navegación de preguntas.
    <x-layouts::exam title="Parcial 1 · PRN115"><x-slot:estado>…</x-slot:estado>…<x-slot:pie>…</x-slot:pie></x-layouts::exam>
--}}
@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-fondo">
<head>
    @include('layouts::partials.head')
</head>
<body class="flex min-h-full flex-col font-sans text-zinc-900 antialiased">
    <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-zinc-200 bg-white px-4 py-3">
        <h1 class="min-w-0 flex-1 truncate text-base font-semibold">{{ $title }}</h1>
        @isset($estado)
            <div class="flex items-center gap-3 text-sm">{{ $estado }}</div>
        @endisset
    </header>
    <main id="contenido" class="mx-auto w-full max-w-3xl flex-1 px-4 py-6">
        {{ $slot }}
    </main>
    @isset($pie)
        <footer class="sticky bottom-0 border-t border-zinc-200 bg-white px-4 py-3">
            <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-2">{{ $pie }}</div>
        </footer>
    @endisset
    <x-ui.toasts />
    @livewireScripts
</body>
</html>
