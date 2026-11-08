{{--
    Formulario en página (docs/13 Layout/Layout base.md), de una sola vista o **por pasos**.

    De una vista:
        <x-ui.formulario accion="crear" :volver="route('usuarios')" :enviar="__('Crear cuenta')">
            <x-ui.card>…campos…</x-ui.card>
        </x-ui.formulario>

    Por pasos: cada paso agrupa una categoría y el último es el resumen para revisar y confirmar. El
    componente Livewire usa el trait App\Support\Navegacion\FormularioPorPasos y declara sus pasos en
    pasos(); aquí se le pasan junto con el paso actual.

        <x-ui.formulario accion="crear" :volver="route('usuarios')" :enviar="__('Crear cuenta')"
            :pasos="$this->pasos()" :paso="$paso">
            <x-ui.formulario.paso :numero="1">…</x-ui.formulario.paso>
            <x-ui.formulario.paso :numero="2">…</x-ui.formulario.paso>
            <x-ui.formulario.paso :numero="3">…resumen…</x-ui.formulario.paso>
        </x-ui.formulario>

    Mientras no sea el último paso, la tecla Intro avanza en vez de enviar. Con `:enviar="false"` no se
    dibuja el botón de envío: es el caso de solo lectura.
--}}
@props(['accion', 'volver', 'enviar' => null, 'cancelar' => null, 'pasos' => [], 'paso' => 1])

@php
    $total = count($pasos);
    $porPasos = $total > 1;
    $ultimo = ! $porPasos || $paso >= $total;
@endphp

{{-- Hasta el último paso, Intro avanza: así no se crea nada a medio llenar. --}}
<form wire:submit="{{ $ultimo ? $accion : 'siguiente' }}" {{ $attributes->class(['flex flex-col gap-5']) }}>
    @if ($porPasos)
        <x-ui.formulario.indicador :pasos="$pasos" :paso="$paso" />
    @endif

    {{ $slot }}

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            @if ($porPasos && $paso > 1)
                <x-ui.button type="button" variante="secundario" icono="arrow-left" wire:click="anterior">{{ __('Atrás') }}</x-ui.button>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button variante="fantasma" :href="$volver">{{ $cancelar ?? ($enviar === false ? __('Volver') : __('Cancelar')) }}</x-ui.button>

            @if (! $ultimo)
                <x-ui.button type="button" wire:click="siguiente" wire:loading.attr="disabled" wire:target="siguiente">{{ __('Siguiente') }}</x-ui.button>
            @elseif ($enviar !== false)
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="{{ $accion }}">{{ $enviar ?? __('Guardar') }}</x-ui.button>
            @endif
        </div>
    </div>
</form>
