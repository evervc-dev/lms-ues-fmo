{{-- <x-ui.textarea id="motivo" rows="3" wire:model="motivo" /> --}}
@php
    $campo = $attributes->wire('model')->value() ?: $attributes->get('name');
    $invalido = $campo && $errors->has($campo);
@endphp

<textarea
    {{ $attributes->merge(['rows' => 3])->class([
        'block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-zinc-900 ring-1 ring-inset placeholder:text-zinc-400 focus:ring-2 focus:ring-inset disabled:bg-zinc-50',
        'ring-red-400 focus:ring-red-600' => $invalido,
        'ring-zinc-300 focus:ring-marca-600' => ! $invalido,
    ]) }}
    @if ($invalido) aria-invalid="true" @if ($attributes->has('id')) aria-describedby="{{ $attributes->get('id') }}-error" @endif @endif
>{{ $slot }}</textarea>
