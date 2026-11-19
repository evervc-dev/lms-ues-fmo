{{-- Paginación simple de Livewire (sin total, p. ej. la auditoría) con la guía de estilo del LMS. --}}
@php
    if (! isset($scrollTo)) {
        $scrollTo = 'body';
    }
    $scrollIntoViewJsSnippet = ($scrollTo !== false) ? "(\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()" : '';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Paginación') }}" class="flex items-center justify-end gap-1 py-3 text-sm">
        @if ($paginator->onFirstPage())
            <span class="rounded-lg px-3 py-1.5 text-texto-desactivado" aria-disabled="true">{{ __('Anterior') }}</span>
        @else
            <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="rounded-lg px-3 py-1.5 text-texto hover:bg-zinc-100">{{ __('Anterior') }}</button>
        @endif

        @if ($paginator->hasMorePages())
            <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="rounded-lg px-3 py-1.5 text-texto hover:bg-zinc-100">{{ __('Siguiente') }}</button>
        @else
            <span class="rounded-lg px-3 py-1.5 text-texto-desactivado" aria-disabled="true">{{ __('Siguiente') }}</span>
        @endif
    </nav>
@endif
