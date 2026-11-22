{{-- Entregas de una tarea (RF15). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />
    <x-ui.card ajustar :titulo="__('Entregas · :t', ['t' => $tarea->title])" :descripcion="$anonima ? __('Calificación anónima: los nombres aparecen al publicar las notas.') : null">
        <x-slot:acciones>
            <livewire:evaluaciones.prorrogas :seccion="$seccion" :actividad="$tarea->ulid" :key="'prorrogas-'.$tarea->ulid" />
            @unless ($tarea->is_published)
                @php
                    $sinCalificar = $entregas->whereNull('score')->count();
                    $confirmar = collect([
                        trans_choice('{0} Las :total entregas están calificadas.|{1} Falta calificar 1 de :total entregas: quedará sin nota.|[2,*] Faltan calificar :count de :total entregas: quedarán sin nota.', $sinCalificar, ['total' => $entregas->count()]),
                        $anonima ? __('Al publicar se revelan los nombres de quienes entregaron.') : null,
                        __('Los estudiantes verán sus notas. ¿Publicar?'),
                    ])->filter()->implode(' ');
                @endphp
                <x-ui.button tamano="sm" wire:click="publicar" wire:confirm="{{ $confirmar }}">{{ __('Publicar notas') }}</x-ui.button>@else<x-ui.badge color="verde">{{ __('Notas publicadas') }}</x-ui.badge>@endunless
        </x-slot:acciones>
        <x-ui.table ajustar :vacio="__('Aún no hay entregas.')">
            <x-slot:cabecera><th>{{ __('Estudiante') }}</th><th>{{ __('Entregada') }}</th><th>{{ __('Archivo') }}</th><th class="text-right">{{ __('Nota') }}</th><th></th></x-slot:cabecera>
            @foreach ($entregas as $e)
                <tr wire:key="entrega-{{ $e->id }}">
                    <td class="font-medium">{{ $e->nombre }}
                        {{-- RF15: cada integrante del equipo con su nota; se puede ajustar la de uno solo. --}}
                        @if ($e->team_id && ! $anonima)
                            @foreach ($e->integrantes as $i)
                                <p class="text-xs font-normal text-texto-suave">{{ $i->last_name }}, {{ $i->first_name }} · {{ $i->score !== null ? number_format((float) $i->score, 2) : '—' }}
                                    @if ($e->score !== null)<button type="button" class="text-marca-600 underline" wire:click="abrirAjuste({{ $e->id }}, {{ $i->matricula }})">{{ __('Ajustar') }}</button>@endif</p>
                            @endforeach
                        @endif
                    </td>
                    <td>{{ \Illuminate\Support\Carbon::parse($e->submitted_at)->timezone('America/El_Salvador')->format('d/m/Y H:i') }} @if ($e->is_late)<x-ui.badge color="ambar">{{ __('Tardía') }}</x-ui.badge>@endif</td>
                    {{-- RF15: el archivo se abre o se descarga; antes solo se leía su nombre. --}}
                    <td class="max-w-48 truncate">
                        @if ($e->archivo)
                            <a href="{{ route('secciones.entregas.archivo', ['seccion' => $seccion, 'entrega' => $e->ulid]) }}" target="_blank" rel="noopener" class="text-marca-700 hover:underline" title="{{ $e->archivo }}">{{ $e->archivo }}</a>
                        @else
                            <span class="text-texto-suave">{{ __('Sin archivo') }}</span>
                        @endif
                    </td>
                    <td class="text-right">{{ $e->score !== null ? number_format((float) $e->score, 2) : '—' }}</td>
                    <td class="text-right"><x-ui.button tamano="sm" :variante="$e->score === null ? 'primario' : 'secundario'" wire:click="abrir({{ $e->id }})">{{ $e->score === null ? __('Calificar') : __('Cambiar') }}</x-ui.button></td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    {{-- RF15 (ADR-065): la rúbrica es de esta tarea; se agrega o se cambia hasta la primera calificación. --}}
    <x-ui.card :titulo="__('Rúbrica')" :descripcion="$rubrica->isEmpty() ? __('Sin rúbrica: las entregas se califican con una nota directa de 0 a 10.') : __('La nota es la suma de los puntos que ponga en cada criterio.')">
        <x-slot:acciones>
            @if ($rubricaEditable)
                @if ($rubrica->isNotEmpty())<x-ui.button tamano="sm" variante="fantasma" wire:click="quitarRubrica" wire:confirm="{{ __('La tarea se calificará con una nota directa. ¿Quitar la rúbrica?') }}">{{ __('Quitar') }}</x-ui.button>@endif
                <x-ui.button tamano="sm" variante="secundario" wire:click="abrirRubrica">{{ $rubrica->isEmpty() ? __('Agregar rúbrica') : __('Editar rúbrica') }}</x-ui.button>
            @endif
        </x-slot:acciones>
        @if ($rubrica->isNotEmpty())
            <x-ui.table>
                <x-slot:cabecera><th>{{ __('Criterio') }}</th><th class="text-right">{{ __('Puntos máximos') }}</th></x-slot:cabecera>
                @foreach ($rubrica as $c)
                    <tr wire:key="criterio-{{ $c->id }}"><td>{{ $c->name }}</td><td class="text-right">{{ rtrim(rtrim(number_format((float) $c->max_points, 2, '.', ''), '0'), '.') }}</td></tr>
                @endforeach
            </x-ui.table>
        @endif
        @unless ($rubricaEditable)
            <p class="mt-3 text-xs text-texto-suave">{{ __('La tarea ya tiene calificaciones, así que la rúbrica quedó fija: todas las entregas se califican con la misma.') }}</p>
        @endunless
    </x-ui.card>

    @if ($rubricaEditable)
        <x-ui.modal nombre="rubrica" :titulo="__('Rúbrica de la tarea')" ancho="lg">
            <form wire:submit="guardarRubrica" id="form-rubrica">
                @include('modules.evaluaciones.partials.criterios-rubrica', ['modelo' => 'criteriosRubrica', 'criterios' => $criteriosRubrica])
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'rubrica')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-rubrica">{{ __('Guardar rúbrica') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif

    @if ($equipos !== null)
        <x-ui.card :titulo="__('Equipos')" :descripcion="__('Tarea grupal: arme los equipos con estudiantes del grupo teórico. Lo que entregue un integrante cuenta para todo el equipo, y la nota se copia a cada uno.')">
            <div class="grid gap-5 lg:grid-cols-2">
                <div class="flex flex-col gap-2">
                    @forelse ($equipos['equipos'] as $eq)
                        <div wire:key="equipo-{{ $eq->id }}" class="rounded-lg p-3 ring-1 ring-borde">
                            <p class="flex items-center justify-between gap-2 text-sm font-medium">{{ $eq->name }}
                                @if ($eq->entrego)<x-ui.badge color="verde">{{ __('Entregó') }}</x-ui.badge>
                                @else<x-ui.button tamano="sm" variante="fantasma" wire:click="eliminarEquipo({{ $eq->id }})" wire:confirm="{{ __('Sus integrantes quedarán sin equipo. ¿Eliminar el equipo?') }}">{{ __('Eliminar') }}</x-ui.button>@endif
                            </p>
                            <p class="text-xs text-texto-suave">{{ $eq->integrantes->map(fn ($i) => $i->last_name.', '.$i->first_name)->join(' · ') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-texto-suave">{{ __('Aún no hay equipos.') }}</p>
                    @endforelse
                </div>
                <form wire:submit="crearEquipo" class="flex flex-col gap-3">
                    <x-ui.field :etiqueta="__('Nombre del equipo')" para="nombreEquipo" error="nombreEquipo" requerido><x-ui.input id="nombreEquipo" wire:model="nombreEquipo" maxlength="100" /></x-ui.field>
                    <fieldset>
                        <legend class="mb-1 text-sm font-medium">{{ __('Integrantes (sin equipo todavía)') }}</legend>
                        @forelse ($equipos['sinEquipo'] as $m)
                            <label wire:key="sin-equipo-{{ $m->matricula }}" class="flex items-center gap-2 py-0.5 text-sm"><input type="checkbox" wire:model="integrantes" value="{{ $m->matricula }}" /> {{ $m->last_name }}, {{ $m->first_name }} <span class="text-xs text-texto-suave">{{ mb_strtoupper((string) $m->student_code) }}</span></label>
                        @empty
                            <p class="text-sm text-texto-suave">{{ __('Todos los estudiantes ya tienen equipo.') }}</p>
                        @endforelse
                        @error('integrantes')<p class="text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                    </fieldset>
                    <x-ui.button type="submit" variante="secundario" :disabled="$equipos['sinEquipo']->isEmpty()">{{ __('Crear equipo') }}</x-ui.button>
                </form>
            </div>
        </x-ui.card>

        <x-ui.modal nombre="ajustar-integrante" :titulo="__('Ajustar la nota de un integrante')">
            <form wire:submit="ajustar" id="form-ajustar" class="flex flex-col gap-4">
                <x-ui.field :etiqueta="__('Nota (0 a 10)')" para="notaIntegrante" error="notaIntegrante" requerido><x-ui.input id="notaIntegrante" type="number" step="0.1" min="0" max="10" wire:model="notaIntegrante" /></x-ui.field>
                <x-ui.field :etiqueta="__('Motivo')" para="motivoAjuste" error="motivoAjuste" requerido><x-ui.textarea id="motivoAjuste" wire:model="motivoAjuste" rows="2" /></x-ui.field>
                <p class="text-xs text-texto-suave">{{ __('Solo cambia la nota de este integrante. Si vuelve a calificar la entrega del equipo, la nota se copia otra vez a todos.') }}</p>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'ajustar-integrante')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-ajustar">{{ __('Guardar') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif

    @php
        $actual = $entregas->firstWhere('id', $entregaId);
        $posicion = $actual ? $entregas->search(fn ($e) => $e->id === $actual->id) : false;
        $pendientes = $entregas->whereNull('score')->count();
    @endphp
    <x-ui.modal nombre="calificar" ancho="completo"
        :titulo="$actual ? __('Calificar · :n', ['n' => $actual->nombre]) : __('Calificar entrega')"
        :subtitulo="$actual ? __('Entrega :i de :t · entregada el :f', ['i' => $posicion + 1, 't' => $entregas->count(), 'f' => \Illuminate\Support\Carbon::parse($actual->submitted_at)->timezone('America/El_Salvador')->format('d/m/Y H:i')]) : null">
        {{-- El documento a la izquierda, con todo el alto, y la nota a la derecha: se califica mirando la entrega. --}}
        <div class="grid gap-5 lg:h-full lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="flex min-h-0 flex-col gap-2">
                @php($ext = strtolower(pathinfo((string) $entregaArchivo, PATHINFO_EXTENSION)))
                @php($url = $entregaUlid !== '' ? route('secciones.entregas.archivo', ['seccion' => $seccion, 'entrega' => $entregaUlid]) : null)
                @if ($url && $ext === 'pdf')
                    <iframe wire:key="visor-{{ $entregaUlid }}" src="{{ $url }}#view=FitH" class="h-[60vh] w-full rounded-lg border border-borde lg:h-auto lg:flex-1" title="{{ __('Documento de la entrega') }}"></iframe>
                @elseif ($url && in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true))
                    <div class="flex h-[60vh] items-center justify-center overflow-auto rounded-lg border border-borde bg-zinc-50 lg:h-auto lg:flex-1">
                        <img wire:key="visor-{{ $entregaUlid }}" src="{{ $url }}" alt="{{ __('Documento de la entrega') }}" class="max-h-full max-w-full object-contain">
                    </div>
                @elseif ($url)
                    <div class="flex h-64 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-borde p-4 text-center text-sm text-texto-suave lg:h-auto lg:flex-1">
                        <x-ui.icon name="arrow-down-tray" class="size-8" />
                        <p>{{ __('Este tipo de archivo no se puede ver aquí: descárguelo para revisarlo.') }}</p>
                        <p class="font-medium text-texto">{{ $entregaArchivo }}</p>
                    </div>
                @else
                    <div class="flex h-64 items-center justify-center rounded-lg border border-dashed border-borde text-sm text-texto-suave lg:h-auto lg:flex-1">{{ __('La entrega no tiene archivo.') }}</div>
                @endif
                @if ($url)
                    <div><x-ui.button variante="secundario" tamano="sm" icono="arrow-top-right-on-square" :href="$url" target="_blank" rel="noopener">{{ __('Abrir :a en otra pestaña', ['a' => $entregaArchivo]) }}</x-ui.button></div>
                @endif
            </div>

            <form wire:submit="calificar" id="form-calificar" class="flex flex-col gap-4">
                @if ($actual)
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($actual->is_late)
                            <x-ui.badge color="ambar">{{ (float) $actual->late_penalty_applied > 0 ? __('Tardía · −:p %', ['p' => rtrim(rtrim(number_format((float) $actual->late_penalty_applied, 2, '.', ''), '0'), '.')]) : __('Tardía') }}</x-ui.badge>
                        @endif
                        <x-ui.badge :color="$actual->score === null ? 'gris' : 'verde'">{{ $actual->score === null ? __('Sin nota') : __('Nota actual: :n', ['n' => number_format((float) $actual->score, 2)]) }}</x-ui.badge>
                    </div>
                @endif
                @if ($rubrica->isNotEmpty())
                    <fieldset class="flex flex-col gap-3">
                        <legend class="mb-1 text-sm font-semibold text-texto">{{ __('Rúbrica') }}</legend>
                        @foreach ($rubrica as $criterio)
                            @php($maximo = rtrim(rtrim(number_format((float) $criterio->max_points, 2, '.', ''), '0'), '.'))
                            <x-ui.field :etiqueta="$criterio->name" :para="'crit-'.$criterio->id" :error="'puntos.'.$criterio->id" requerido>
                                <div class="flex items-center gap-2">
                                    <x-ui.input :id="'crit-'.$criterio->id" type="number" step="0.1" min="0" :max="$criterio->max_points" wire:model.live.debounce.300ms="puntos.{{ $criterio->id }}" class="w-24" />
                                    <span class="text-sm text-texto-suave">{{ __('de :m', ['m' => $maximo]) }}</span>
                                </div>
                            </x-ui.field>
                        @endforeach
                    </fieldset>
                    @php($total = collect($rubrica)->sum(fn ($c) => (float) ($puntos[$c->id] ?? 0)))
                    <div class="flex items-baseline justify-between rounded-lg bg-marca-50 px-4 py-3">
                        <span class="text-sm font-medium text-marca-800">{{ __('Nota') }}</span>
                        <span class="text-2xl font-semibold text-marca-800">{{ rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.') ?: '0' }} <span class="text-sm font-normal">/ 10</span></span>
                    </div>
                @else
                    <x-ui.field :etiqueta="__('Nota (0 a 10)')" para="notaEntrega" error="nota" requerido>
                        <x-ui.input id="notaEntrega" type="number" step="0.1" min="0" max="10" wire:model="nota" class="text-lg" />
                    </x-ui.field>
                @endif
                @if ($rubrica->isNotEmpty())@error('nota')<p class="text-xs font-medium text-red-700">{{ $message }}</p>@enderror@endif
                <x-ui.field :etiqueta="__('Retroalimentación')" para="comentario" :ayuda="__('La ve el estudiante cuando se publiquen las notas.')">
                    <x-ui.textarea id="comentario" wire:model="comentario" rows="5" />
                </x-ui.field>
                @if ($actual && $actual->is_late && (float) $actual->late_penalty_applied > 0)
                    <p class="text-xs text-texto-suave">{{ __('Escriba la nota sin descontar: la penalización por tardía se aplica sola al guardar.') }}</p>
                @endif
            </form>
        </div>
        <x-slot:pie>
            {{-- Un solo bloque: en el teléfono el pie apilaba cinco botones y se comía un cuarto de la pantalla. --}}
            <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center">
                <div class="flex items-center justify-between gap-2 sm:flex-1 sm:justify-start">
                    <x-ui.button variante="fantasma" tamano="sm" icono="arrow-left" wire:click="moverA(-1)" :disabled="$posicion === false || $posicion === 0">{{ __('Anterior') }}</x-ui.button>
                    <span class="text-xs text-texto-suave sm:order-last">{{ trans_choice('{0} Todas calificadas|{1} Falta 1 sin nota|[2,*] Faltan :count sin nota', $pendientes) }}</span>
                    <x-ui.button variante="fantasma" tamano="sm" wire:click="moverA(1)" :disabled="$posicion === false || $posicion >= $entregas->count() - 1">{{ __('Siguiente') }} <x-ui.icon name="chevron-right" class="size-4" /></x-ui.button>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                    <span class="hidden sm:inline-flex"><x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'calificar')">{{ __('Cerrar') }}</x-ui.button></span>
                    <x-ui.button type="submit" form="form-calificar" variante="secundario">{{ __('Guardar') }}</x-ui.button>
                    <x-ui.button type="button" wire:click="calificar(true)">{{ __('Guardar y siguiente') }}</x-ui.button>
                </div>
            </div>
        </x-slot:pie>
    </x-ui.modal>
</div>
