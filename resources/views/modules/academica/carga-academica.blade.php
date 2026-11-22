{{-- Carga académica (RF08, maquetas 23 y 24). --}}
@php
    $estados = ['ok' => ['verde', __('Completa')], 'llena' => ['azul', __('Llena')], 'sin_docente' => ['ambar', __('Sin docente')], 'choque' => ['rojo', __('Choque de aula')]];
    $dias = [1 => __('Lunes'), 2 => __('Martes'), 3 => __('Miércoles'), 4 => __('Jueves'), 5 => __('Viernes'), 6 => __('Sábado')];
@endphp
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Carga académica · Período :p', ['p' => $periodo ?? '—'])" :subtitulo="__('Grupos teóricos, horarios, aulas y docentes. El sistema rechaza los choques de aula y de docente.')">
        <x-slot:acciones>
            {{-- El siguiente período se prepara mientras otro está en curso (Ciclo de vida del período académico). --}}
            @if ($periodos->count() > 1)
                <x-ui.select wire:model.live="periodoId" :aria-label="__('Período')" class="w-auto">
                    @foreach ($periodos as $p)
                        <option value="{{ $p->id }}" @selected($p->id === $periodoActual)>{{ $p->name }} · {{ $p->status === 'active' ? __('en curso') : __('en preparación') }}</option>
                    @endforeach
                </x-ui.select>
            @endif
            @if ($crea)
                <x-ui.button icono="plus" wire:click="abrir('nueva-seccion')">{{ __('Nuevo grupo teórico') }}</x-ui.button>
            @endif
        </x-slot:acciones>
    </x-ui.encabezado>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat :etiqueta="__('Grupos planificados')" :valor="$kpi['secciones']" icono="squares-2x2" />
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

        <x-ui.table ajustar :vacio="__('No hay grupos teóricos con esos filtros.')">
            <x-slot:cabecera><th>{{ __('Asignatura / grupo') }}</th><th>{{ __('Docentes') }}</th><th>{{ __('Horario y aula') }}</th><th class="text-right">{{ __('Cupo') }}</th><th>{{ __('Estado') }}</th><th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
            @foreach ($this->secciones as $s)
                <tr wire:key="carga-{{ $s->id }}" @class(['bg-red-50/40' => $s->estado === 'choque'])>
                    <td><span class="font-semibold">{{ $s->code }}</span> · {{ $s->name }}<p class="text-xs text-texto-suave">{{ __('Grupo :g', ['g' => $s->grupo]) }}{{ $s->ciclo ? ' · '.__('ciclo :c', ['c' => $s->ciclo]) : '' }}</p></td>
                    <td>
                        @forelse ($s->docentes as $d)
                            <span class="flex items-center gap-1">{{ $d->first_name }} {{ $d->last_name }}@unless ($d->is_responsible)<span class="text-xs text-texto-suave">({{ __('co-docente') }})</span>@endunless
                                @if ($asignaDocentes)<button type="button" wire:click="quitarDocente({{ $s->id }}, {{ $d->id }})" wire:confirm="{{ __('¿Quitar a este docente del grupo teórico?') }}" class="text-texto-suave hover:text-red-700" aria-label="{{ __('Quitar docente') }}"><x-ui.icon name="x-mark" class="size-4" /></button>@endif</span>
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

    @if ($crea)
        <x-ui.modal nombre="nueva-seccion" :titulo="__('Nuevo grupo teórico')">
            <form wire:submit="crearSeccion" id="form-seccion" class="flex flex-col gap-4">
                {{-- Administrador: departamento → carrera → materia. Jefe y Coordinador: carrera → materia de su alcance. --}}
                @if ($eligeDepartamento)
                    <x-ui.field :etiqueta="__('Departamento')" para="departamentoGrupo" error="departamentoGrupo" requerido>
                        <x-ui.select id="departamentoGrupo" wire:model.live="departamentoGrupo"><option value="">{{ __('Elija…') }}</option>@foreach ($departamentosGrupo as $d)<option value="{{ $d->id }}">{{ $d->code }} · {{ $d->name }}</option>@endforeach</x-ui.select>
                    </x-ui.field>
                @endif
                {{-- ADR-080: la carrera solo filtra las materias y queda como dato; el grupo lo comparten todas las carreras que llevan la materia. --}}
                <x-ui.field :etiqueta="__('Carrera que lo solicita')" para="carreraGrupo" error="carreraGrupo" requerido
                    :ayuda="__('El grupo lo pueden cursar los estudiantes de todas las carreras que llevan la materia en su plan.')">
                    <x-ui.select id="carreraGrupo" wire:model.live="carreraGrupo" :disabled="$carrerasGrupo->isEmpty()"><option value="">{{ __('Elija…') }}</option>@foreach ($carrerasGrupo as $c)<option value="{{ $c->id }}">{{ $c->code }} · {{ $c->name }}</option>@endforeach</x-ui.select>
                </x-ui.field>
                <x-ui.field :etiqueta="__('Asignatura')" para="asignaturaId" error="asignaturaId" requerido
                    :ayuda="$carreraGrupo && $asignaturas->isEmpty() ? __('Esta carrera no tiene materias activas en sus planes de estudio que usted pueda programar.') : null">
                    <x-ui.select id="asignaturaId" wire:model="asignaturaId" :disabled="! $carreraGrupo || $asignaturas->isEmpty()"><option value="">{{ __('Elija…') }}</option>@foreach ($asignaturas as $a)<option value="{{ $a->id }}">{{ $a->code }} · {{ $a->name }}</option>@endforeach</x-ui.select>
                </x-ui.field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field :etiqueta="__('Grupo')" para="grupo" error="grupo" requerido><x-ui.input id="grupo" wire:model="grupo" /></x-ui.field>
                    <x-ui.field :etiqueta="__('Cupo máximo')" para="cupo" error="cupo" requerido><x-ui.input id="cupo" type="number" wire:model="cupo" /></x-ui.field>
                </div>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'nueva-seccion')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-seccion">{{ __('Crear grupo teórico') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif

    @if ($gestiona)

        <x-ui.modal nombre="horario" :titulo="__('Horario').($actual ? ' · '.$actual->code.' · '.$actual->grupo : '')"
            :subtitulo="__('Cada cambio avisa a los estudiantes matriculados y a los docentes del grupo teórico.')">
            <div class="flex flex-col gap-5">
                {{-- Bloques actuales (RF08): el aula se cambia en la misma fila. --}}
                <section class="flex flex-col gap-2">
                    <h3 class="text-sm font-semibold text-texto">{{ __('Bloques actuales') }}</h3>
                    @if ($actual && $actual->bloques->isNotEmpty())
                        @if ($actual->choque)
                            <x-ui.alert tipo="aviso">{{ __('El aula de un bloque ya la ocupa :g a esa hora: cámbiela.', ['g' => $actual->choque]) }}</x-ui.alert>
                        @endif
                        <ul class="divide-y divide-borde rounded-lg ring-1 ring-borde">
                            @foreach ($actual->bloques as $b)
                                <li wire:key="bloque-{{ $b->id }}" class="flex flex-col gap-2 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between">
                                    <span class="flex items-center gap-2 text-sm font-medium text-texto">
                                        <x-ui.icon name="clock" class="size-4 shrink-0 text-texto-suave" />{{ $b->etiqueta }}
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <div class="min-w-0 flex-1 sm:w-48 sm:flex-none">
                                            <x-ui.select :aria-label="__('Aula de :b', ['b' => $b->etiqueta])" wire:change="cambiarAula({{ $b->id }}, $event.target.value)">
                                                <option value="" @selected($b->aulaId === null)>{{ __('Virtual / sin aula') }}</option>
                                                @foreach ($aulas as $a)<option value="{{ $a->id }}" @selected($b->aulaId === (int) $a->id)>{{ $a->code }} ({{ $a->capacity }})</option>@endforeach
                                            </x-ui.select>
                                        </div>
                                        <button type="button" wire:click="quitarHorario({{ $b->id }})" wire:confirm="{{ __('¿Quitar este horario? Se avisará a los estudiantes y docentes del grupo teórico.') }}"
                                            class="inline-flex h-10 shrink-0 items-center gap-1 rounded-lg px-2 text-sm text-texto-suave hover:bg-red-50 hover:text-red-700" aria-label="{{ __('Quitar :b', ['b' => $b->etiqueta]) }}">
                                            <x-ui.icon name="x-mark" class="size-5" /><span class="sm:sr-only">{{ __('Quitar') }}</span>
                                        </button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="rounded-lg border border-dashed border-borde px-4 py-4 text-center text-sm text-texto-suave">{{ __('Aún no tiene horario. Agregue el primer bloque abajo.') }}</p>
                    @endif
                </section>

                {{-- Nuevo bloque: el día con botones y la duración calculada mientras se escribe. --}}
                <form wire:submit="agregarHorario" id="form-horario" class="flex flex-col gap-4 rounded-lg bg-zinc-50 p-4 ring-1 ring-borde"
                    x-data="{ get duracion() {
                        const [hi, mi] = ($wire.inicio || '').split(':').map(Number), [hf, mf] = ($wire.fin || '').split(':').map(Number);
                        const m = (hf * 60 + mf) - (hi * 60 + mi);
                        return Number.isFinite(m) && m > 0 ? (m >= 60 ? Math.floor(m / 60) + ' h ' : '') + (m % 60 ? (m % 60) + ' min' : '') : null;
                    } }">
                    <h3 class="text-sm font-semibold text-texto">{{ __('Agregar un bloque') }}</h3>
                    <fieldset>
                        <legend class="mb-1.5 text-sm font-medium text-zinc-800">{{ __('Día') }}</legend>
                        <div class="grid grid-cols-6 gap-1.5">
                            @foreach ($dias as $n => $d)
                                <label wire:key="dia-{{ $n }}" @class(['flex h-10 cursor-pointer items-center justify-center rounded-lg text-sm font-medium ring-1 transition', 'bg-marca-600 text-white ring-marca-600' => (int) $dia === (int) $n, 'bg-white text-texto ring-borde hover:bg-zinc-100' => (int) $dia !== (int) $n]) title="{{ $d }}">
                                    <input type="radio" wire:model.live="dia" value="{{ $n }}" class="sr-only">{{ mb_substr($d, 0, 3) }}
                                </label>
                            @endforeach
                        </div>
                        @error('dia')<p class="mt-1 text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                    </fieldset>
                    <div class="grid grid-cols-2 gap-4">
                        <x-ui.field :etiqueta="__('Inicio')" para="inicio" error="inicio"><x-ui.input id="inicio" type="time" wire:model.live="inicio" /></x-ui.field>
                        <x-ui.field :etiqueta="__('Fin')" para="fin" error="fin"><x-ui.input id="fin" type="time" wire:model.live="fin" /></x-ui.field>
                    </div>
                    <p class="-mt-2 text-xs" :class="duracion ? 'text-texto-suave' : 'text-red-700'" x-text="duracion ? @js(__('Duración: ')) + duracion : @js(__('El fin debe ser después del inicio.'))"></p>
                    <x-ui.field :etiqueta="__('Aula')" para="aulaId" error="aula" :ayuda="__('Se rechaza si el aula o el docente ya están ocupados en ese horario.')">
                        <x-ui.select id="aulaId" wire:model="aulaId"><option value="">{{ __('Virtual / sin aula') }}</option>@foreach ($aulas as $a)<option value="{{ $a->id }}">{{ $a->code }} ({{ $a->capacity }})</option>@endforeach</x-ui.select>
                    </x-ui.field>
                </form>
            </div>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'horario')">{{ __('Cerrar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-horario" icono="plus">{{ __('Agregar bloque') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif

    <x-ui.modal nombre="docente" :titulo="__('Asignar docente').($actual ? ' · '.$actual->code.' · '.$actual->grupo : '')">
        <form wire:submit="asignarDocente" id="form-docente" class="flex flex-col gap-4">
            <x-ui.field :etiqueta="__('Docente')" para="docenteId" error="docente" :ayuda="__('Entre paréntesis, los grupos teóricos que ya tiene en el período.')" requerido>
                <x-ui.select id="docenteId" wire:model="docenteId"><option value="">{{ __('Elija…') }}</option>@foreach ($docentes as $d)<option value="{{ $d->id }}">{{ $d->last_name }}, {{ $d->first_name }} · {{ $d->departamento }} ({{ $d->carga }})</option>@endforeach</x-ui.select>
            </x-ui.field>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="responsable" class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600"> {{ __('Responsable del grupo teórico') }}</label>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'docente')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-docente">{{ __('Asignar') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>

    @if ($cambiaCupo)
        <x-ui.modal nombre="cupo" :titulo="__('Cupo del grupo teórico').($actual ? ' · '.$actual->code.' · '.$actual->grupo : '')">
            <form wire:submit="cambiarCupo" id="form-cupo" class="flex flex-col gap-4">
                <x-ui.field :etiqueta="__('Cupo máximo')" para="cupoNuevo" error="cupoNuevo"
                    :ayuda="$actual ? __('Inscritos: :n · mínimo del grupo: :m', ['n' => $actual->inscritos, 'm' => $actual->min_capacity]) : null" requerido>
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
