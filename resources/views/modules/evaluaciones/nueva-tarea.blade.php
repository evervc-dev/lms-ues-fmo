{{-- Crear o editar tarea (RF15, maqueta 19). --}}
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />
    <form wire:submit="guardar" class="flex flex-col gap-5 [&_input]:scroll-mb-24 [&_select]:scroll-mb-24 [&_textarea]:scroll-mb-24">
        @if ($entregasCount > 0)
            <x-ui.alert tipo="aviso" :titulo="__('Tarea con entregas registradas')">
                {{ trans_choice('{1} Ya hay 1 entrega de estudiante registrada.|[2,*] Ya hay :count entregas de estudiantes registradas.', $entregasCount) }}
                {{ __('Si modifica las fechas o los archivos permitidos, tenga en cuenta que ya existen entregas realizadas.') }}
            </x-ui.alert>
        @endif

        <div class="grid gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:items-start">
            @include('modules.evaluaciones.partials.datos-actividad', ['sufijo' => '', 'titulo' => $editando ? __('Editar tarea') : __('Nueva tarea')])

            <div class="flex flex-col gap-5">
                <x-ui.card :titulo="__('Entrega')" :descripcion="__('Qué puede subir el estudiante y qué pasa si llega tarde.')">
                    <div class="flex flex-col gap-4">
                        {{-- La política en tarjetas, cada una con lo que pasa con la entrega tardía (ADR-008). --}}
                        <fieldset>
                            <legend class="mb-2 text-sm font-medium text-zinc-800">{{ __('Entregas tardías') }}</legend>
                            <div class="flex flex-col gap-2">
                                @foreach (['strict' => __('No se aceptan después del cierre.'), 'grace' => __('Se aceptan hasta la fecha de gracia, sin descuento.'), 'grace_penalty' => __('Se aceptan hasta la fecha de gracia, con un descuento en la nota.')] as $valor => $efecto)
                                    <label wire:key="politica-{{ $valor }}" @class(['flex cursor-pointer items-start gap-3 rounded-lg p-3 ring-1 transition', 'bg-marca-50 ring-2 ring-marca-600' => $politica === $valor, 'ring-borde hover:bg-zinc-50' => $politica !== $valor])>
                                        <input type="radio" wire:model.live="politica" value="{{ $valor }}" class="mt-0.5 text-marca-600 focus:ring-marca-600">
                                        <span class="flex flex-col"><span class="text-sm font-medium text-texto">{{ __($politicas[$valor]) }}</span><span class="text-xs text-texto-suave">{{ $efecto }}</span></span>
                                    </label>
                                @endforeach
                            </div>
                            @error('politica')<p class="mt-1 text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                        </fieldset>
                        @if ($politica !== 'strict')
                            <div @class(['grid gap-4', 'sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]' => $politica === 'grace_penalty'])>
                                <x-ui.field :etiqueta="__('Fecha límite de gracia')" para="gracia" error="gracia" requerido><x-ui.input id="gracia" type="datetime-local" wire:model="gracia" /></x-ui.field>
                                @if ($politica === 'grace_penalty')
                                    <x-ui.field :etiqueta="__('Descuento (%)')" para="penalizacion" error="penalizacion"><x-ui.input id="penalizacion" type="number" min="0" max="100" wire:model="penalizacion" /></x-ui.field>
                                @endif
                            </div>
                        @endif
                        {{-- Se eligen del catálogo, no se escriben: una extensión mal tecleada rechazaba entregas correctas. --}}
                        <x-ui.field :etiqueta="__('Extensiones permitidas')" para="extensiones" error="extensiones" :ayuda="__('Marque las que acepta. Al menos una.')" requerido>
                            <div class="flex flex-col gap-3 rounded-lg p-3 ring-1 ring-borde" id="extensiones">
                                @foreach ($catalogoExtensiones as $grupo => $exts)
                                    <div wire:key="ext-{{ $loop->index }}">
                                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __($grupo) }}</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($exts as $ext)
                                                <label @class(['inline-flex cursor-pointer items-center gap-1.5 rounded-full px-2.5 py-1 font-mono text-xs ring-1 transition', 'bg-marca-50 text-marca-800 ring-marca-300' => in_array($ext, $extensiones, true), 'text-texto ring-borde hover:bg-zinc-50' => ! in_array($ext, $extensiones, true)])>
                                                    <input type="checkbox" value="{{ $ext }}" wire:model.live="extensiones" class="size-3.5 rounded border-borde text-marca-600 focus:ring-marca-600">
                                                    {{ $ext }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </x-ui.field>
                        <div class="grid grid-cols-2 gap-4">
                            <x-ui.field :etiqueta="__('Peso máximo (MB)')" para="mb" error="mb"><x-ui.input id="mb" type="number" min="1" wire:model="mb" /></x-ui.field>
                            <x-ui.field :etiqueta="__('Intentos')" para="intentos" error="intentos" :ayuda="__('Cuántas veces puede entregar.')"><x-ui.input id="intentos" type="number" min="1" wire:model="intentos" /></x-ui.field>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card :titulo="__('Calificación')">
                    <div class="flex flex-col gap-4 text-sm">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" wire:model.live="conRubrica" @disabled(! $rubricaEditable) class="mt-0.5 size-4 rounded border-borde text-marca-600 focus:ring-marca-600 disabled:opacity-50">
                            <span class="flex flex-col">
                                <span class="font-medium text-texto">{{ __('Calificar con rúbrica') }}</span>
                                <span class="text-xs text-texto-suave">{{ __('La nota es la suma de los puntos de cada criterio. Sin rúbrica, se pone una nota directa de 0 a 10.') }}</span>
                                @if (! $rubricaEditable)
                                    <span class="text-xs font-medium text-amber-600">{{ __('La tarea ya tiene calificaciones: la rúbrica no se puede modificar.') }}</span>
                                @endif
                            </span>
                        </label>
                        @if ($conRubrica)
                            @include('modules.evaluaciones.partials.criterios-rubrica', ['modelo' => 'criterios', 'criterios' => $criterios])
                        @endif
                        <label class="flex items-start gap-3">
                            <input type="checkbox" wire:model="anonima" class="mt-0.5 size-4 rounded border-borde text-marca-600 focus:ring-marca-600">
                            <span class="flex flex-col"><span class="font-medium text-texto">{{ __('Calificación anónima') }}</span><span class="text-xs text-texto-suave">{{ __('Los nombres aparecen al publicar las notas.') }}</span></span>
                        </label>
                        <label class="flex items-start gap-3">
                            <input type="checkbox" wire:model="grupal" @disabled($modalidadBloqueada) class="mt-0.5 size-4 rounded border-borde text-marca-600 focus:ring-marca-600 disabled:opacity-50">
                            <span class="flex flex-col">
                                <span class="font-medium text-texto">{{ __('Tarea grupal') }}</span>
                                <span class="text-xs text-texto-suave">{{ __('Usted arma los equipos en la pantalla de entregas; la nota se copia a cada integrante.') }}</span>
                                @if ($modalidadBloqueada)
                                    <span class="text-xs font-medium text-amber-600">{{ __('No se puede cambiar la modalidad porque ya existen entregas o equipos asignados.') }}</span>
                                @endif
                            </span>
                        </label>
                    </div>
                </x-ui.card>
            </div>
        </div>

        @include('modules.evaluaciones.partials.acciones-formulario', ['volver' => $editando ? route('secciones.tareas.show', ['seccion' => $seccion, 'tarea' => $editandoUlid]) : route('secciones.actividades', ['seccion' => $seccion]), 'enviar' => $editando ? __('Guardar cambios') : __('Crear tarea')])
    </form>
</div>
