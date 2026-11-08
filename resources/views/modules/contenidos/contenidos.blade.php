{{-- Vista de sección › Contenidos (RF11, RF18, maquetas 06 y 07). --}}
@php($iconos = ['file' => 'document-chart-bar', 'page' => 'book-open', 'link' => 'arrow-up-tray', 'video' => 'presentation-chart-line', 'folder' => 'rectangle-stack'])
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex w-full max-w-xl flex-wrap items-center gap-2">
            <x-ui.search class="min-w-0 flex-1" wire:model.live.debounce.300ms="buscar" :placeholder="__('Buscar material por nombre…')" />
            <x-ui.select class="w-44" wire:model.live="tipo" :aria-label="__('Filtrar por tipo')">
                <option value="">{{ __('Todos los tipos') }}</option>
                @foreach ($tipos as $valor => $etiqueta)<option value="{{ $valor }}">{{ __($etiqueta) }}</option>@endforeach
            </x-ui.select>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($gestiona)
                <x-ui.button variante="fantasma" icono="x-circle" :href="route('secciones.papelera', ['seccion' => $seccion])">{{ __('Papelera') }}</x-ui.button>
                <x-ui.button variante="secundario" icono="book-open" wire:click="abrirDescripcion">{{ __('Descripción de la materia') }}</x-ui.button>
                <x-ui.button variante="secundario" icono="arrow-up-tray" :href="route('secciones.importar', ['seccion' => $seccion])">{{ __('Importar de un período anterior') }}</x-ui.button>
                <x-ui.button icono="plus" x-on:click="$dispatch('abrir-modal', 'nueva-unidad')">{{ __('Nueva unidad') }}</x-ui.button>
            @endif
        </div>
    </div>

    @if ($esEstudiante)
        <x-ui.card><p class="mb-2 text-sm font-medium text-texto">{{ __('Su avance en la materia') }}</p><x-ui.progress :valor="$avance" :etiqueta="__('Avance')" /></x-ui.card>
    @endif

    @if ($unidades->isEmpty())
        <x-ui.card><x-ui.empty-state icono="book-open" :titulo="$buscar || $tipo ? __('No hay materiales con ese criterio') : __('Aún no hay materiales')">
            {{ $buscar || $tipo ? __('Pruebe con otro nombre o quite el filtro de tipo.') : ($gestiona ? __('Cree la primera unidad o importe el contenido de un período anterior.') : __('Su docente todavía no publicó materiales.')) }}
        </x-ui.empty-state></x-ui.card>
    @else
        <x-ui.accordion>
            @foreach ($unidades as $u)
                <x-ui.accordion.item :titulo="$u->title" :abierto="$loop->first || $buscar !== ''" wire:key="unidad-{{ $u->id }}">
                    @if ($esEstudiante && $u->recursos->isNotEmpty())
                        {{-- Avance de la unidad (RF18): recursos completados sobre los publicados. --}}
                        <x-slot:resumen>
                            <x-ui.progress :valor="100 * $u->recursos->where('completado', true)->count() / $u->recursos->count()" :etiqueta="__('Avance de :u', ['u' => $u->title])" />
                        </x-slot:resumen>
                    @endif
                    <ul class="flex flex-col divide-y divide-borde">
                        @forelse ($u->recursos as $r)
                            <li class="flex items-center gap-3 py-2.5" wire:key="recurso-{{ $r->id }}">
                                <span class="rounded-lg bg-marca-50 p-2 text-marca-600"><x-ui.icon :name="$iconos[$r->resource_type] ?? 'book-open'" class="size-4" /></span>
                                <a href="{{ route('secciones.recurso', ['seccion' => $seccion, 'recurso' => $r->ulid]) }}" wire:navigate class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-texto hover:text-marca-700">{{ $r->title }}</span>
                                    <span class="text-xs text-texto-suave">{{ __($tipos[$r->resource_type] ?? $r->resource_type) }}{{ $r->description ? ' · '.\Illuminate\Support\Str::limit($r->description, 60) : '' }}</span>
                                </a>
                                @if ($esEstudiante)
                                    @if ($r->completado)
                                        <x-ui.badge color="verde"><x-ui.icon name="check-circle" class="size-3.5" /> {{ __('Completado') }}</x-ui.badge>
                                    @elseif ($r->completion_mode === 'manual')
                                        <x-ui.button tamano="sm" variante="secundario" wire:click="marcarLeido({{ $r->id }})">{{ __('Ya lo leí') }}</x-ui.button>
                                    @endif
                                @endif
                                @if ($gestiona)
                                    <button type="button" wire:click="eliminar({{ $r->id }})" wire:confirm="{{ __('¿Mover este recurso a la papelera?') }}" class="rounded p-1 text-texto-suave hover:text-red-700" aria-label="{{ __('Eliminar recurso') }}"><x-ui.icon name="x-mark" class="size-4" /></button>
                                @endif
                            </li>
                        @empty
                            <li class="py-2 text-sm text-texto-suave">{{ __('Unidad sin materiales.') }}</li>
                        @endforelse
                    </ul>
                    @if ($gestiona)
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <x-ui.button tamano="sm" variante="fantasma" icono="plus" :href="route('secciones.recursos.crear', ['seccion' => $seccion, 'unidad' => $u->id])">{{ __('Agregar recurso') }}</x-ui.button>
                            <x-ui.button tamano="sm" variante="fantasma" icono="x-circle" wire:click="eliminarUnidad({{ $u->id }})" wire:confirm="{{ __('¿Mover esta unidad y sus materiales a la papelera?') }}">{{ __('Eliminar unidad') }}</x-ui.button>
                        </div>
                    @endif
                </x-ui.accordion.item>
            @endforeach
        </x-ui.accordion>
    @endif

    @if ($gestiona)
        <x-ui.modal nombre="nueva-unidad" :titulo="__('Nueva unidad')">
            <form wire:submit="crearUnidad" id="form-unidad"><x-ui.field :etiqueta="__('Título')" para="tituloUnidad" error="tituloUnidad" :ayuda="__('Por semana, cómputo o tema. Ej.: Unidad 5 · Recursividad')" requerido><x-ui.input id="tituloUnidad" wire:model="tituloUnidad" /></x-ui.field></form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'nueva-unidad')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-unidad">{{ __('Crear unidad') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>

        <x-ui.modal nombre="descripcion-materia" :titulo="__('Descripción de la materia')">
            <form wire:submit="guardarDescripcion" id="form-descripcion">
                <x-ui.field :etiqueta="__('Descripción')" para="descripcion" error="descripcion" :ayuda="__('La ven los estudiantes al elegir la materia en «Inscripción de materias». Texto plano, hasta 2000 caracteres. Si otros docentes dictan la materia, la comparten.')">
                    <x-ui.textarea id="descripcion" rows="8" maxlength="2000" wire:model="descripcion" />
                </x-ui.field>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'descripcion-materia')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-descripcion">{{ __('Guardar') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif
</div>
