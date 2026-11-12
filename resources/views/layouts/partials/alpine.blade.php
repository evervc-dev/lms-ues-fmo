{{--
    Estado global del menú (Alpine): se define antes de que Livewire arranque Alpine y sobrevive a wire:navigate.
    «contraido» se guarda en el navegador; «abierto» es el panel del teléfono.
--}}
<script data-navigate-once>
    document.addEventListener('alpine:init', () => {
        window.Alpine.store('menu', {
            contraido: window.Alpine.$persist(false).as('lms.menu.contraido'),
            abierto: false,
        });
    });
</script>
