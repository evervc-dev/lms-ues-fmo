{{--
    Menú unificado (ADR-034). Recibe $menu de App\Support\Navegacion\Menu.
    $compacto = true en el menú fijo (se contrae a íconos con $store.menu.contraido); false en el panel móvil.
    La opción activa la marca wire:current, porque el menú fijo persiste entre páginas (ADR-035).

    Las opciones «secundarias» (una acción de la anterior, como Importar estudiantes bajo Usuarios) van con
    sangría y una guía vertical, para que se vea de qué opción cuelgan. Al navegar, la opción activa se trae
    a la vista: antes, al abrir una del final el menú aparecía desplazado hasta arriba
    (revisión de diseño del 25/10/2026).
--}}
@php
    $contraido = $compacto ? '$store.menu.contraido' : 'false';
    $clasesActiva = 'bg-marca-50 text-marca-700 font-semibold before:absolute before:inset-y-1.5 before:left-0 before:w-1 before:rounded-r before:bg-marca-600 [&_[data-slot=icon]]:text-marca-600';
@endphp

<nav
    aria-label="{{ __('Menú principal') }}"
    class="flex flex-1 flex-col gap-5 overflow-y-auto px-3 py-4"
    x-data
    x-on:livewire:navigated.window="$nextTick(() => (
        $el.querySelector(`a[href='${location.href}']`) ?? $el.querySelector(`a[href='${location.origin + location.pathname}']`)
    )?.scrollIntoView({ block: 'nearest' }))"
>
    @foreach ($menu['grupos'] as $grupo)
        <div class="flex flex-col gap-0.5">
            @if ($menu['conTitulos'] && $grupo['titulo'])
                <p x-show="! {{ $contraido }}" class="px-3 pb-1.5 text-xs font-semibold uppercase tracking-wider text-texto-suave">{{ $grupo['titulo'] }}</p>
                <hr x-show="{{ $contraido }}" x-cloak class="mx-2 mb-1 border-borde">
            @endif
            @foreach ($grupo['opciones'] as $opcion)
                @if ($opcion->disponible())
                    <a
                        href="{{ $opcion->url }}"
                        @if (! $opcion->externa) wire:navigate wire:current="{{ $clasesActiva }}" @else target="_blank" rel="noopener" @endif
                        @if ($opcion->activa) aria-current="page" @endif
                        :title="{{ $contraido }} ? @js($opcion->etiqueta) : null"
                        @class([
                            'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-texto transition hover:bg-zinc-100',
                            'ml-3 border-l border-borde pl-4' => $opcion->secundaria,
                            $clasesActiva => $opcion->activa,
                        ])
                        :class="{{ $contraido }} && @js($opcion->secundaria) ? '!ml-0 !border-l-0 !pl-3' : null"
                    >
                        <x-ui.icon :name="$opcion->icono" class="text-texto-suave group-hover:text-texto" />
                        <span x-show="! {{ $contraido }}" class="flex-1 truncate">{{ $opcion->etiqueta }}</span>
                        @if ($opcion->externa)
                            <x-ui.icon name="arrow-top-right-on-square" x-show="! {{ $contraido }}" class="size-3.5 text-texto-suave" />
                            <span class="sr-only">{{ __('(se abre en otra pestaña)') }}</span>
                        @endif
                        @if ($opcion->contador > 0)
                            <span x-show="! {{ $contraido }}" aria-label="{{ trans_choice('{1} 1 pendiente|[2,*] :count pendientes', $opcion->contador) }}"
                                class="rounded-full bg-marca-600 px-1.5 py-0.5 text-[11px] font-semibold leading-none text-white">{{ $opcion->contador > 99 ? '99+' : $opcion->contador }}</span>
                            <span x-show="{{ $contraido }}" x-cloak class="absolute right-1.5 top-1.5 size-2 rounded-full bg-marca-600" aria-hidden="true"></span>
                        @endif
                        @if ($opcion->pendiente)
                            <span x-show="! {{ $contraido }}" class="size-1.5 rounded-full bg-texto-desactivado" title="{{ __('En construcción') }}"></span>
                        @endif
                    </a>
                @else
                    <span aria-disabled="true" :title="{{ $contraido }} ? @js($opcion->etiqueta.' (pronto)') : null" class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-sm text-texto-desactivado">
                        <x-ui.icon :name="$opcion->icono" />
                        <span x-show="! {{ $contraido }}" class="flex-1 truncate">{{ $opcion->etiqueta }}</span>
                        <span x-show="! {{ $contraido }}" class="text-[10px] font-semibold uppercase tracking-wide">{{ __('Pronto') }}</span>
                    </span>
                @endif
            @endforeach
        </div>
    @endforeach

    <form method="POST" action="{{ route('logout') }}" class="mt-auto">
        @csrf
        <button type="submit" :title="{{ $contraido }} ? @js(__('Cerrar sesión')) : null" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-texto hover:bg-zinc-100">
            <x-ui.icon name="arrow-right-start-on-rectangle" class="text-texto-suave" />
            <span x-show="! {{ $contraido }}">{{ __('Cerrar sesión') }}</span>
        </button>
    </form>
</nav>
