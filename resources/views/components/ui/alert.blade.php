{{-- <x-ui.alert tipo="info|exito|aviso|error" titulo="…">Mensaje</x-ui.alert> --}}
@props(['tipo' => 'info', 'titulo' => null])

@php
    [$clases, $icono] = match ($tipo) {
        'exito' => ['bg-emerald-50 text-emerald-900 ring-emerald-200', 'check-circle'],
        'aviso' => ['bg-amber-50 text-amber-900 ring-amber-200', 'exclamation-triangle'],
        'error' => ['bg-red-50 text-red-900 ring-red-200', 'exclamation-triangle'],
        default => ['bg-sky-50 text-sky-900 ring-sky-200', 'information-circle'],
    };
@endphp

<div role="{{ in_array($tipo, ['error', 'aviso'], true) ? 'alert' : 'status' }}" {{ $attributes->class(['flex gap-3 rounded-lg p-4 text-sm ring-1 ring-inset', $clases]) }}>
    <x-ui.icon :name="$icono" class="mt-0.5" />
    <div class="min-w-0">
        @if ($titulo)
            <p class="font-semibold">{{ $titulo }}</p>
        @endif
        <div @class(['mt-1' => $titulo])>{{ $slot }}</div>
    </div>
</div>
