{{-- <x-ui.badge color="gris|marca|verde|ambar|rojo|azul">Texto</x-ui.badge> --}}
@props(['color' => 'gris'])

<span {{ $attributes->class([
    'inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
    match ($color) {
        'marca' => 'bg-marca-50 text-marca-800 ring-marca-200',
        'verde' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'ambar' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'rojo' => 'bg-red-50 text-red-800 ring-red-200',
        'azul' => 'bg-sky-50 text-sky-800 ring-sky-200',
        default => 'bg-zinc-100 text-zinc-700 ring-zinc-200',
    },
]) }}>{{ $slot }}</span>
