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
                    <td class="text-right"><x-ui.button tamano="sm" :variante="$e->score === null ? 'primario' : 'secundario'" wire:click="abrir({{ $e->id }}, '{{ $e->ulid }}', @js($e->archivo))">{{ $e->score === null ? __('Calificar') : __('Cambiar') }}</x-ui.button></td>
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
        <x-ui.card :titulo="__('Equipos')" :descripcion="__('Tarea grupal: arme los equipos con estudiantes de la sección. Lo que entregue un integrante cuenta para todo el equipo, y la nota se copia a cada uno.')">
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
                            <label wire:key="sin-equipo-{{ $m->matricula }}" class="flex items-center gap-2 py-0.5 text-sm"><input type="checkbox" wire:model="integrantes" value="{{ $m->matricula }}" /> {{ $m->last_name }}, {{ $m->first_name }} <span class="text-xs text-texto-suave">{{ $m->student_code }}</span></label>
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

    <x-ui.modal nombre="calificar" :titulo="__('Calificar entrega')" ancho="xl">
        {{-- El documento a la izquierda y la nota a la derecha: calificar sin poder abrir la entrega era el defecto. --}}
        <div class="grid gap-4 md:grid-cols-2">
            <div class="flex flex-col gap-2">
                @php($ext = strtolower(pathinfo((string) $entregaArchivo, PATHINFO_EXTENSION)))
                @php($url = $entregaUlid !== '' ? route('secciones.entregas.archivo', ['seccion' => $seccion, 'entrega' => $entregaUlid]) : null)
                @if ($url && $ext === 'pdf')
                    <iframe src="{{ $url }}#view=FitH" class="h-96 w-full rounded-md border border-borde" title="{{ __('Documento de la entrega') }}"></iframe>
                @elseif ($url && in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true))
                    <img src="{{ $url }}" alt="{{ __('Documento de la entrega') }}" class="max-h-96 w-full rounded-md border border-borde object-contain">
                @elseif ($url)
                    <div class="flex h-96 flex-col items-center justify-center gap-2 rounded-md border border-dashed border-borde p-4 text-center text-sm text-texto-suave">
                        <p>{{ __('Este tipo de archivo no se puede ver aquí.') }}</p>
                        <p class="font-medium text-texto">{{ $entregaArchivo }}</p>
                    </div>
                @else
                    <div class="flex h-96 items-center justify-center rounded-md border border-dashed border-borde text-sm text-texto-suave">{{ __('La entrega no tiene archivo.') }}</div>
                @endif
                @if ($url)
                    <x-ui.button variante="secundario" tamano="sm" :href="$url" target="_blank" rel="noopener">{{ __('Descargar :a', ['a' => $entregaArchivo]) }}</x-ui.button>
                @endif
            </div>
        <form wire:submit="calificar" id="form-calificar" class="flex flex-col gap-4">
            @if ($rubrica->isNotEmpty())
                @foreach ($rubrica as $criterio)
                    @php($maximo = rtrim(rtrim(number_format((float) $criterio->max_points, 2, '.', ''), '0'), '.'))
                    <x-ui.field :etiqueta="$criterio->name" :para="'crit-'.$criterio->id" :error="'puntos.'.$criterio->id" :ayuda="__('De 0 a :m.', ['m' => $maximo])" requerido>
                        <x-ui.input :id="'crit-'.$criterio->id" type="number" step="0.1" min="0" :max="$criterio->max_points" wire:model.live="puntos.{{ $criterio->id }}" />
                    </x-ui.field>
                @endforeach
                @php($total = collect($rubrica)->sum(fn ($c) => (float) ($puntos[$c->id] ?? 0)))
                <p class="text-sm font-medium">{{ __('Nota: :n de 10', ['n' => rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.')]) }}</p>
            @else
                <x-ui.field :etiqueta="__('Nota (0 a 10)')" para="notaEntrega" error="nota" requerido><x-ui.input id="notaEntrega" type="number" step="0.1" min="0" max="10" wire:model="nota" /></x-ui.field>
            @endif
            @error('nota')<p class="text-xs font-medium text-red-700">{{ $message }}</p>@enderror
            <x-ui.field :etiqueta="__('Retroalimentación')" para="comentario"><x-ui.textarea id="comentario" wire:model="comentario" rows="3" /></x-ui.field>
            <p class="text-xs text-texto-suave">{{ __('Si la entrega fue tardía con penalización, se descuenta automáticamente.') }}</p>
        </form>
        </div>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'calificar')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-calificar">{{ __('Guardar') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
