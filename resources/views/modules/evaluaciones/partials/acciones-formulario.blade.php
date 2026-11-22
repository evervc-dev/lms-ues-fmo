{{--
    Barra de acciones de un formulario largo (tarea, cuestionario): queda pegada abajo mientras se desplaza, así el botón
    de guardar siempre está a mano. Variables: $volver (URL de Cancelar) y $enviar (texto del botón).
    Fondo opaco y sombra hacia arriba: con el fondo translúcido y desenfocado, los campos de detrás se veían encimados
    bajo la barra (revisión del rol Docente). El formulario que la incluye deja espacio con scroll-mb para que el campo
    enfocado no quede escondido detrás. En escritorio el <main> se desplaza con 20 px de relleno abajo (py-5): sin el
    -bottom-5, entre la barra y el borde se asomaba el contenido.
--}}
<div class="sticky bottom-0 z-10 lg:-bottom-5 -mx-1 flex justify-end gap-2 rounded-t-xl border-t border-borde bg-white px-4 py-3 shadow-[0_-6px_16px_-8px_rgb(0_0_0/0.15)]">
    <x-ui.button variante="secundario" :href="$volver">{{ __('Cancelar') }}</x-ui.button>
    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="guardar">{{ $enviar }}</x-ui.button>
</div>
