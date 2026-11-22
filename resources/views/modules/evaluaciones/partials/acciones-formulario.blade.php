{{--
    Barra de acciones de un formulario largo (tarea, cuestionario): queda pegada abajo mientras se desplaza, así el botón
    de guardar siempre está a mano. Variables: $volver (URL de Cancelar) y $enviar (texto del botón).
--}}
<div class="sticky bottom-2 z-10 flex justify-end gap-2 rounded-xl bg-white/95 px-3 py-2.5 shadow-sm ring-1 ring-borde backdrop-blur sm:px-4 sm:py-3">
    <x-ui.button variante="secundario" :href="$volver">{{ __('Cancelar') }}</x-ui.button>
    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="guardar">{{ $enviar }}</x-ui.button>
</div>
