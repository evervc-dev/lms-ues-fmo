{{-- Crear o editar cuestionario (RF16). --}}
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />
    <form wire:submit="guardar" class="flex flex-col gap-5">
        @if ($intentosCount > 0)
            <x-ui.alert tipo="aviso" :titulo="__('Cuestionario con intentos registrados')">
                {{ trans_choice('{1} Ya hay 1 intento de estudiante registrado.|[2,*] Ya hay :count intentos de estudiantes registrados.', $intentosCount) }}
                {{ __('Si modifica las preguntas o el banco, tenga en cuenta que los nuevos intentos tendrán preguntas distintas a los intentos ya completados.') }}
            </x-ui.alert>
        @endif
        <div class="grid gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:items-start">
            @include('modules.evaluaciones.partials.datos-actividad', ['sufijo' => 'C', 'titulo' => $editando ? __('Editar cuestionario') : __('Nuevo cuestionario')])

            <div class="flex flex-col gap-5">
                <x-ui.card :titulo="__('Preguntas')" :descripcion="__('Cada estudiante recibe las suyas, sacadas al azar del banco.')">
                    <div class="flex flex-col gap-4">
                        <x-ui.field :etiqueta="__('Banco')" para="bancoC" error="banco" requerido>
                            <x-ui.select id="bancoC" wire:model.live="banco"><option value="">{{ __('Elija…') }}</option>@foreach ($bancos as $b)<option value="{{ $b->id }}">{{ $b->name }} ({{ trans_choice('{1} 1 pregunta|[0,*] :count preguntas', $b->preguntas) }})</option>@endforeach</x-ui.select>
                        </x-ui.field>
                        <div class="grid grid-cols-2 gap-4">
                            <x-ui.field :etiqueta="__('Cantidad al azar')" para="cantidad" error="cantidad" :ayuda="$banco ? trans_choice('{1} :count pregunta disponible con este filtro.|[0,*] :count preguntas disponibles con este filtro (0 = todas).', $disponibles) : __('0 = todas las del banco.')"><x-ui.input id="cantidad" type="number" min="0" wire:model="cantidad" /></x-ui.field>
                            {{-- El tema se elige de los que existen en el banco: escribirlo dejaba el filtro en 0 preguntas. --}}
                            <x-ui.field :etiqueta="__('Tema (opcional)')" para="temaC" :ayuda="$banco && $temas->isEmpty() ? __('Este banco no tiene temas.') : null">
                                <x-ui.select id="temaC" wire:model.live="tema" :disabled="! $banco || $temas->isEmpty()">
                                    <option value="">{{ __('Todos los temas') }}</option>
                                    @foreach ($temas as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                                </x-ui.select>
                            </x-ui.field>
                        </div>
                    </div>
                </x-ui.card>
                <x-ui.card :titulo="__('Intento')" :descripcion="__('El tiempo lo lleva el servidor: al llegar a cero, el intento se envía solo.')">
                    <div class="flex flex-col gap-4">
                        <div class="grid grid-cols-2 gap-4">
                            <x-ui.field :etiqueta="__('Minutos')" para="minutos" error="minutos"><x-ui.input id="minutos" type="number" min="1" wire:model="minutos" /></x-ui.field>
                            <x-ui.field :etiqueta="__('Intentos')" para="intentosC" error="intentos" :ayuda="__('Vale el mejor.')"><x-ui.input id="intentosC" type="number" min="1" wire:model="intentos" /></x-ui.field>
                        </div>
                        <fieldset>
                            <legend class="mb-2 text-sm font-medium text-zinc-800">{{ __('Navegación') }}</legend>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach (['free' => [__('Libre'), __('Puede ir y volver entre preguntas.')], 'linear' => [__('Una a una'), __('Avanza sin poder volver a las anteriores.')]] as $valor => [$nombreNav, $detalleNav])
                                    <label wire:key="nav-{{ $valor }}" @class(['flex cursor-pointer flex-col gap-0.5 rounded-lg p-3 ring-1 transition', 'bg-marca-50 ring-2 ring-marca-600' => $navegacion === $valor, 'ring-borde hover:bg-zinc-50' => $navegacion !== $valor])>
                                        <input type="radio" id="navegacion-{{ $valor }}" wire:model.live="navegacion" value="{{ $valor }}" class="sr-only">
                                        <span class="text-sm font-medium text-texto">{{ $nombreNav }}</span>
                                        <span class="text-xs text-texto-suave">{{ $detalleNav }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                </x-ui.card>
            </div>
        </div>

        @include('modules.evaluaciones.partials.acciones-formulario', ['volver' => route('secciones.actividades', ['seccion' => $seccion]), 'enviar' => $editando ? __('Guardar cambios') : __('Crear cuestionario')])
    </form>
</div>
