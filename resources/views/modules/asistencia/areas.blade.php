{{-- Áreas de asistencia (RF26, maqueta 34). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Áreas de asistencia')" :subtitulo="__('El estudiante debe estar dentro de un área activa para marcar con QR. Defínala por centro y radio o por polígono, con margen por la imprecisión del GPS.')">
        <x-slot:acciones><x-ui.button icono="plus" wire:click="nueva">{{ __('Nueva área') }}</x-ui.button></x-slot:acciones>
    </x-ui.encabezado>
    {{-- QA sesión 4: se leía como un área por carrera o materia. Es la geocerca del campus (RF26). --}}
    <x-ui.alert tipo="info" :titulo="__('Las áreas valen para todas las clases presenciales')">
        {{ __('No se asignan a una carrera, asignatura ni docente: el QR de cualquier sesión presencial se acepta si el estudiante está dentro de cualquier área activa. El nombre solo sirve para reconocerla en esta lista. Para dejar de aceptar un lugar, desactive su área.') }}
    </x-ui.alert>
    <x-ui.table ajustar :vacio="__('No hay áreas configuradas.')">
        <x-slot:cabecera><th>{{ __('Área') }}</th><th>{{ __('Centro') }}</th><th>{{ __('Forma') }}</th><th>{{ __('Estado') }}</th><th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
        @foreach ($areas as $a)
            <tr wire:key="area-{{ $a->id }}">
                <td class="font-medium">{{ $a->name }}</td>
                <td class="font-mono text-xs">{{ number_format((float) $a->center_lat, 5) }}, {{ number_format((float) $a->center_lng, 5) }}</td>
                <td>{{ $a->area_type === 'polygon' ? __('Polígono · :n vértices', ['n' => count(json_decode((string) $a->polygon, true) ?? [])]) : __('Círculo · :m m', ['m' => $a->radius_m]) }}</td>
                <td><x-ui.badge :color="$a->is_active ? 'verde' : 'gris'">{{ $a->is_active ? __('Activa') : __('Inactiva') }}</x-ui.badge></td>
                <td class="text-right whitespace-nowrap">
                    <x-ui.button tamano="sm" variante="fantasma" wire:click="editar({{ $a->id }})">{{ __('Editar') }}</x-ui.button>
                    <x-ui.button tamano="sm" variante="secundario" wire:click="alternar({{ $a->id }})">{{ $a->is_active ? __('Desactivar') : __('Activar') }}</x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>

    <x-ui.modal nombre="area" ancho="lg" :titulo="$areaId ? __('Editar área') : __('Nueva área')">
        <form wire:submit="guardar" id="form-area" class="flex flex-col gap-4">
            <x-ui.field :etiqueta="__('Nombre')" para="nombreArea" error="nombre" requerido><x-ui.input id="nombreArea" wire:model="nombre" /></x-ui.field>
            <fieldset class="flex flex-wrap gap-4">
                <legend class="mb-1 text-sm font-medium text-texto">{{ __('Forma') }}</legend>
                <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model.live="tipo" value="circle" class="text-marca-600 focus:ring-marca-600"> {{ __('Centro y radio') }}</label>
                <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model.live="tipo" value="polygon" class="text-marca-600 focus:ring-marca-600"> {{ __('Polígono') }}</label>
            </fieldset>

            {{-- Mapa (ADR-055). wire:ignore: Livewire no debe tocar el DOM de Leaflet al re-renderizar. --}}
            <div wire:ignore x-data="mapaArea(@js($centro))" x-on:abrir-modal.window="abrir($event.detail)" class="flex flex-col gap-2">
                <div x-ref="mapa" class="h-72 w-full rounded-lg border border-borde bg-zinc-50" aria-label="{{ __('Mapa del área') }}"></div>
                <p class="text-xs text-texto-suave" x-show="$wire.tipo !== 'polygon'">{{ __('Haga clic en el mapa para poner el centro; el círculo usa el radio de abajo.') }}</p>
                <div class="flex flex-wrap items-center gap-3" x-show="$wire.tipo === 'polygon'" x-cloak>
                    <p class="text-xs text-texto-suave">{{ __('Haga clic en el mapa para marcar cada vértice, siguiendo el contorno. Dibújelo con unos metros de margen alrededor del edificio por la imprecisión del GPS.') }}</p>
                    <span class="text-xs font-medium text-texto" x-text="@js(__('Vértices: ')) + ($wire.vertices ?? []).length"></span>
                    <x-ui.button tamano="sm" variante="secundario" x-on:click="deshacer()">{{ __('Deshacer') }}</x-ui.button>
                    <x-ui.button tamano="sm" variante="fantasma" x-on:click="limpiar()">{{ __('Limpiar') }}</x-ui.button>
                </div>
            </div>
            @error('vertices')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            @error('vertices.*')<p class="text-sm text-red-700">{{ $message }}</p>@enderror

            @if ($tipo !== 'polygon')
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field :etiqueta="__('Latitud')" para="latitud" error="latitud" requerido><x-ui.input id="latitud" wire:model="latitud" placeholder="13.4393" /></x-ui.field>
                <x-ui.field :etiqueta="__('Longitud')" para="longitud" error="longitud" requerido><x-ui.input id="longitud" wire:model="longitud" placeholder="-88.1597" /></x-ui.field>
            </div>
            {{-- Centro con el GPS de este dispositivo: conviene tomarlo de pie en el centro del aula o del edificio. --}}
            <div class="flex flex-wrap items-center gap-3" x-data="{
                ubicando: false, mensaje: '', error: false,
                textos: @js(['sinGps' => __('Este navegador no da la ubicación.'), 'buscando' => __('Obteniendo su ubicación…'),
                    'precision' => __('Precisión del GPS: ±:n m. El radio debería cubrir ese margen.'),
                    'fallo' => __('No se pudo obtener su ubicación. Permita el acceso a la ubicación en su navegador.')]),
                usar() {
                    if (!('geolocation' in navigator)) { this.error = true; this.mensaje = this.textos.sinGps; return; }
                    this.ubicando = true; this.error = false; this.mensaje = this.textos.buscando;
                    navigator.geolocation.getCurrentPosition((p) => {
                        this.$wire.set('latitud', p.coords.latitude.toFixed(7));
                        this.$wire.set('longitud', p.coords.longitude.toFixed(7));
                        this.ubicando = false;
                        this.mensaje = this.textos.precision.replace(':n', Math.round(p.coords.accuracy));
                    }, () => { this.ubicando = false; this.error = true; this.mensaje = this.textos.fallo; },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
                },
            }">
                <x-ui.button variante="secundario" tamano="sm" icono="map-pin" x-bind:disabled="ubicando" x-on:click="usar()">{{ __('Usar mi ubicación') }}</x-ui.button>
                <p class="text-xs" :class="error ? 'text-red-700' : 'text-texto-suave'" x-show="mensaje" x-text="mensaje"></p>
            </div>
            <x-ui.field :etiqueta="__('Radio (metros)')" para="radio" error="radio" :ayuda="__('Entre 10 y 5 000 m, con margen para el GPS.')" requerido><x-ui.input id="radio" type="number" wire:model.live.debounce.400ms="radio" /></x-ui.field>
            @endif
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'area')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-area">{{ __('Guardar') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
