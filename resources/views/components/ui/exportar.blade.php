{{--
    Botón «Exportar» con los tres formatos (ADR-049). El componente Livewire expone un método que recibe el
    formato y devuelve la descarga:

        <x-ui.exportar />                      → llama a exportar('xlsx' | 'pdf' | 'csv')
        <x-ui.exportar metodo="exportarActa" /> → llama a ese otro método

    Se mantienen los tres: Excel y PDF los pide el RF, y el CSV ya estaba en uso en varias pantallas.
    Con `:pdf="false"` se ofrece solo Excel y CSV, para los listados que pueden traer miles de filas: los dos
    escriben en streaming, mientras que dompdf arma el documento entero en memoria (ADR-049).
--}}
@props(['metodo' => 'exportar', 'tamano' => 'md', 'pdf' => true])

<x-ui.dropdown alinear="derecha" ancho="w-56">
    <x-slot:disparador>
        <span @class([
            'inline-flex items-center justify-center gap-2 rounded-lg bg-white font-medium text-texto ring-1 ring-inset ring-borde transition hover:bg-zinc-50',
            'px-3 py-1.5 text-sm' => $tamano === 'sm',
            'px-4 py-2 text-sm' => $tamano !== 'sm',
        ])>
            <x-ui.icon name="arrow-down-tray" class="size-4" />
            {{ __('Exportar') }}
            <x-ui.icon name="chevron-down" class="size-4 text-texto-suave" />
        </span>
    </x-slot:disparador>

    <x-ui.dropdown.item icono="table-cells" wire:click="{{ $metodo }}('xlsx')">{{ __('Excel (.xlsx)') }}</x-ui.dropdown.item>
    @if ($pdf)
        <x-ui.dropdown.item icono="document-chart-bar" wire:click="{{ $metodo }}('pdf')">{{ __('PDF') }}</x-ui.dropdown.item>
    @endif
    <x-ui.dropdown.item icono="rectangle-stack" wire:click="{{ $metodo }}('csv')">{{ __('CSV') }}</x-ui.dropdown.item>
</x-ui.dropdown>
