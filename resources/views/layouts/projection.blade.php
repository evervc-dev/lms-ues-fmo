{{--
    Layout Proyección (RF20, ADR-028): pantalla completa para el proyector del aula.
    Muestra el QR y contadores; nunca nombres de estudiantes ni rechazos.
    <x-layouts::projection title="PRN115 · Grupo 01 · Asistencia">…</x-layouts::projection>
--}}
@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-texto">
<head>
    @include('layouts::partials.head')
</head>
<body class="flex min-h-full flex-col items-center justify-center gap-8 p-6 font-sans text-white antialiased">
    <h1 class="text-center text-2xl font-semibold sm:text-4xl">{{ $title }}</h1>
    <main class="flex w-full max-w-4xl flex-col items-center gap-8">
        {{ $slot }}
    </main>
    @livewireScripts
</body>
</html>
