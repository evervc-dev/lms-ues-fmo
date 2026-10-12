{{-- Bloque de carga (para #[Lazy] y wire:loading). <x-ui.skeleton :lineas="3" class="h-32" /> --}}
@props(['lineas' => 3])

<div {{ $attributes->class(['animate-pulse rounded-xl bg-white p-4 ring-1 ring-borde']) }} aria-hidden="true">
    <div class="mb-3 h-4 w-1/3 rounded bg-zinc-200"></div>
    @for ($i = 0; $i < $lineas; $i++)
        <div class="mb-2 h-3 rounded bg-zinc-100" style="width: {{ 90 - $i * 15 }}%"></div>
    @endfor
</div>
