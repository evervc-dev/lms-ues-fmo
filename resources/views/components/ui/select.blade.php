{{-- <x-ui.select id="seccion" wire:model="seccion"><option value="">Elija…</option>…</x-ui.select> --}}
@php
    $campo = $attributes->wire('model')->value() ?: $attributes->get('name');
    $invalido = $campo && $errors->has($campo);
@endphp

<select
    {{ $attributes->class([
        'block w-full rounded-lg border-0 bg-white py-2 pl-3 pr-8 text-sm text-zinc-900 ring-1 ring-inset focus:ring-2 focus:ring-inset disabled:bg-zinc-50',
        'ring-red-400 focus:ring-red-600' => $invalido,
        'ring-zinc-300 focus:ring-marca-600' => ! $invalido,
    ]) }}
    @if ($invalido) aria-invalid="true" @if ($attributes->has('id')) aria-describedby="{{ $attributes->get('id') }}-error" @endif @endif
>{{ $slot }}</select>
