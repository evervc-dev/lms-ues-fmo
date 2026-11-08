{{--
    Campo de formulario: etiqueta, control, ayuda y error.
    <x-ui.field etiqueta="Clave de la asignatura" para="clave" error="clave" ayuda="Se la da su docente." requerido>
        <x-ui.input id="clave" wire:model="clave" />
    </x-ui.field>
--}}
@props(['etiqueta', 'para' => null, 'error' => null, 'ayuda' => null, 'requerido' => false])

<div {{ $attributes->class(['flex flex-col gap-1.5']) }}>
    <label @if ($para) for="{{ $para }}" @endif class="text-sm font-medium text-zinc-800">
        {{ $etiqueta }}
        @if ($requerido)
            <span class="text-red-600" aria-hidden="true">*</span>
        @endif
    </label>
    {{ $slot }}
    @if ($ayuda)
        <p @if ($para) id="{{ $para }}-ayuda" @endif class="text-xs text-zinc-500">{{ $ayuda }}</p>
    @endif
    @if ($error)
        @error($error)
            <p @if ($para) id="{{ $para }}-error" @endif class="text-xs font-medium text-red-700">{{ $message }}</p>
        @enderror
    @endif
</div>
