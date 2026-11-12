{{--
    Layout Invitado: inicio de sesión, «Entrar como…», acceso de emergencia y páginas de error.
    <x-layouts::guest title="Iniciar sesión">contenido de la tarjeta</x-layouts::guest>
--}}
@props(['title' => null, 'ancho' => 'md'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-fondo">
<head>
    @include('layouts::partials.head')
</head>
<body class="flex min-h-full flex-col items-center justify-center px-4 py-10 font-sans text-zinc-900 antialiased">
    <main class="flex w-full flex-col items-center gap-6">
        <div class="flex flex-col items-center gap-2 text-center">
            <span class="grid size-12 place-items-center rounded-xl bg-marca-600 text-sm font-bold text-white">UES</span>
            <p class="text-sm font-medium text-zinc-600">{{ __('Universidad de El Salvador · Facultad Multidisciplinaria de Oriente') }}</p>
        </div>
        <div @class([
            'w-full rounded-2xl bg-white p-6 shadow-sm ring-1 ring-zinc-200 sm:p-8',
            $ancho === 'lg' ? 'max-w-2xl' : 'max-w-md',
        ])>
            @if ($title)
                <h1 class="mb-4 text-xl font-semibold text-zinc-900">{{ $title }}</h1>
            @endif
            {{ $slot }}
        </div>
    </main>
    <footer class="mt-8 text-xs text-zinc-500">
        LMS UES FMO
        @if (Route::has('ayuda'))
            · <a href="{{ route('ayuda') }}" class="hover:underline">{{ __('Ayuda') }}</a>
        @endif
    </footer>
    <x-ui.toasts />
    @livewireScripts
</body>
</html>
