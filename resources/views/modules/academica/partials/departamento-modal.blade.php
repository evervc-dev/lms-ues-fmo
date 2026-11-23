{{-- Modal para crear o editar un departamento (RF07). Lo incluyen Estructura y Departamentos, con GestionaDepartamentos. --}}
<x-ui.modal nombre="departamento" :titulo="$departamentoEditando ? __('Editar departamento') : __('Nuevo departamento')">
    <form wire:submit="guardarDepartamento" id="form-departamento" class="grid gap-4 sm:grid-cols-[1fr_2fr]">
        <x-ui.field :etiqueta="__('Código')" para="departamentoCodigo" error="departamentoCodigo" requerido><x-ui.input id="departamentoCodigo" wire:model="departamentoCodigo" placeholder="DIA" /></x-ui.field>
        <x-ui.field :etiqueta="__('Nombre')" para="departamentoNombre" error="departamentoNombre" requerido><x-ui.input id="departamentoNombre" wire:model="departamentoNombre" /></x-ui.field>
    </form>
    <x-slot:pie>
        <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'departamento')">{{ __('Cancelar') }}</x-ui.button>
        <x-ui.button type="submit" form="form-departamento">{{ __('Guardar') }}</x-ui.button>
    </x-slot:pie>
</x-ui.modal>
