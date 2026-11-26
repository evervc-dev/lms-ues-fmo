{{--
    Barra de acciones de un formulario largo (tarea, cuestionario): va al final del formulario, en el flujo normal.
    Variables: $volver (URL de Cancelar) y $enviar (texto del botón).
    Antes quedaba pegada abajo (sticky) mientras se desplazaba; aun con fondo opaco tapaba los campos de debajo y se veía
    encimada sobre «Cómputo» y «Peso» (revisión del rol Docente en staging, 22/11/2026). Ahora no flota: se llega a ella
    al terminar de llenar, como en el resto de formularios.
--}}
<div class="flex justify-end gap-2 border-t border-borde pt-4">
    <x-ui.button variante="secundario" :href="$volver">{{ __('Cancelar') }}</x-ui.button>
    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="guardar">{{ $enviar }}</x-ui.button>
</div>
