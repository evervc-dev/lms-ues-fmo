{{-- Carga académica (RF08, maquetas 23 y 24). --}}
@php
    $estados = ['ok' => ['verde', __('Completa')], 'llena' => ['azul', __('Llena')], 'sin_docente' => ['ambar', __('Sin docente')], 'choque' => ['rojo', __('Choque de aula')]];
    $dias = [1 => __('Lunes'), 2 => __('Martes'), 3 => __('Miércoles'), 4 => __('Jueves'), 5 => __('Viernes'), 6 => __('Sábado')];
@endphp
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Carga académica · Período :p', ['p' => $periodo ?? '—'])" :subtitulo="__('Secciones, horarios, aulas y docentes. El sistema rechaza los choques de aula y de docente.')">
        @if ($gestiona)
            <x-slot:acciones><x-ui.button icono="plus" wire:click="abrir('nueva-seccion')">{{ __('Nueva sección') }}</x-ui.button></x-slot:acciones>
        @endif
    </x-ui.encabezado>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat :etiqueta="__('Secciones planificadas')" :valor="$kpi['secciones']" icono="squares-2x2" />
        <x-ui.stat :etiqueta="__('Sin docente')" :valor="$kpi['sinDocente']" icono="user-plus" :tono="$kpi['sinDocente'] ? 'aviso' : 'exito'" />
        <x-ui.stat :etiqueta="__('Ocupación de cupos')" :valor="$kpi['ocupacion'].' %'" icono="building-office" />
        <x-ui.stat :etiqueta="__('Choques de aula')" :valor="$kpi['choques']" icono="exclamation-triangle" :tono="$kpi['choques'] ? 'error' : 'exito'" />
    </div>

    <x-ui.card ajustar>
        <div class="mb-4 grid gap-3 md:grid-cols-[1fr_auto]">
            <x-ui.search wire:model.live.debounce.300ms="buscar" :placeholder="__('Buscar por código o asignatura…')" />
            <x-ui.select wire:model.live="estado" :aria-label="__('Estado')">
                <option value="">{{ __('Todos los estados') }}</option>
                @foreach ($estados as $valor => [, $etiqueta])<option value="{{ $valor }}">{{ $etiqueta }}</option>@endforeach
            </x-ui.select>
        </div>

        <x-ui.table ajustar :vacio="__('No hay secciones con esos filtros.')">
            <x-slot:cabecera><th>{{ __('Asignatura / sección') }}</th><th>{{ __('Docentes') }}</th><th>{{ __('Horario y aula') }}</th><th class="text-right">{{ __('Cupo') }}</th><th>{{ __('Estado') }}</th><th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
            @foreach ($this->secciones as $s)
                <tr wire:key="carga-{{ $s->id }}" @class(['bg-red-50/40' => $s->estado === 'choque'])>
                    <td><span class="font-semibold">{{ $s->code }}</span> · {{ $s->name }}<p class="text-xs text-texto-suave">{{ __('Grupo :g', ['g' => $s->grupo]) }}{{ $s->ciclo ? ' · '.__('ciclo :c', ['c' => $s->ciclo]) : '' }}</p></td>
                    <td>
                        @forelse ($s->docentes as $d)
                            <span class="flex items-center gap-1">{{ $d->first_name }} {{ $d->last_name }}@unless ($d->is_responsible)<span class="text-xs text-texto-suave">({{ __('co-docente') }})</span>@endunless
                                @if ($asignaDocentes)<button type="button" wire:click="quitarDocente({{ $s->id }}, {{ $d->id }})" wire:confirm="{{ __('¿Quitar a este docente de la sección?') }}" class="text-texto-suave hover:text-red-700" aria-label="{{ __('Quitar docente') }}"><x-ui.icon name="x-mark" class="size-4" /></button>@endif</span>
                        @empty
                            <span class="text-amber-700">{{ __('Sin asignar') }}</span>
                        @endforelse
                    </td>
                    <td>@forelse ($s->horario as $h)<p>{{ $h }}</p>@empty<span class="text-texto-suave">{{ __('Sin horario') }}</span>@endforelse
                        @if ($s->choque)<p class="text-xs text-red-700">{{ __('Choca con :s', ['s' => $s->choque]) }}</p>@endif</td>
                    <td class="text-right">{{ $s->inscritos }} / {{ $s->max_capacity }}</td>
                    <td><x-ui.badge :color="$estados[$s->estado][0]">{{ $estados[$s->estado][1] }}</x-ui.badge></td>
                    <td class="text-right whitespace-nowrap">
                        @if ($gestiona)
                            <x-ui.button tamano="sm" variante="fantasma" wire:click="abrir('horario', {{ $s->id }})">{{ __('Horario') }}</x-ui.button>
                        @endif
                        @if ($cambiaCupo)
                            <x-ui.button tamano="sm" variante="fantasma" wire:click="abrirCupo({{ $s->id }})">{{ __('Cupo') }}</x-ui.button>
                        @endif
                        @if ($asignaDocentes)
                            <x-ui.button tamano="sm" variante="fantasma" wire:click="abrir('docente', {{ $s->id }})">{{ __('Asignar docente') }}</x-ui.button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    @if ($gestiona)
        <x-ui.modal nombre="nueva-seccion" :titulo="__('Nueva sección')">
            <form wire:submit="crearSeccion" id="form-seccion" class="flex flex-col gap-4">
                <x-ui.field :etiqueta="__('Asignatura')" para="asignaturaId" error="asignaturaId" requerido>
                    <x-ui.select id="asignaturaId" wire:model="asignaturaId"><option value="">{{ __('Elija…') }}</option>@foreach ($asignaturas as $a)<option value="{{ $a->id }}">{{ $a->code }} · {{ $a->name }}</option>@endforeach</x-ui.select>
                </x-ui.field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field :etiqueta="__('Grupo')" para="grupo" error="grupo" requerido><x-ui.input id="grupo" wire:model="grupo" /></x-ui.field>
                    <x-ui.field :etiqueta="__('Cupo máximo')" para="cupo" error="cupo" requerido><x-ui.input id="cupo" type="number" wire:model="cupo" /></x-ui.field>
                </div>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'nueva-seccion')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-seccion">{{ __('Crear sección') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>

        <x-ui.modal nombre="horario" :titulo="__('Horario').($actual ? ' · '.$actual->code.' · '.$actual->grupo : '')">
            @if ($actual && $actual->bloques->isNotEmpty())
                {{-- Cada cambio avisa a los estudiantes matriculados y a los docentes de la sección (RF08). --}}
                <ul class="mb-4 divide-y divide-borde rounded-lg border border-borde text-sm">
                    @foreach ($actual->bloques as $b)
                        <li wire:key="bloque-{{ $b->id }}" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                            <span>{{ $b->etiqueta }}</span>
                            <div class="flex items-center gap-2">
                                <div class="w-44"><x-ui.select :aria-label="__('Aula de :b', ['b' => $b->etiqueta])" wire:change="cambiarAula({{ $b->id }}, $event.target.value)">
                                    <option value="" @selected($b->aulaId === null)>{{ __('Virtual / sin aula') }}</option>
                                    @foreach ($aulas as $a)<option value="{{ $a->id }}" @selected($b->aulaId === (int) $a->id)>{{ $a->code }} ({{ $a->capacity }})</option>@endforeach
                                </x-ui.select></div>
                                <x-ui.button tamano="sm" variante="fantasma" wire:click="quitarHorario({{ $b->id }})" wire:confirm="{{ __('¿Quitar este horario? Se avisará a los estudiantes y docentes de la sección.') }}">{{ __('Quitar') }}</x-ui.button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
            <form wire:submit="agregarHorario" id="form-horario" class="flex flex-col gap-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.field :etiqueta="__('Día')" para="dia" error="dia"><x-ui.select id="dia" wire:model="dia">@foreach ($dias as $n => $d)<option value="{{ $n }}">{{ $d }}</option>@endforeach</x-ui.select></x-ui.field>
                    <x-ui.field :etiqueta="__('Inicio')" para="inicio" error="inicio"><x-ui.input id="inicio" type="time" wire:model="inicio" /></x-ui.field>
                    <x-ui.field :etiqueta="__('Fin')" para="fin" error="fin"><x-ui.input id="fin" type="time" wire:model="fin" /></x-ui.field>
                </div>
                <x-ui.field :etiqueta="__('Aula')" para="aulaId" error="aula">
                    <x-ui.select id="aulaId" wire:model="aulaId"><option value="">{{ __('Virtual / sin aula') }}</option>@foreach ($aulas as $a)<option value="{{ $a->id }}">{{ $a->code }} ({{ $a->capacity }})</option>@endforeach</x-ui.select>
                </x-ui.field>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'horario')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-horario">{{ __('Agregar') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif

    <x-ui.modal nombre="docente" :titulo="__('Asignar docente').($actual ? ' · '.$actual->code.' · '.$actual->grupo : '')">
        <form wire:submit="asignarDocente" id="form-docente" class="flex flex-col gap-4">
            <x-ui.field :etiqueta="__('Docente')" para="docenteId" error="docente" :ayuda="__('Entre paréntesis, las secciones que ya tiene en el período.')" requerido>
                <x-ui.select id="docenteId" wire:model="docenteId"><option value="">{{ __('Elija…') }}</option>@foreach ($docentes as $d)<option value="{{ $d->id }}">{{ $d->last_name }}, {{ $d->first_name }} · {{ $d->departamento }} ({{ $d->carga }})</option>@endforeach</x-ui.select>
            </x-ui.field>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="responsable" class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600"> {{ __('Responsable de la sección') }}</label>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'docente')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-docente">{{ __('Asignar') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>

    @if ($cambiaCupo)
        <x-ui.modal nombre="cupo" :titulo="__('Cupo de la sección').($actual ? ' · '.$actual->code.' · '.$actual->grupo : '')">
            <form wire:submit="cambiarCupo" id="form-cupo" class="flex flex-col gap-4">
                <x-ui.field :etiqueta="__('Cupo máximo')" para="cupoNuevo" error="cupoNuevo"
                    :ayuda="$actual ? __('Inscritos: :n · mínimo de la sección: :m', ['n' => $actual->inscritos, 'm' => $actual->min_capacity]) : null" requerido>
                    <x-ui.input id="cupoNuevo" type="number" min="1" wire:model="cupoNuevo" />
                </x-ui.field>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'cupo')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-cupo">{{ __('Guardar') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif
</div>
