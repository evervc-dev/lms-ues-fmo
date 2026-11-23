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
                init() { this.reloj = setInterval(() => { if (document.visibilityState !== 'visible') return; this.$dispatch('lectura-segundo'); if (++this.visibles >= {{ $latido }}) this.enviar() }, 1000) },
                destroy() { clearInterval(this.reloj) },
                enviar() { if (this.visibles > 0) { this.$wire.latido(this.visibles); this.visibles = 0 } },
            }"
            x-on:visibilitychange.document="document.visibilityState === 'hidden' && enviar()"
            x-on:lectura-enviar.window="enviar()"
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
            @if ($leccion)
                <x-ui.badge color="marca">{{ __('Lección') }}</x-ui.badge>
            @endif
        </x-slot:acciones>

        @if ($recurso->description)<p class="mb-4 text-sm text-texto-suave">{{ $recurso->description }}</p>@endif

        @if ($esEstudiante && ! $completado && $esLectura)
            {{-- Lectura comprobada (RF38): el tiempo corre en el navegador entre envíos; el servidor decide al pulsar. --}}
            <div class="mb-4 flex flex-col gap-3 rounded-lg bg-marca-50 p-3 text-sm sm:flex-row sm:items-center"
                x-data="{ leidos: {{ $lectura['segundos'] }}, minimo: {{ $lectura['minimo'] }}, alFinal: @js($lectura['alFinal']), exigeFinal: @js($lectura['exigeFinal']),
                    get listo() { return this.leidos >= this.minimo && (this.alFinal || ! this.exigeFinal) },
                    minutos(s) { return Math.floor(s / 60) } }"
                x-on:lectura-segundo.window="leidos++"
                x-on:lectura-final.window="alFinal = true">
                <div class="flex flex-1 flex-col gap-1">
                    <span class="flex flex-wrap gap-x-4 gap-y-1 text-texto">
                        <span><span class="font-medium">{{ __('Lectura') }}:</span> <span x-text="Math.min(minutos(leidos), minutos(minimo))"></span> {{ __('de') }} <span x-text="minutos(minimo)"></span> {{ __('min') }}</span>
                        <template x-if="exigeFinal">
                            <span x-text="alFinal ? @js(__('Llegó al final ✓')) : @js(__('Falta llegar al final'))"></span>
                        </template>
                    </span>
                    <div class="h-1.5 overflow-hidden rounded-full bg-zinc-200" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="Math.min(100, Math.round(100 * leidos / Math.max(1, minimo)))" aria-label="{{ __('Avance de la lectura') }}">
                        <div class="h-full rounded-full bg-marca-600" :style="`width: ${Math.min(100, Math.round(100 * leidos / Math.max(1, minimo)))}%`"></div>
                    </div>
                </div>
                <x-ui.button tamano="sm" x-bind:disabled="! listo" x-on:click="$dispatch('lectura-enviar'); $wire.marcarLeido()">{{ __('Ya lo leí') }}</x-ui.button>
            </div>
        @elseif ($esEstudiante && ! $completado && $reglaEnPalabras)
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
                @if ($esEstudiante && $esLectura && $lectura['exigeFinal'] && ! $lectura['alFinal'])
                    {{-- Final de la página (RF38): al verse este punto, el estudiante llegó al final del texto. --}}
                    <div aria-hidden="true" class="h-px" x-data x-intersect.once="$dispatch('lectura-final'); $wire.llegoAlFinal()"></div>
                @endif

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
                    @if ($disponible)
                        <x-ui.button :href="route('secciones.recurso.zip', ['seccion' => $resumen['ulid'], 'recurso' => $recurso->ulid])" :navegar="false" icono="rectangle-stack">{{ __('Descargar todo (.zip)') }}</x-ui.button>
                    @else
                        <x-ui.alert tipo="aviso" :titulo="__('Los archivos no están disponibles')">{{ __('La carpeta lista sus archivos, pero no se encuentran en el servidor. Avise a su docente para que los vuelva a subir.') }}</x-ui.alert>
                    @endif
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
                @if ($recurso->file_path && ! $disponible)
                    <x-ui.alert tipo="aviso" :titulo="__('El archivo no está disponible')">{{ __('No se encuentra en el servidor. Avise a su docente para que lo vuelva a subir.') }}</x-ui.alert>
                @elseif ($recurso->file_path)
                    @php($archivoUrl = route('secciones.recurso.archivo', ['seccion' => $resumen['ulid'], 'recurso' => $recurso->ulid]))
                    @if ($esPdf && $recurso->pdf_mode === 'viewer' && $esEstudiante && $esLectura && $lectura['exigeFinal'])
                        {{-- Lección (RF38): visor con PDF.js, que sabe cuándo se ve la última página. --}}
                        <div wire:ignore x-data="visorPdf(@js($archivoUrl), () => { $dispatch('lectura-final'); $wire.llegoAlFinal() })" class="relative">
                            <div x-ref="paginas" class="h-[70vh] overflow-y-auto rounded-lg border border-borde bg-zinc-100 p-3"></div>
                            <p x-show="cargando" class="absolute inset-0 flex items-center justify-center text-sm text-texto-suave">{{ __('Cargando el documento…') }}</p>
                            <p x-show="error" x-cloak class="mt-2 text-sm text-red-700">{{ __('No se pudo mostrar el documento. Recargue la página o ábralo en una pestaña nueva.') }}</p>
                        </div>
                        <x-ui.button class="mt-3" variante="secundario" tamano="sm" :href="$archivoUrl" :navegar="false" icono="arrow-up-tray">{{ __('Abrir en una pestaña nueva') }}</x-ui.button>
                    @elseif ($esPdf && $recurso->pdf_mode === 'viewer')
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

    @if ($leccion)
        {{-- Cuestionario de la lección (RF38): misma página que el material; se intenta al completar la lectura. --}}
        @php($quiz = $leccion['cuestionario'])
        @php($fechaQuiz = fn ($f) => \Illuminate\Support\Carbon::parse($f)->timezone('America/El_Salvador')->format('d/m/Y H:i'))
        {{-- Verificación de lectura (ADR-091): no suma a la nota; el estudiante ve su resultado como retroalimentación. --}}
        <x-ui.card :titulo="__('Verificación de lectura')" :descripcion="__('Comprueba que leyó el material. No suma a su nota.')">
            @if ($leccion['gestiona'])
                <x-slot:acciones>
                    <x-ui.button variante="secundario" tamano="sm" :href="route('secciones.cuestionarios.show', ['seccion' => $resumen['ulid'], 'cuestionario' => $quiz->ulid])">{{ __('Intentos y monitor') }}</x-ui.button>
                </x-slot:acciones>
            @endif
            <dl class="grid gap-3 text-sm sm:grid-cols-4">
                <div><dt class="text-texto-suave">{{ __('Abre') }}</dt><dd>{{ $fechaQuiz($quiz->opens_at) }}</dd></div>
                <div><dt class="text-texto-suave">{{ __('Cierra') }}</dt><dd class="font-semibold">{{ $fechaQuiz($quiz->closes_at) }}</dd></div>
                <div><dt class="text-texto-suave">{{ __('Tiempo') }}</dt><dd>{{ __(':n minutos', ['n' => $quiz->time_limit_minutes]) }}</dd></div>
                <div><dt class="text-texto-suave">{{ __('Preguntas · intentos') }}</dt><dd>{{ $leccion['preguntas'] }} · {{ $quiz->max_attempts }}</dd></div>
            </dl>

            @if ($leccion['esEstudiante'])
                @php($enCurso = $leccion['misIntentos']->firstWhere('status', 'in_progress'))
                <div class="mt-5 flex flex-col gap-3">
                    @foreach ($leccion['misIntentos'] as $i)
                        <div class="flex items-center justify-between rounded-lg p-3 text-sm ring-1 ring-borde">
                            <span>{{ __('Intento :n', ['n' => $i->attempt_number]) }}
                                @if ($i->status === 'graded' && $i->score !== null)
                                    · <span class="font-semibold">{{ __('Resultado: :n de 10', ['n' => number_format((float) $i->score, 1)]) }}</span>
                                @elseif ($i->status !== 'in_progress')
                                    · <span class="text-texto-suave">{{ __('Enviado') }}</span>
                                @endif
                            </span>
                            @if ($i->status === 'in_progress')
                                <x-ui.button tamano="sm" :href="route('intentos.show', $i->ulid)" :navegar="false">{{ __('Continuar') }}</x-ui.button>
                            @elseif ($leccion['revisionVisible'])
                                <a href="{{ route('intentos.show', $i->ulid) }}" class="text-sm font-medium text-marca-700 hover:underline">{{ __('Ver revisión') }}</a>
                            @endif
                        </div>
                    @endforeach

                    @if (! $enCurso && $leccion['misIntentos']->count() < $quiz->max_attempts)
                        @if (! $completado)
                            <p class="flex items-center gap-2 text-sm text-texto-suave"><x-ui.icon name="lock-closed" class="size-4" />{{ __('Primero complete la lectura y pulse «Ya lo leí».') }}</p>
                            <div><x-ui.button disabled>{{ __('Intentar cuestionario') }}</x-ui.button></div>
                        @elseif ($leccion['estado'] === 'abierta')
                            <div><x-ui.button x-on:click="$dispatch('abrir-modal', 'intentar-leccion')">{{ __('Intentar cuestionario') }}</x-ui.button></div>
                        @elseif ($leccion['estado'] === 'proxima')
                            <p class="text-sm text-texto-suave">{{ __('El cuestionario aún no abre.') }}</p>
                        @else
                            <p class="text-sm text-texto-suave">{{ __('El cuestionario ya cerró.') }}</p>
                        @endif
                    @endif
                </div>

                <x-ui.modal nombre="intentar-leccion" :titulo="__('¿Está seguro de intentar el cuestionario?')" ancho="sm">
                    <div class="flex flex-col gap-3 text-sm">
                        <p class="text-texto">{{ __('Se abrirá en modo examen y el intento contará aunque lo deje a medias.') }}</p>
                        <ul class="flex flex-col gap-1 text-texto">
                            <li>{{ __('Tiempo: :n minutos. Empieza a correr al comenzar y no se detiene si sale de la página.', ['n' => $quiz->time_limit_minutes]) }}</li>
                            <li>{{ __('Intentos: :usados de :max usados.', ['usados' => $leccion['misIntentos']->count(), 'max' => $quiz->max_attempts]) }}</li>
                            <li>{{ $quiz->navigation === 'linear' ? __('Una a una: no podrá volver a preguntas anteriores.') : __('Puede moverse entre las preguntas.') }}</li>
                        </ul>
                        <p class="text-texto-suave">{{ __('Sus respuestas se guardan solas mientras responde.') }}</p>
                    </div>
                    <x-slot:pie>
                        <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'intentar-leccion')">{{ __('Todavía no') }}</x-ui.button>
                        <x-ui.button wire:click="comenzarCuestionario" wire:loading.attr="disabled">{{ __('Sí, empezar ahora') }}</x-ui.button>
                    </x-slot:pie>
                </x-ui.modal>
            @endif
        </x-ui.card>
    @endif

    @if ($gestiona)
        <x-ui.card :titulo="__('Cómo se completa')" :descripcion="__('Lo que ya está completado no se desmarca al cambiar la regla.')">
            <form wire:submit="cambiarRegla" class="flex flex-col gap-4">
                {{-- Una sola elección: a mano o la regla que lo marca solo. Antes eran dos listas encadenadas («forma» y «regla»). --}}
                @php($opcionActual = $completion === 'manual' ? 'manual' : $reglaTipo)
                <fieldset>
                    <legend class="mb-1 text-sm font-medium text-zinc-800">{{ __('¿Cuándo se marca como completado?') }}</legend>
                    <p class="mb-2 text-xs text-texto-suave">{{ __('Vigente: :r', ['r' => $reglaEnPalabras ?: __('el estudiante pulsa «Ya lo leí»')]) }}</p>
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach (['manual' => [__('A mano'), __('El estudiante pulsa «Ya lo leí».')]] + collect($reglas)->map(fn ($etiqueta, $valor) => [__($etiqueta), match ($valor) {
                            'opened' => __('Se marca solo al abrir el recurso.'),
                            'min_score' => __('Al sacar la nota mínima en un cuestionario.'),
                            'submission' => __('Al entregar una tarea.'),
                            'forum_posts' => __('Al escribir mensajes en un hilo del foro.'),
                            'read' => __('Con tiempo mínimo y, si se exige, llegando al final; luego «Ya lo leí».'),
                            default => '',
                        }])->all() as $valor => [$nombreOpcion, $detalleOpcion])
                            <label wire:key="opcion-{{ $valor }}" @class(['flex cursor-pointer flex-col gap-0.5 rounded-lg p-3 ring-1 transition', 'bg-marca-50 ring-2 ring-marca-600' => $opcionActual === $valor, 'ring-borde hover:bg-zinc-50' => $opcionActual !== $valor])>
                                <input type="radio" name="opcion-completado" value="{{ $valor }}" @checked($opcionActual === $valor) class="sr-only"
                                    @if ($valor === 'manual') x-on:change="$wire.set('completion', 'manual')"
                                    @else x-on:change="$wire.set('completion', 'automatic', false); $wire.set('reglaTipo', @js($valor))" @endif>
                                <span class="text-sm font-medium text-texto">{{ $nombreOpcion }}</span>
                                <span class="text-xs text-texto-suave">{{ $detalleOpcion }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('completion')<p class="mt-1 text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                    @error('reglaTipo')<p class="mt-1 text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                </fieldset>

                @if ($completion === 'automatic')
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
                    @elseif ($reglaTipo === 'read')
                        <div class="flex max-w-xl flex-col gap-3">
                            <x-ui.field class="max-w-xs" :etiqueta="__('Minutos mínimos de lectura')" para="lecturaMinutos" error="lecturaMinutos" :ayuda="__('Con la pestaña visible.')" requerido>
                                <x-ui.input id="lecturaMinutos" type="number" min="1" wire:model="lecturaMinutos" />
                            </x-ui.field>
                            <label class="flex cursor-pointer items-center gap-3 text-sm">
                                <input type="checkbox" wire:model="lecturaFinal" class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600">
                                {{ __('Exigir que llegue al final del material (PDF en el visor o página)') }}
                            </label>
                        </div>
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
