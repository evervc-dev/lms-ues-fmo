{{--
    Layout Invitado: inicio de sesión, «Entrar como…», escenarios de prueba, acceso de emergencia y
    páginas de error.
    <x-layouts::guest title="Iniciar sesión" ancho="md|lg|xl">contenido de la tarjeta</x-layouts::guest>
--}}
@props(['title' => null, 'ancho' => 'md'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-fondo">
<head>
    @include('layouts::partials.head')
</head>
<body class="flex min-h-full flex-col items-center justify-center px-4 py-10 font-sans text-texto antialiased">
    {{-- Franja institucional: el rojo UES de la cabecera del sitio oficial (Guía de estilo, sección 2). --}}
    <div class="fixed inset-x-0 top-0 h-1 bg-marca-600" aria-hidden="true"></div>

    <main class="flex w-full flex-col items-center gap-6">
        {{-- Marcador «UES»: el escudo oficial se coloca cuando la universidad entregue el archivo; no se dibuja ni se inventa. --}}
        <div class="flex items-center gap-3 text-left">
            <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-marca-600 text-sm font-bold tracking-tight text-white shadow-[0_2px_8px_rgba(174,21,45,0.25)]">UES</span>
            <div class="flex flex-col">
                <p class="text-sm font-semibold uppercase tracking-wide text-marca-700">{{ __('Universidad de El Salvador') }}</p>
                <p class="text-sm text-texto-suave">{{ __('Facultad Multidisciplinaria Oriental') }}</p>
            </div>
        </div>
        <div @class([
            'w-full rounded-2xl border-t-4 border-marca-600 bg-white p-6 shadow-sm ring-1 ring-borde sm:p-8',
            match ($ancho) {
                'xl' => 'max-w-5xl',
                'lg' => 'max-w-2xl',
                default => 'max-w-md',
            },
        ])>
            @if ($title)
                <h1 class="mb-4 text-xl font-semibold text-texto">{{ $title }}</h1>
            @endif
            {{ $slot }}
        </div>
    </main>
    <footer class="mt-8 flex flex-col items-center gap-1 text-center text-xs text-texto-suave">
        <p>
            {{ __('LMS UES FMO · Aula virtual de Ingeniería de Sistemas Informáticos') }}
            {{-- La Ayuda pide sesión: sin ella, el enlace volvía al login. --}}
            @if (Route::has('ayuda') && auth()->check())
                · <a href="{{ route('ayuda') }}" class="hover:underline">{{ __('Ayuda') }}</a>
            @endif
        </p>
        <p>© {{ now()->year }} {{ __('Universidad de El Salvador · Facultad Multidisciplinaria Oriental') }}</p>
    </footer>
    <x-ui.toasts />
    @livewireScripts
</body>
</html>
