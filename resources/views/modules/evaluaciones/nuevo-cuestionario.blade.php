{{-- Nuevo cuestionario (RF16). --}}
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />
    <form wire:submit="guardar" class="grid gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <x-ui.card :titulo="__('Nuevo cuestionario')">
            <div class="flex flex-col gap-4">
                <x-ui.field :etiqueta="__('Título')" para="tituloC" error="titulo" requerido><x-ui.input id="tituloC" wire:model="titulo" /></x-ui.field>
                <x-ui.field :etiqueta="__('Instrucciones')" para="instruccionesC"><x-ui.textarea id="instruccionesC" wire:model="instrucciones" rows="4" /></x-ui.field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field :etiqueta="__('Cómputo')" para="componenteC" error="componente" requerido><x-ui.select id="componenteC" wire:model="componente"><option value="">{{ __('Elija…') }}</option>@foreach ($computos as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</x-ui.select></x-ui.field>
                    <x-ui.field :etiqueta="__('Peso dentro del cómputo (%)')" para="pesoC" error="peso" requerido><x-ui.input id="pesoC" type="number" step="0.5" wire:model="peso" /></x-ui.field>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field :etiqueta="__('Abre')" para="abreC" error="abre" requerido><x-ui.input id="abreC" type="datetime-local" wire:model="abre" /></x-ui.field>
                    <x-ui.field :etiqueta="__('Cierra')" para="cierraC" error="cierra" requerido><x-ui.input id="cierraC" type="datetime-local" wire:model="cierra" /></x-ui.field>
                </div>
            </div>
        </x-ui.card>
        <div class="flex flex-col gap-5">
            <x-ui.card :titulo="__('Preguntas')">
                <div class="flex flex-col gap-4">
                    <x-ui.field :etiqueta="__('Banco')" para="bancoC" error="banco" requerido><x-ui.select id="bancoC" wire:model="banco"><option value="">{{ __('Elija…') }}</option>@foreach ($bancos as $b)<option value="{{ $b->id }}">{{ $b->name }} ({{ $b->preguntas }})</option>@endforeach</x-ui.select></x-ui.field>
                    <div class="grid grid-cols-2 gap-4">
                        <x-ui.field :etiqueta="__('Cantidad al azar')" para="cantidad" error="cantidad"><x-ui.input id="cantidad" type="number" wire:model="cantidad" /></x-ui.field>
                        <x-ui.field :etiqueta="__('Tema (opcional)')" para="temaC"><x-ui.input id="temaC" wire:model="tema" /></x-ui.field>
                    </div>
                </div>
            </x-ui.card>
            <x-ui.card :titulo="__('Intento')">
                <div class="grid grid-cols-2 gap-4">
                    <x-ui.field :etiqueta="__('Minutos')" para="minutos" error="minutos"><x-ui.input id="minutos" type="number" wire:model="minutos" /></x-ui.field>
                    <x-ui.field :etiqueta="__('Intentos')" para="intentosC" error="intentos"><x-ui.input id="intentosC" type="number" wire:model="intentos" /></x-ui.field>
                    <x-ui.field class="col-span-2" :etiqueta="__('Navegación')" para="navegacion"><x-ui.select id="navegacion" wire:model="navegacion"><option value="free">{{ __('Libre') }}</option><option value="linear">{{ __('Una a una (sin volver)') }}</option></x-ui.select></x-ui.field>
                </div>
            </x-ui.card>
            <div class="flex justify-end gap-2">
                <x-ui.button variante="secundario" :href="route('secciones.actividades', ['seccion' => $seccion])">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit">{{ __('Crear cuestionario') }}</x-ui.button>
            </div>
        </div>
    </form>
</div>
