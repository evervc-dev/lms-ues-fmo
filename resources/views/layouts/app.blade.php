{{--
    Layout Aplicación (docs/13 Layout/Layout base.md, maqueta 17): cabecera, menú lateral, migas y contenido.
    Navegación con wire:navigate (ADR-035): el menú lateral persiste entre páginas y la opción activa la marca
    wire:current. Desde 1024 px el menú queda fijo y se contrae; por debajo, ☰ lo abre sobre el contenido.
    Las variables $usuario, $iniciales, $roles, $menu, $periodoActivo y $asistenciaAbierta
    vienen de App\Support\Navegacion\Cabecera.

    <x-layouts::app title="Mis materias" subtitulo="…" :migas="[…]" ayuda="estudiante#3-…">
        <x-slot:acciones>…</x-slot:acciones>
        contenido
    </x-layouts::app>
--}}
@props(['title' => null, 'subtitulo' => null, 'migas' => [], 'ayuda' => null, 'encabezado' => true])

@php
    extract(app(\App\Support\Navegacion\Cabecera::class)->datos());
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-fondo">
<head>
    @include('layouts::partials.head')
</head>
<body class="h-full font-sans text-texto antialiased">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">{{ __('Saltar al contenido') }}</a>

    {{-- Al navegar se cierra el menú y <main> vuelve arriba: desde 1024 px el desplazamiento es suyo, no de la ventana. --}}
    <div class="min-h-full" x-data x-on:livewire:navigated.window="$store.menu.abierto = false; document.getElementById('contenido')?.scrollTo({ top: 0 })">
        <header class="fixed inset-x-0 top-0 z-30 flex flex-col">
            @if ($usuario->is_emergency)
                <div role="alert" class="bg-red-700 px-4 py-1.5 text-center text-sm font-semibold text-white">{{ __('Está usando la cuenta de emergencia') }}</div>
            @endif
            <div class="flex h-16 items-center gap-2 border-b border-borde bg-white px-3 sm:gap-3 sm:px-4">
                <button
                    type="button"
                    x-on:click="window.matchMedia('(min-width: 1024px)').matches ? $store.menu.contraido = ! $store.menu.contraido : $store.menu.abierto = true"
                    class="rounded-lg p-2 text-texto-suave hover:bg-zinc-100 hover:text-texto"
                    aria-controls="menu-lateral"
                    :aria-expanded="(window.matchMedia('(min-width: 1024px)').matches ? ! $store.menu.contraido : $store.menu.abierto).toString()"
                    aria-label="{{ __('Menú') }}"
                >
                    <x-ui.icon name="bars-3" class="size-6" />
                </button>

                <a href="{{ route('inicio') }}" wire:navigate class="flex items-center gap-2.5 font-semibold text-texto">
                    <span class="grid size-9 place-items-center rounded-lg bg-marca-600 text-xs font-bold text-white">UES</span>
                    <span class="hidden sm:inline">LMS UES FMO</span>
                </a>

                <span @class([
                    'ml-1 hidden items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset md:inline-flex',
                    'bg-marca-50 text-marca-700 ring-marca-200' => $periodoActivo,
                    'bg-zinc-100 text-texto-suave ring-borde' => ! $periodoActivo,
                ])>
                    <span @class(['size-1.5 rounded-full', 'bg-marca-600' => $periodoActivo, 'bg-texto-desactivado' => ! $periodoActivo])></span>
                    {{ $periodoActivo ? __('Período :periodo · en curso', ['periodo' => $periodoActivo]) : __('Sin período activo') }}
                </span>

                @if ($asistenciaAbierta)
                    <a href="{{ route('asistencia.escanear') }}" wire:navigate class="mx-auto hidden items-center gap-2 rounded-full bg-marca-50 px-3 py-1.5 text-sm text-texto ring-1 ring-inset ring-marca-200 hover:bg-marca-100 sm:inline-flex">
                        <span class="size-2 animate-pulse rounded-full bg-marca-600"></span>
                        {{ __('Asistencia abierta · :seccion', ['seccion' => $asistenciaAbierta]) }}
                        <span class="font-semibold text-marca-700 underline">{{ __('Marcar') }}</span>
                    </a>
                @endif

                <div class="ml-auto flex items-center gap-1 sm:gap-2">
                    @if ($ayuda && Route::has('ayuda'))
                        <a href="{{ route('ayuda', ['manual' => $ayuda]) }}" wire:navigate class="rounded-lg p-2 text-texto-suave hover:bg-zinc-100" aria-label="{{ __('Ayuda de esta pantalla') }}">
                            <x-ui.icon name="question-mark-circle" class="size-6" />
                        </a>
                    @endif

                    <livewire:comunicacion.campana />

                    <span class="mx-1 hidden h-8 w-px bg-borde sm:block" aria-hidden="true"></span>

                    <x-ui.dropdown alinear="derecha" ancho="w-64">
                        <x-slot:disparador>
                            <span class="flex items-center gap-2 rounded-lg p-1 hover:bg-zinc-100">
                                <x-ui.avatar :iniciales="$iniciales" :foto="$usuario->avatar_url" />
                                <span class="hidden text-left leading-tight lg:block">
                                    <span class="block text-sm font-semibold text-texto">{{ $usuario->fullName() }}</span>
                                    <span class="block text-xs text-texto-suave">{{ $roles }}</span>
                                </span>
                                <span class="sr-only">{{ __('Menú de usuario') }}</span>
                                <x-ui.icon name="chevron-down" class="hidden size-4 text-texto-suave sm:block" />
                            </span>
                        </x-slot:disparador>

                        <div class="border-b border-zinc-100 px-3 pb-2 pt-1">
                            <p class="truncate text-sm font-semibold text-texto">{{ $usuario->fullName() }}</p>
                            <p class="truncate text-xs text-texto-suave">{{ $usuario->institutional_email }}</p>
                        </div>
                        <x-ui.dropdown.item :href="route('perfil')" icono="user-circle">{{ __('Mi perfil') }}</x-ui.dropdown.item>
                        <x-ui.dropdown.item :href="route('ayuda')" icono="question-mark-circle">{{ __('Ayuda') }}</x-ui.dropdown.item>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-ui.dropdown.item type="submit" icono="arrow-right-start-on-rectangle">{{ __('Cerrar sesión') }}</x-ui.dropdown.item>
                        </form>
                    </x-ui.dropdown>
                </div>
            </div>
        </header>

        {{-- Menú en el teléfono y la tableta: se abre sobre el contenido. --}}
        <div x-show="$store.menu.abierto" x-cloak class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('Menú principal') }}">
            <div x-show="$store.menu.abierto" x-transition.opacity class="fixed inset-0 bg-zinc-900/50" x-on:click="$store.menu.abierto = false"></div>
            <div
                x-show="$store.menu.abierto"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                x-trap.noscroll="$store.menu.abierto"
                x-on:keydown.escape.window="$store.menu.abierto = false"
                class="fixed inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col bg-white shadow-xl"
            >
                <div class="flex h-16 items-center justify-between border-b border-borde px-4">
                    <span class="flex items-center gap-2 font-semibold text-texto">
                        <span class="grid size-8 place-items-center rounded-lg bg-marca-600 text-[10px] font-bold text-white">UES</span>
                        LMS UES FMO
                    </span>
                    <button type="button" x-on:click="$store.menu.abierto = false" class="rounded-lg p-2 text-texto-suave hover:bg-zinc-100" aria-label="{{ __('Cerrar menú') }}">
                        <x-ui.icon name="x-mark" class="size-6" />
                    </button>
                </div>
                @include('layouts::partials.menu', ['compacto' => false])
            </div>
        </div>

        {{-- Menú fijo desde 1024 px; persiste entre páginas (wire:navigate). --}}
        @persist('menu-lateral')
            <aside
                id="menu-lateral"
                @class(['fixed bottom-0 left-0 z-20 hidden flex-col border-r border-borde bg-white transition-[width] lg:flex', 'top-[6.25rem]' => $usuario->is_emergency, 'top-16' => ! $usuario->is_emergency])
                :class="$store.menu.contraido ? 'w-[4.5rem]' : 'w-72'"
            >
                @include('layouts::partials.menu', ['compacto' => true])
            </aside>
        @endpersist

        {{-- Desde 1024 px el alto lo fija la ventana y el desplazamiento vive en <main>, para que los listados
             con tabla ajustada mantengan a la vista el título y los indicadores. El relleno superior deja
             libre la cabecera fija y, al ser border-box, ya se descuenta del alto. --}}
        <div @class(['transition-[padding] lg:h-dvh lg:overflow-hidden', 'pt-[6.25rem]' => $usuario->is_emergency, 'pt-16' => ! $usuario->is_emergency]) :class="$store.menu.contraido ? 'lg:pl-[4.5rem]' : 'lg:pl-72'">
            <main id="contenido" class="mx-auto flex w-full max-w-[1600px] flex-col gap-5 px-3 py-5 sm:px-4 lg:h-full lg:overflow-y-auto lg:px-5">
                <x-ui.breadcrumbs :migas="$migas" />
                @if ($encabezado && ($title || isset($acciones)))
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div class="min-w-0">
                            @if ($title)
                                <h1 class="text-2xl font-semibold tracking-tight text-texto">{{ $title }}</h1>
                            @endif
                            @if ($subtitulo)
                                <p class="mt-1 text-sm text-texto-suave">{{ $subtitulo }}</p>
                            @endif
                        </div>
                        @isset($acciones)
                            <div class="flex flex-wrap items-center gap-2">{{ $acciones }}</div>
                        @endisset
                    </div>
                @endif
                {{ $slot }}
            </main>
        </div>

        @if ($sinDispositivo && ! request()->routeIs('perfil.dispositivo'))
            <div
                x-data="{
                    abierto: ! sessionStorage.getItem('alerta_dispositivo_descartada'),
                    descartar() {
                        sessionStorage.setItem('alerta_dispositivo_descartada', '1');
                        this.abierto = false;
                    }
                }"
                x-show="abierto"
                x-cloak
                class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-alerta-dispositivo-titulo"
                x-on:keydown.escape.window="descartar()"
            >
                <div x-show="abierto" x-transition.opacity class="fixed inset-0 bg-zinc-900/50" x-on:click="descartar()"></div>

                <div
                    x-show="abierto"
                    x-transition
                    x-trap.noscroll="abierto"
                    class="relative flex max-h-[90vh] w-full max-w-md flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl"
                >
                    <header class="flex items-center justify-between gap-4 border-b border-zinc-100 px-5 py-4">
                        <div class="flex items-center gap-2.5">
                            <span class="grid size-9 place-items-center rounded-lg bg-amber-50 text-amber-600">
                                <x-ui.icon name="device-phone-mobile" class="size-5" />
                            </span>
                            <h2 id="modal-alerta-dispositivo-titulo" class="text-base font-semibold text-zinc-900">
                                {{ __('Registre su dispositivo de asistencia') }}
                            </h2>
                        </div>
                        <button type="button" x-on:click="descartar()" class="rounded-md p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700" aria-label="{{ __('Cerrar') }}">
                            <x-ui.icon name="x-mark" />
                        </button>
                    </header>

                    <div class="space-y-3 px-5 py-4 text-sm text-zinc-600">
                        <p>
                            {{ __('Aún no tiene un dispositivo móvil de confianza registrado en su cuenta.') }}
                        </p>
                        <p>
                            {{ __('Para registrar su asistencia a clases presenciales mediante código QR, es indispensable vincular su teléfono móvil personal.') }}
                        </p>
                        <div class="rounded-lg bg-amber-50/70 p-3 text-xs text-amber-900">
                            <p class="font-medium text-amber-950">{{ __('Importante') }}</p>
                            <p class="mt-0.5">{{ __('Solo puede tener un único dispositivo activo a la vez. Le recomendamos registrarlo desde el navegador de su teléfono.') }}</p>
                        </div>
                    </div>

                    <footer class="flex flex-col-reverse gap-2 border-t border-zinc-100 px-5 py-4 sm:flex-row sm:justify-end">
                        <x-ui.button type="button" variante="secundario" x-on:click="descartar()">
                            {{ __('Más tarde') }}
                        </x-ui.button>
                        <x-ui.button icono="device-phone-mobile" :href="route('perfil.dispositivo')" wire:navigate x-on:click="descartar()">
                            {{ __('Registrar dispositivo') }}
                        </x-ui.button>
                    </footer>
                </div>
            </div>
        @endif

        <x-ui.toasts />
    </div>

    @include('layouts::partials.alpine')
    @livewireScripts
</body>
</html>
