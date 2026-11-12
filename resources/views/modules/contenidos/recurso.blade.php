{{-- Ver recurso (RF11, RF12). --}}
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    @if ($esEstudiante)
        {{-- RF13 (CP-RF13-02): cuenta los segundos con la pestaña visible y los envía cada :latido s, al ocultar la pestaña
             y antes de salir con wire:navigate. Sin interfaz. --}}
        <div class="hidden" aria-hidden="true"
            x-data="{
                visibles: 0,
                reloj: null,
                init() { this.reloj = setInterval(() => { if (document.visibilityState === 'visible' && ++this.visibles >= {{ $latido }}) this.enviar() }, 1000) },
                destroy() { clearInterval(this.reloj) },
                enviar() { if (this.visibles > 0) { this.$wire.latido(this.visibles); this.visibles = 0 } },
            }"
            x-on:visibilitychange.document="document.visibilityState === 'hidden' && enviar()"
            x-on:livewire:navigating.document="enviar()"></div>
    @endif

    <x-ui.card :titulo="$recurso->title" :descripcion="$recurso->unidad">
        <x-slot:acciones>
            @if ($esEstudiante)
                @if ($completado)
                    <x-ui.badge color="verde">{{ __('Completado') }}</x-ui.badge>
                @elseif ($recurso->completion_mode === 'manual')
                    <x-ui.button tamano="sm" wire:click="marcarLeido">{{ __('Ya lo leí') }}</x-ui.button>
                @endif
            @endif
        </x-slot:acciones>

        @if ($recurso->description)<p class="mb-4 text-sm text-texto-suave">{{ $recurso->description }}</p>@endif

        @if ($esEstudiante && ! $completado && $reglaEnPalabras)
            {{-- Cómo se completa, en palabras (RF18): el estudiante no tiene que adivinar qué le falta. --}}
            <p class="mb-4 flex items-start gap-2 rounded-lg bg-marca-50 p-3 text-sm text-texto">
                <x-ui.icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-marca-600" />
                <span>{{ $reglaEnPalabras }}</span>
            </p>
        @endif

        @switch($recurso->resource_type)
            @case('video')
                @if ($video)
                    <div class="aspect-video overflow-hidden rounded-lg bg-black"><iframe src="{{ $video }}" title="{{ $recurso->title }}" class="size-full" allow="accelerometer; encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>
                @else
                    <x-ui.button :href="$recurso->url" :navegar="false" target="_blank" rel="noopener" icono="presentation-chart-line">{{ __('Abrir el video') }}</x-ui.button>
                @endif
                @break
            @case('page')
                {{-- El HTML se saneó en el servidor al guardarlo (HTMLPurifier, perfil `contenidos`): aquí se muestra con su formato. --}}
                <div class="contenido-pagina max-w-prose text-sm leading-relaxed text-texto">{!! $recurso->html_content !!}</div>

                @if ($gestiona)
                    <form wire:submit="editarPagina" class="mt-4 flex flex-col gap-3 border-t border-borde pt-4">
                        <x-ui.field :etiqueta="__('Editar el contenido')" para="editor-pagina" error="texto" :ayuda="__('Dé formato con la barra. El HTML se sanea en el servidor al guardar.')">
                            {{-- wire:ignore: el editor vive en el navegador y Livewire no debe redibujar su HTML. --}}
                            <div wire:ignore x-data="editorPaginas('texto')" class="rounded-lg bg-white">
                                <div id="editor-pagina" x-ref="editor" class="min-h-48"></div>
                            </div>
                        </x-ui.field>
                        <div><x-ui.button type="submit" tamano="sm" variante="secundario">{{ __('Guardar la página') }}</x-ui.button></div>
                    </form>
                @endif
                @break
            @case('folder')
                {{-- Carpeta (RF11): su estructura original y la descarga completa en .zip, que se arma al vuelo. --}}
                @if ($archivos->isEmpty())
                    <p class="text-sm text-texto-suave">{{ __('Esta carpeta ya no tiene archivos disponibles.') }}</p>
                @else
                    <x-ui.button :href="route('secciones.recurso.zip', ['seccion' => $resumen['ulid'], 'recurso' => $recurso->ulid])" :navegar="false" icono="rectangle-stack">{{ __('Descargar todo (.zip)') }}</x-ui.button>
                    <div class="mt-3 flex flex-col gap-3">
                        @foreach ($arbol as $directorio => $contenido)
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ $directorio === '' ? __('Raíz de la carpeta') : $directorio }}</p>
                                <ul class="divide-y divide-borde text-sm">
                                    @foreach ($contenido as $a)
                                        <li class="flex justify-between gap-3 py-2">
                                            <span class="min-w-0 truncate">{{ $a->name }}</span>
                                            <span class="shrink-0 text-texto-suave">{{ number_format(max($a->bytes / 1024, 1), 0) }} KB</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
                @break
            @case('link')
                <x-ui.button :href="$recurso->url" :navegar="false" target="_blank" rel="noopener" icono="arrow-up-tray">{{ __('Abrir el enlace') }}</x-ui.button>
                <p class="mt-2 break-all text-xs text-texto-suave">{{ $recurso->url }}</p>
                @break
            @default
                @php($esPdf = $recurso->file_path && \Illuminate\Support\Str::endsWith(mb_strtolower((string) $recurso->file_path), '.pdf'))
                @if ($recurso->file_path)
                    @php($archivoUrl = route('secciones.recurso.archivo', ['seccion' => $resumen['ulid'], 'recurso' => $recurso->ulid]))
                    @if ($esPdf && $recurso->pdf_mode === 'viewer')
                        {{-- Visor incrustado: el archivo se sirve en línea desde el disco privado (RF11). --}}
                        <div class="h-[70vh] overflow-hidden rounded-lg border border-borde bg-zinc-100">
                            <iframe src="{{ $archivoUrl }}" title="{{ $recurso->title }}" class="size-full" loading="lazy"></iframe>
                        </div>
                        <x-ui.button class="mt-3" variante="secundario" tamano="sm" :href="$archivoUrl" :navegar="false" icono="arrow-up-tray">{{ __('Abrir en una pestaña nueva') }}</x-ui.button>
                    @else
                        <x-ui.button :href="$archivoUrl" :navegar="false" icono="arrow-up-tray">{{ $esPdf ? __('Descargar el PDF') : __('Descargar') }}</x-ui.button>
                    @endif
                @endif

                @if ($gestiona && $recurso->resource_type === 'file')
                    <form wire:submit="cambiarModoPdf" class="mt-4 flex flex-wrap items-end gap-3 border-t border-borde pt-4">
                        <x-ui.field class="w-64" :etiqueta="__('Cómo se abre el PDF')" para="pdf" error="pdf" :ayuda="__('Puede cambiarlo cuando quiera.')">
                            <x-ui.select id="pdf" wire:model="pdf">@foreach ($modosPdf as $valor => $etiqueta)<option value="{{ $valor }}">{{ __($etiqueta) }}</option>@endforeach</x-ui.select>
                        </x-ui.field>
                        <x-ui.button type="submit" tamano="sm" variante="secundario">{{ __('Guardar') }}</x-ui.button>
                    </form>
                @endif
        @endswitch
    </x-ui.card>

    @if ($gestiona)
        <x-ui.card :titulo="__('Cómo se completa')" :descripcion="__('Lo que ya está completado no se desmarca al cambiar la regla.')">
            <form wire:submit="cambiarRegla" class="flex flex-col gap-4">
                <x-ui.field class="max-w-md" :etiqueta="__('Forma de completarlo')" para="completion" error="completion"
                    :ayuda="__('A mano, el estudiante pulsa «Ya lo leí». Con una regla, la plataforma lo marca sola al cumplirse.')">
                    <x-ui.select id="completion" wire:model.live="completion">
                        <option value="manual">{{ __('A mano: el estudiante marca «Ya lo leí»') }}</option>
                        <option value="automatic">{{ __('Con una regla: se marca solo') }}</option>
                    </x-ui.select>
                </x-ui.field>

                @if ($completion === 'automatic')
                    <x-ui.field class="max-w-md" :etiqueta="__('Regla')" para="reglaTipo" error="reglaTipo">
                        <x-ui.select id="reglaTipo" wire:model.live="reglaTipo">
                            @foreach ($reglas as $valor => $etiqueta)<option value="{{ $valor }}">{{ __($etiqueta) }}</option>@endforeach
                        </x-ui.select>
                    </x-ui.field>

                    @if ($reglaTipo === 'min_score')
                        <div class="grid max-w-xl gap-4 sm:grid-cols-2">
                            <x-ui.field :etiqueta="__('Cuestionario')" para="reglaEvaluacion" error="reglaEvaluacion" requerido>
                                <x-ui.select id="reglaEvaluacion" wire:model="reglaEvaluacion">
                                    <option value="">{{ __('Elija un cuestionario…') }}</option>
                                    @foreach ($cuestionarios as $c)<option value="{{ $c->id }}">{{ $c->title }}</option>@endforeach
                                </x-ui.select>
                            </x-ui.field>
                            <x-ui.field :etiqueta="__('Nota mínima')" para="reglaMinimo" error="reglaMinimo" :ayuda="__('De 0 a 10. Se exige cuando la nota ya está publicada.')" requerido>
                                <x-ui.input id="reglaMinimo" type="number" step="0.1" min="0" max="10" wire:model="reglaMinimo" />
                            </x-ui.field>
                        </div>
                    @elseif ($reglaTipo === 'submission')
                        <x-ui.field class="max-w-md" :etiqueta="__('Tarea')" para="reglaEvaluacion" error="reglaEvaluacion" :ayuda="__('En una tarea grupal cuenta la entrega del equipo.')" requerido>
                            <x-ui.select id="reglaEvaluacion" wire:model="reglaEvaluacion">
                                <option value="">{{ __('Elija una tarea…') }}</option>
                                @foreach ($tareas as $t)<option value="{{ $t->id }}">{{ $t->title }}</option>@endforeach
                            </x-ui.select>
                        </x-ui.field>
                    @elseif ($reglaTipo === 'forum_posts')
                        <div class="grid max-w-xl gap-4 sm:grid-cols-2">
                            <x-ui.field :etiqueta="__('Hilo del foro')" para="reglaHilo" error="reglaHilo" requerido>
                                <x-ui.select id="reglaHilo" wire:model="reglaHilo">
                                    <option value="">{{ __('Elija un hilo…') }}</option>
                                    @foreach ($hilos as $h)<option value="{{ $h->id }}">{{ $h->title }}</option>@endforeach
                                </x-ui.select>
                            </x-ui.field>
                            <x-ui.field :etiqueta="__('Mensajes del estudiante')" para="reglaMensajes" error="reglaMensajes" :ayuda="__('De 1 a 20.')" requerido>
                                <x-ui.input id="reglaMensajes" type="number" min="1" max="20" wire:model="reglaMensajes" />
                            </x-ui.field>
                        </div>
                    @endif

                    @error('regla')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                @endif

                <div><x-ui.button type="submit" tamano="sm" variante="secundario">{{ __('Guardar') }}</x-ui.button></div>
            </form>
        </x-ui.card>
    @endif
</div>
