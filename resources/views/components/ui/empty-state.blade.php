{{-- Estado vacío: explica qué hacer. <x-ui.empty-state icono="book-open" titulo="…">Texto <x-slot:acciones>…</x-slot:acciones></x-ui.empty-state> --}}
@props(['icono' => 'information-circle', 'titulo'])

<div {{ $attributes->class(['flex flex-col items-center gap-3 px-4 py-10 text-center']) }}>
    <span class="rounded-full bg-zinc-100 p-3 text-zinc-500"><x-ui.icon :name="$icono" class="size-6" /></span>
    <h3 class="text-base font-semibold text-zinc-900">{{ $titulo }}</h3>
    @if ($slot->isNotEmpty())
        <p class="max-w-md text-sm text-zinc-600">{{ $slot }}</p>
    @endif
    @isset($acciones)
        <div class="mt-2 flex flex-wrap justify-center gap-2">{{ $acciones }}</div>
    @endisset
</div>
