{{-- Crear o editar un área de asistencia (RF26, ADR-055). En página: el mapa necesita espacio para dibujar. --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado
        :titulo="$areaId ? __('Editar :n', ['n' => $nombre]) : __('Nueva área')"
        :subtitulo="__('Marque el área sobre el mapa. Vale para todas las clases presenciales: el QR se acepta si el estudiante está dentro de cualquier área activa.')"
    />

    <x-ui.formulario accion="guardar" :volver="route('areas-asistencia')" :enviar="$areaId ? __('Guardar cambios') : __('Crear área')">
        <div class="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)] lg:items-start">
            {{-- Mapa (ADR-055). wire:ignore solo en el lienzo: Livewire no debe tocar el DOM de Leaflet al re-renderizar. --}}
            <x-ui.card class="lg:sticky lg:top-4">
                {{-- Los botones de Deshacer y Limpiar llaman al mapa: viven dentro de su x-data, pero los textos que cambian con la forma se
                     dibujan en el servidor, porque un x-show dentro de wire:ignore no se vuelve a evaluar. --}}
                <div x-data="mapaArea(@js($centro))" x-init="iniciar()" class="flex flex-col gap-3">
                    <div wire:ignore><div x-ref="mapa" class="h-80 w-full rounded-lg border border-borde bg-zinc-50 sm:h-[28rem] lg:h-[32rem]" aria-label="{{ __('Mapa del área') }}"></div></div>
                    @if ($tipo === 'polygon')
                        <p class="flex items-start gap-2 text-sm text-texto-suave">
                            <x-ui.icon name="information-circle" class="mt-0.5 size-4 shrink-0" />
                            {{ __('Haga clic en el mapa para marcar cada vértice, siguiendo el contorno. Deje unos metros de margen alrededor del edificio por la imprecisión del GPS.') }}
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge :color="count($vertices) >= 3 ? 'verde' : 'marca'">{{ trans_choice('{0} Sin vértices|{1} 1 vértice|[2,*] :count vértices', count($vertices)) }}</x-ui.badge>
                            <x-ui.button tamano="sm" variante="secundario" x-on:click="deshacer()" :disabled="$vertices === []">{{ __('Deshacer') }}</x-ui.button>
                            <x-ui.button tamano="sm" variante="fantasma" x-on:click="limpiar()" :disabled="$vertices === []">{{ __('Limpiar') }}</x-ui.button>
                        </div>
                    @else
                        <p class="flex items-start gap-2 text-sm text-texto-suave">
                            <x-ui.icon name="information-circle" class="mt-0.5 size-4 shrink-0" />
                            {{ __('Haga clic en el mapa para poner el centro; el círculo usa el radio del formulario.') }}
                        </p>
                    @endif
                </div>
                @error('vertices')<x-ui.alert class="mt-3" tipo="error">{{ $message }}</x-ui.alert>@enderror
                @error('vertices.*')<x-ui.alert class="mt-3" tipo="error">{{ $message }}</x-ui.alert>@enderror
            </x-ui.card>

            <div class="flex flex-col gap-5">
                <x-ui.card :titulo="__('Datos del área')">
                    <div class="flex flex-col gap-4">
                        <x-ui.field :etiqueta="__('Nombre')" para="nombreArea" error="nombre" requerido :ayuda="__('Solo sirve para reconocerla en la lista, como «Edificio de Ingeniería».')">
                            <x-ui.input id="nombreArea" wire:model.blur="nombre" />
                        </x-ui.field>
                        <fieldset>
                            <legend class="mb-2 text-sm font-medium text-texto">{{ __('Forma') }}</legend>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach (['circle' => [__('Centro y radio'), __('Un punto y una distancia')], 'polygon' => [__('Polígono'), __('Sigue el contorno')]] as $valor => [$titulo, $detalle])
                                    <label wire:key="forma-{{ $valor }}" @class(['flex cursor-pointer flex-col gap-0.5 rounded-lg p-3 ring-1 transition', 'bg-marca-50 ring-2 ring-marca-600' => $tipo === $valor, 'ring-borde hover:bg-zinc-50' => $tipo !== $valor])>
                                        <input type="radio" wire:model.live="tipo" value="{{ $valor }}" class="sr-only">
                                        <span class="text-sm font-medium text-texto">{{ $titulo }}</span>
                                        <span class="text-xs text-texto-suave">{{ $detalle }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                </x-ui.card>

                @if ($tipo !== 'polygon')
                    <x-ui.card :titulo="__('Centro y radio')" :descripcion="__('Póngalo con un clic en el mapa, escríbalo o tómelo con el GPS de pie en el centro del aula o del edificio.')">
                        <div class="flex flex-col gap-4">
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                                <x-ui.field :etiqueta="__('Latitud')" para="latitud" error="latitud" requerido><x-ui.input id="latitud" wire:model="latitud" placeholder="13.4393" class="font-mono" /></x-ui.field>
                                <x-ui.field :etiqueta="__('Longitud')" para="longitud" error="longitud" requerido><x-ui.input id="longitud" wire:model="longitud" placeholder="-88.1597" class="font-mono" /></x-ui.field>
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
                        </div>
                    </x-ui.card>
                @endif
            </div>
        </div>
    </x-ui.formulario>
</div>
