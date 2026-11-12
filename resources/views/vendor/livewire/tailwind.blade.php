{{-- Paginación de Livewire con la guía de estilo del LMS (rojo UES, textos de usted). --}}
@php
    if (! isset($scrollTo)) {
        $scrollTo = 'body';
    }
    $scrollIntoViewJsSnippet = ($scrollTo !== false) ? "(\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()" : '';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Paginación') }}" class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
        <p class="text-texto-suave">
            {{ __('Mostrando :desde–:hasta de :total', ['desde' => $paginator->firstItem(), 'hasta' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="rounded-lg px-3 py-1.5 text-texto-desactivado" aria-disabled="true">{{ __('Anterior') }}</span>
            @else
                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="rounded-lg px-3 py-1.5 text-texto hover:bg-zinc-100">{{ __('Anterior') }}</button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-texto-suave">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="rounded-lg bg-marca-50 px-3 py-1.5 font-semibold text-marca-700 ring-1 ring-inset ring-marca-200" wire:key="paginator-{{ $paginator->getPageName() }}-page-{{ $page }}">{{ $page }}</span>
                        @else
                            <button type="button" wire:key="paginator-{{ $paginator->getPageName() }}-page-{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="rounded-lg px-3 py-1.5 text-texto hover:bg-zinc-100" aria-label="{{ __('Ir a la página :pagina', ['pagina' => $page]) }}">{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="rounded-lg px-3 py-1.5 text-texto hover:bg-zinc-100">{{ __('Siguiente') }}</button>
            @else
                <span class="rounded-lg px-3 py-1.5 text-texto-desactivado" aria-disabled="true">{{ __('Siguiente') }}</span>
            @endif
        </div>
    </nav>
@endif
