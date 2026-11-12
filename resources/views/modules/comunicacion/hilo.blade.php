{{-- Hilo del foro (RF30, maqueta 41). --}}
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    <x-ui.card :titulo="$hilo->title">
        <x-slot:acciones>
            @if ($hilo->is_closed)<x-ui.badge>{{ __('Cerrado') }}</x-ui.badge>@endif
            @if ($modera)
                <x-ui.button tamano="sm" variante="fantasma" wire:click="moderar('fijar', null)">{{ $hilo->is_pinned ? __('Desfijar') : __('Fijar') }}</x-ui.button>
                <x-ui.button tamano="sm" variante="secundario" wire:click="moderar('cerrar', null)" wire:confirm="{{ $hilo->is_closed ? __('¿Reabrir el hilo? Volverán a poder responder.') : __('¿Cerrar el hilo? Nadie más podrá responder.') }}">{{ $hilo->is_closed ? __('Reabrir') : __('Cerrar hilo') }}</x-ui.button>
            @endif
        </x-slot:acciones>
        <ul class="flex flex-col gap-3">
            @foreach ($raiz as $m)
                @include('modules.comunicacion.partials.mensaje', ['m' => $m])
            @endforeach
        </ul>
    </x-ui.card>

    @unless ($hilo->is_closed)
        <x-ui.card :titulo="$respondiendoA ? __('Responder al mensaje') : __('Responder al hilo')">
            <form wire:submit="responder" class="flex flex-col gap-3">
                <x-ui.textarea wire:model="mensaje" rows="3" :aria-label="__('Mensaje')" />
                @error('mensaje')<p class="text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                <div class="flex justify-end gap-2">
                    @if ($respondiendoA)<x-ui.button variante="fantasma" wire:click="responderA(null)">{{ __('Cancelar respuesta') }}</x-ui.button>@endif
                    <x-ui.button type="submit">{{ __('Publicar') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endunless
</div>
