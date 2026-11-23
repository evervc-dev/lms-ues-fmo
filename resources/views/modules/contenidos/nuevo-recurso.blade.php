{{-- Agregar un recurso a una unidad (RF11, RF12, maqueta 06). Formulario por pasos. --}}
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    <x-ui.formulario accion="agregar" :volver="route('secciones.contenidos', ['seccion' => $seccion])" :enviar="__('Publicar')" :pasos="$this->pasos()" :paso="$paso">
        <x-ui.formulario.paso :numero="1">
            <x-ui.card :titulo="__('Qué es')" :descripcion="__('Se publicará en la unidad :u.', ['u' => $unidad->title])">
                <div class="flex flex-col gap-4">
                    <x-ui.field :etiqueta="__('Tipo')" para="tipo" error="tipo">
                        <x-ui.select id="tipo" wire:model.live="tipo">@foreach (['file', 'folder', 'page', 'link', 'video'] as $t)<option value="{{ $t }}">{{ __($tipos[$t]) }}</option>@endforeach</x-ui.select>
                    </x-ui.field>

                    <x-ui.field :etiqueta="__('Título')" para="tituloRecurso" error="tituloRecurso" requerido>
                        <x-ui.input id="tituloRecurso" wire:model="tituloRecurso" />
                    </x-ui.field>

                    <x-ui.field :etiqueta="__('Descripción')" para="descripcion" error="descripcion">
                        <x-ui.textarea id="descripcion" wire:model="descripcion" rows="2" />
                    </x-ui.field>

                    @if (in_array($tipo, $tiposLeccion, true))
                        {{-- Lección con cuestionario (RF38): material y cuestionario en una misma página. --}}
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg p-3 ring-1 ring-borde hover:bg-zinc-50">
                            <input type="checkbox" id="leccion" wire:model.live="leccion" class="mt-0.5 size-4 rounded border-borde text-marca-600 focus:ring-marca-600">
                            <span class="flex flex-col gap-0.5">
                                <span class="text-sm font-medium text-texto">{{ __('Es una lección con verificación de lectura') }}</span>
                                <span class="text-xs text-texto-suave">{{ __('El estudiante lee el material y, al completar la lectura, responde un cuestionario corto en la misma página. No suma a la nota.') }}</span>
                            </span>
                        </label>
                    @endif
                </div>
            </x-ui.card>
        </x-ui.formulario.paso>

        <x-ui.formulario.paso :numero="2">
            <x-ui.card :titulo="__('Contenido')" :descripcion="__('Recurso de tipo :t.', ['t' => __($tipos[$tipo])])">
                <div class="flex flex-col gap-4">
                    @if ($tipo === 'file')
                        <x-ui.field :etiqueta="__('Archivo')" para="archivo" error="archivo" :ayuda="__('Admite :tipos. Máximo :mb MB.', ['tipos' => implode(', ', $extensiones), 'mb' => $maximoMb])" requerido>
                            {{-- Arrastrar y soltar con wire:drop: los archivos soltados entran por la misma subida temporal que el input. --}}
                            <div wire:drop.file="$upload('archivo')"
                                class="flex flex-col items-center gap-2 rounded-lg border-2 border-dashed border-borde bg-zinc-50 px-4 py-6 text-center data-dragging:border-marca-600 data-dragging:bg-marca-50">
                                <x-ui.icon name="arrow-up-tray" class="size-6 text-texto-suave" />
                                <p class="text-sm text-texto-suave">{{ __('Arrastre el archivo aquí o elíjalo desde su equipo.') }}</p>
                                <input id="archivo" type="file" wire:model="archivo" accept="{{ $accept }}" class="text-sm">
                                <p wire:loading wire:target="archivo" class="text-xs text-texto-suave">{{ __('Subiendo el archivo…') }}</p>
                                @if ($archivo)
                                    <p class="text-xs font-medium text-texto">{{ __('Archivo elegido: :nombre', ['nombre' => $archivo->getClientOriginalName()]) }}</p>
                                @endif
                            </div>
                        </x-ui.field>
                        <x-ui.field :etiqueta="__('Si es PDF')" para="pdf" :ayuda="__('Después puede cambiarlo desde el recurso.')">
                            <x-ui.select id="pdf" wire:model="pdf">@foreach ($modosPdf as $valor => $etiqueta)<option value="{{ $valor }}">{{ __($etiqueta) }}</option>@endforeach</x-ui.select>
                        </x-ui.field>
                    @elseif ($tipo === 'folder')
                        <x-ui.field :etiqueta="__('Carpeta')" para="archivos" error="archivos"
                            :ayuda="__('Se conserva la estructura de subcarpetas. Hasta :n archivos de :tipos, :mb MB en total.', ['n' => $maximoArchivos, 'tipos' => implode(', ', $extensiones), 'mb' => $maximoTotalMb])" requerido>
                            <div x-data="selectorCarpeta()" class="flex flex-col gap-2 rounded-lg border-2 border-dashed border-borde bg-zinc-50 px-4 py-6 text-center">
                                <x-ui.icon name="rectangle-stack" class="mx-auto size-6 text-texto-suave" />
                                <p class="text-sm text-texto-suave">{{ __('Elija una carpeta de su equipo: se subirán todos sus archivos.') }}</p>
                                {{-- webkitdirectory: el navegador entrega cada archivo con su ruta relativa. --}}
                                <input id="archivos" type="file" multiple webkitdirectory directory x-on:change="elegir($event)" class="mx-auto text-sm">
                                <p x-show="subiendo" class="text-xs text-texto-suave">{{ __('Subiendo los archivos de la carpeta…') }}</p>
                                <p x-show="error" x-cloak class="text-sm font-medium text-red-700">{{ __('No se pudo subir la carpeta. Revise que no pase de :mb MB ni de :n archivos y vuelva a intentarlo.', ['mb' => $maximoTotalMb, 'n' => $maximoArchivos]) }}</p>
                            </div>
                        </x-ui.field>
                        @error('archivos.*')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                        @if ($rutas)
                            <div class="rounded-lg border border-borde p-3">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __(':n archivos elegidos', ['n' => count($rutas)]) }}</p>
                                <ul class="max-h-48 overflow-auto text-sm text-texto">
                                    @foreach ($rutas as $ruta)<li class="truncate py-0.5">{{ $ruta }}</li>@endforeach
                                </ul>
                                <x-ui.button class="mt-2" tamano="sm" variante="fantasma" wire:click="quitarCarpeta">{{ __('Quitar la carpeta') }}</x-ui.button>
                            </div>
                        @endif
                    @elseif ($tipo === 'page')
                        <x-ui.field :etiqueta="__('Contenido')" para="editor-pagina" error="texto" :ayuda="__('Dé formato con la barra: negrita, cursiva, subrayado, títulos, listas, alineación y enlaces.')">
                            {{-- wire:ignore: el editor vive en el navegador y Livewire no debe redibujar su HTML. --}}
                            <div wire:ignore x-data="editorPaginas('texto', { marcador: @js(__('Escriba aquí el contenido de la página…')) })" class="rounded-lg bg-white">
                                <div id="editor-pagina" x-ref="editor" class="min-h-64"></div>
                            </div>
                        </x-ui.field>
                    @else
                        <x-ui.field :etiqueta="$tipo === 'video' ? __('Enlace del video (YouTube o Drive)') : __('Enlace')" para="url" error="url" requerido>
                            <x-ui.input id="url" type="url" wire:model="url" placeholder="https://" />
                        </x-ui.field>
                    @endif
                </div>
            </x-ui.card>
        </x-ui.formulario.paso>

        <x-ui.formulario.paso :numero="3">
            <x-ui.card :titulo="$leccion ? __('Lectura comprobada') : __('Cómo se completa')" :descripcion="$leccion ? __('El estudiante pulsa «Ya lo leí» cuando cumple las condiciones; solo entonces puede intentar el cuestionario.') : __('Determina cuándo el recurso cuenta como completado en el avance del estudiante.')">
                @if ($leccion)
                    <div class="flex flex-col gap-4">
                        <x-ui.field class="max-w-xs" :etiqueta="__('Minutos mínimos de lectura')" para="lecturaMinutos" error="lecturaMinutos" :ayuda="__('Con la pestaña visible. De 1 a :n.', ['n' => \App\Modules\Contenidos\Services\ProgresoService::MAXIMO_MINUTOS_LECTURA])" requerido>
                            <x-ui.input id="lecturaMinutos" type="number" min="1" wire:model.live="lecturaMinutos" />
                        </x-ui.field>
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" id="lecturaFinal" wire:model.live="lecturaFinal" class="mt-0.5 size-4 rounded border-borde text-marca-600 focus:ring-marca-600">
                            <span class="flex flex-col gap-0.5">
                                <span class="text-sm font-medium text-texto">{{ __('Exigir que llegue al final del material') }}</span>
                                <span class="text-xs text-texto-suave">{{ __('La última página del PDF (en el visor) o el final de la página. En un archivo que solo se descarga, como Word o PowerPoint, basta el tiempo.') }}</span>
                            </span>
                        </label>
                        @error('regla')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                @else
                <div class="flex flex-col gap-4">
                    <x-ui.field :etiqueta="__('Forma de completarlo')" para="completion" error="completion"
                        :ayuda="__('A mano, el estudiante pulsa «Ya lo leí». Con una regla, la plataforma lo marca sola al cumplirse.')">
                        <x-ui.select id="completion" wire:model.live="completion">
                            <option value="manual">{{ __('A mano: el estudiante marca «Ya lo leí»') }}</option>
                            <option value="automatic">{{ __('Con una regla: se marca solo') }}</option>
                        </x-ui.select>
                    </x-ui.field>

                    @if ($completion === 'automatic')
                        <x-ui.field :etiqueta="__('Regla')" para="reglaTipo" error="reglaTipo">
                            <x-ui.select id="reglaTipo" wire:model.live="reglaTipo">
                                @foreach ($reglas as $valor => $etiqueta)<option value="{{ $valor }}">{{ __($etiqueta) }}</option>@endforeach
                            </x-ui.select>
                        </x-ui.field>

                        @if ($reglaTipo === 'min_score')
                            <div class="grid gap-4 sm:grid-cols-2">
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
                            @if ($cuestionarios->isEmpty())
                                <p class="text-sm text-texto-suave">{{ __('Este grupo teórico aún no tiene cuestionarios: cree uno antes de usar esta regla.') }}</p>
                            @endif
                        @elseif ($reglaTipo === 'submission')
                            <x-ui.field :etiqueta="__('Tarea')" para="reglaEvaluacion" error="reglaEvaluacion" :ayuda="__('En una tarea grupal cuenta la entrega del equipo.')" requerido>
                                <x-ui.select id="reglaEvaluacion" wire:model="reglaEvaluacion">
                                    <option value="">{{ __('Elija una tarea…') }}</option>
                                    @foreach ($tareas as $t)<option value="{{ $t->id }}">{{ $t->title }}</option>@endforeach
                                </x-ui.select>
                            </x-ui.field>
                            @if ($tareas->isEmpty())
                                <p class="text-sm text-texto-suave">{{ __('Este grupo teórico aún no tiene tareas: cree una antes de usar esta regla.') }}</p>
                            @endif
                        @elseif ($reglaTipo === 'forum_posts')
                            <div class="grid gap-4 sm:grid-cols-2">
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
                            @if ($hilos->isEmpty())
                                <p class="text-sm text-texto-suave">{{ __('Este grupo teórico aún no tiene hilos en el foro: abra uno antes de usar esta regla.') }}</p>
                            @endif
                        @endif

                        @error('regla')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                    @endif
                </div>
                @endif
            </x-ui.card>
        </x-ui.formulario.paso>

        @if ($leccion)
            <x-ui.formulario.paso :numero="4">
                <x-ui.card :titulo="__('Verificación de lectura')" :descripcion="__('Un cuestionario corto para comprobar que leyó el material. Se crea junto con la lección, lleva su mismo título y se edita después desde la lección.')">
                    <div class="flex flex-col gap-4">
                        {{-- ADR-091: no suma a la nota, por eso no pide cómputo ni peso. --}}
                        <p class="flex items-center gap-2 rounded-lg bg-zinc-50 p-3 text-sm text-texto-suave ring-1 ring-borde">
                            <x-ui.icon name="information-circle" class="size-4 shrink-0" />{{ __('No suma a la nota: el estudiante ve su resultado como retroalimentación. No admite preguntas de ensayo.') }}
                        </p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field :etiqueta="__('Abre')" para="cuestionarioAbre" error="cuestionario.abre" requerido>
                                <x-ui.input id="cuestionarioAbre" type="datetime-local" wire:model="cuestionario.abre" />
                            </x-ui.field>
                            <x-ui.field :etiqueta="__('Cierra')" para="cuestionarioCierra" error="cuestionario.cierra" requerido>
                                <x-ui.input id="cuestionarioCierra" type="datetime-local" wire:model="cuestionario.cierra" />
                            </x-ui.field>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ui.field :etiqueta="__('Banco')" para="cuestionarioBanco" error="cuestionario.banco" requerido>
                                <x-ui.select id="cuestionarioBanco" wire:model.live="cuestionario.banco">
                                    <option value="">{{ __('Elija…') }}</option>
                                    @foreach ($bancos as $b)<option value="{{ $b->id }}">{{ $b->name }} ({{ trans_choice('{1} 1 pregunta|[0,*] :count preguntas', $b->preguntas) }})</option>@endforeach
                                </x-ui.select>
                            </x-ui.field>
                            <x-ui.field :etiqueta="__('Tema (opcional)')" para="cuestionarioTema">
                                <x-ui.select id="cuestionarioTema" wire:model.live="cuestionario.tema" :disabled="$temas->isEmpty()">
                                    <option value="">{{ __('Todos los temas') }}</option>
                                    @foreach ($temas as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                                </x-ui.select>
                            </x-ui.field>
                            <x-ui.field :etiqueta="__('Cantidad al azar')" para="cuestionarioCantidad" error="cuestionario.cantidad"
                                :ayuda="$cuestionario['banco'] ? trans_choice('{1} :count pregunta disponible (0 = todas).|[0,*] :count preguntas disponibles (0 = todas).', $disponibles) : __('0 = todas las del banco.')">
                                <x-ui.input id="cuestionarioCantidad" type="number" min="0" wire:model="cuestionario.cantidad" />
                            </x-ui.field>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ui.field :etiqueta="__('Minutos')" para="cuestionarioMinutos" error="cuestionario.minutos" requerido>
                                <x-ui.input id="cuestionarioMinutos" type="number" min="1" wire:model="cuestionario.minutos" />
                            </x-ui.field>
                            <x-ui.field :etiqueta="__('Intentos')" para="cuestionarioIntentos" error="cuestionario.intentos" :ayuda="__('Vale el mejor.')" requerido>
                                <x-ui.input id="cuestionarioIntentos" type="number" min="1" wire:model="cuestionario.intentos" />
                            </x-ui.field>
                            <x-ui.field :etiqueta="__('Navegación')" para="cuestionarioNavegacion" error="cuestionario.navegacion">
                                <x-ui.select id="cuestionarioNavegacion" wire:model="cuestionario.navegacion">
                                    <option value="free">{{ __('Libre: puede ir y volver') }}</option>
                                    <option value="linear">{{ __('Una a una, sin volver') }}</option>
                                </x-ui.select>
                            </x-ui.field>
                        </div>
                        @error('cantidad')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                        @error('banco')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                        @error('cierra')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                </x-ui.card>
            </x-ui.formulario.paso>
        @endif

        <x-ui.formulario.paso :numero="count($this->pasos())">
            <x-ui.card :titulo="__('Revisar y publicar')" :descripcion="__('Si algo no está bien, vuelva al paso correspondiente desde el indicador de arriba.')">
                <dl class="flex flex-col gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Recurso') }}</dt>
                        <dd class="mt-1 flex flex-wrap items-center gap-2 text-texto">
                            <x-ui.badge color="marca">{{ __($tipos[$tipo]) }}</x-ui.badge>
                            @if ($leccion)<x-ui.badge color="verde">{{ __('Lección con cuestionario') }}</x-ui.badge>@endif
                            {{ $tituloRecurso }}
                        </dd>
                    </div>

                    @if ($descripcion)
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Descripción') }}</dt>
                            <dd class="mt-1 whitespace-pre-line text-texto">{{ $descripcion }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Contenido') }}</dt>
                        <dd class="mt-1 break-words text-texto">
                            @switch($tipo)
                                @case('file') {{ $archivo?->getClientOriginalName() ?? __('sin archivo') }} @break
                                @case('folder') {{ $rutas ? __(':n archivos', ['n' => count($rutas)]) : __('sin carpeta') }} @break
                                @case('page') {{ \Illuminate\Support\Str::limit(trim(strip_tags($texto)), 160) ?: '—' }} @break
                                @default {{ $url ?: '—' }}
                            @endswitch
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Cómo se completa') }}</dt>
                        <dd class="mt-1 text-texto">{{ $reglaEnPalabras ?? __('El estudiante lo marca con «Ya lo leí».') }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Unidad') }}</dt>
                        <dd class="mt-1 text-texto">{{ $unidad->title }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </x-ui.formulario.paso>
    </x-ui.formulario>
</div>
