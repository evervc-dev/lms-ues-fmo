{{-- Crear tarea (RF15, maqueta 19). --}}
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />
    <form wire:submit="guardar" class="grid gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <x-ui.card :titulo="__('Nueva tarea')">
            <div class="flex flex-col gap-4">
                <x-ui.field :etiqueta="__('Título')" para="titulo" error="titulo" requerido><x-ui.input id="titulo" wire:model="titulo" /></x-ui.field>
                <x-ui.field :etiqueta="__('Instrucciones')" para="instrucciones" error="instrucciones"><x-ui.textarea id="instrucciones" wire:model="instrucciones" rows="6" /></x-ui.field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field :etiqueta="__('Cómputo')" para="componente" error="componente" requerido>
                        <x-ui.select id="componente" wire:model="componente"><option value="">{{ __('Elija…') }}</option>@foreach ($computos as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ rtrim(rtrim(number_format((float) $c->weight_percent, 2, '.', ''), '0'), '.') }} %)</option>@endforeach</x-ui.select>
                    </x-ui.field>
                    <x-ui.field :etiqueta="__('Peso dentro del cómputo (%)')" para="peso" error="peso" requerido><x-ui.input id="peso" type="number" step="0.5" wire:model="peso" /></x-ui.field>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field :etiqueta="__('Abre')" para="abre" error="abre" requerido><x-ui.input id="abre" type="datetime-local" wire:model="abre" /></x-ui.field>
                    <x-ui.field :etiqueta="__('Cierra')" para="cierra" error="cierra" requerido><x-ui.input id="cierra" type="datetime-local" wire:model="cierra" /></x-ui.field>
                </div>
            </div>
        </x-ui.card>

        <div class="flex flex-col gap-5">
            <x-ui.card :titulo="__('Entrega')">
                <div class="flex flex-col gap-4">
                    <x-ui.field :etiqueta="__('Política de entrega')" para="politica" error="politica">
                        <x-ui.select id="politica" wire:model.live="politica">@foreach ($politicas as $v => $e)<option value="{{ $v }}">{{ __($e) }}</option>@endforeach</x-ui.select>
                    </x-ui.field>
                    @if ($politica !== 'strict')
                        <x-ui.field :etiqueta="__('Fecha límite de gracia')" para="gracia" error="gracia" requerido><x-ui.input id="gracia" type="datetime-local" wire:model="gracia" /></x-ui.field>
                    @endif
                    @if ($politica === 'grace_penalty')
                        <x-ui.field :etiqueta="__('Penalización (%)')" para="penalizacion" error="penalizacion"><x-ui.input id="penalizacion" type="number" wire:model="penalizacion" /></x-ui.field>
                    @endif
                    <x-ui.field :etiqueta="__('Extensiones permitidas')" para="extensiones" :ayuda="__('Separadas por coma.')"><x-ui.input id="extensiones" wire:model="extensiones" /></x-ui.field>
                    <div class="grid grid-cols-2 gap-4">
                        <x-ui.field :etiqueta="__('Peso máx. (MB)')" para="mb" error="mb"><x-ui.input id="mb" type="number" wire:model="mb" /></x-ui.field>
                        <x-ui.field :etiqueta="__('Intentos')" para="intentos" error="intentos"><x-ui.input id="intentos" type="number" wire:model="intentos" /></x-ui.field>
                    </div>
                </div>
            </x-ui.card>
            <x-ui.card :titulo="__('Calificación')">
                <div class="flex flex-col gap-3 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="conRubrica" class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600"> {{ __('Calificar con rúbrica') }}</label>
                    @if ($conRubrica)
                        @include('modules.evaluaciones.partials.criterios-rubrica', ['modelo' => 'criterios', 'criterios' => $criterios])
                    @else
                        <p class="text-xs text-texto-suave">{{ __('Sin rúbrica, la entrega se califica con una nota directa de 0 a 10. Úsela en reportes, exposiciones o proyectos.') }}</p>
                    @endif
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="anonima" class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600"> {{ __('Calificación anónima hasta publicar las notas') }}</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="grupal" class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600"> {{ __('Tarea grupal') }}</label>
                </div>
            </x-ui.card>
            <div class="flex justify-end gap-2">
                <x-ui.button variante="secundario" :href="route('secciones.actividades', ['seccion' => $seccion])">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit">{{ __('Crear tarea') }}</x-ui.button>
            </div>
        </div>
    </form>
</div>
