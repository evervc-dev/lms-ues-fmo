{{-- Panel académico del Administrador (RF07, ADR-075, ADR-076): departamentos → carreras → materias del plan → grupos teóricos. --}}
<div class="flex flex-col gap-5">
    @if ($nivel === 'departamentos')
        <x-ui.encabezado :titulo="__('Panel académico')" :subtitulo="__('Elija un departamento para ver sus carreras, las materias del plan y sus grupos teóricos.')">
            <x-slot:acciones>
                <x-ui.button variante="secundario" icono="building-library" :href="route('catalogo.estructura')">{{ __('Departamentos, carreras y aulas') }}</x-ui.button>
            </x-slot:acciones>
        </x-ui.encabezado>

        <x-ui.card :titulo="__('Departamentos')" :descripcion="__('Los departamentos se crean, editan, archivan y eliminan en Departamentos, carreras y aulas.')">
            <x-ui.table :vacio="__('Todavía no hay departamentos.')">
                <x-slot:cabecera>
                    <th>{{ __('Código') }}</th>
                    <th>{{ __('Departamento') }}</th>
                    <th class="text-right">{{ __('Carreras') }}</th>
                    <th class="text-right">{{ __('Asignaturas') }}</th>
                    <th class="text-right">{{ __('Grupos vigentes') }}</th>
                </x-slot:cabecera>
                @foreach ($departamentos as $d)
                    <tr wire:key="departamento-{{ $d->id }}" @class(['text-texto-suave' => ! $d->is_active])>
                        <td class="font-mono text-xs font-semibold">{{ $d->code }}</td>
                        <td>
                            <a href="{{ route('catalogo.departamentos.show', ['departamento' => $d->id]) }}" wire:navigate class="font-medium text-marca-700 hover:underline">{{ $d->name }}</a>
                            @unless ($d->is_active) <x-ui.badge>{{ __('Archivado') }}</x-ui.badge>@endunless
                        </td>
                        <td class="text-right">{{ $d->carreras }}</td>
                        <td class="text-right">{{ $d->asignaturas }}</td>
                        <td class="text-right">{{ $d->secciones }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>

    @elseif ($nivel === 'departamento')
        <x-ui.encabezado :titulo="$departamento->name" :subtitulo="__('Carreras del departamento.')">
            <div class="mt-2 flex flex-wrap gap-1">
                <x-ui.badge color="marca">{{ $departamento->code }}</x-ui.badge>
                @unless ($departamento->is_active)<x-ui.badge>{{ __('Archivado') }}</x-ui.badge>@endunless
            </div>
        </x-ui.encabezado>

        <x-ui.card :titulo="__('Carreras')">
            <x-ui.table :vacio="__('El departamento no tiene carreras.')">
                <x-slot:cabecera>
                    <th>{{ __('Carrera') }}</th>
                    <th>{{ __('Planes') }}</th>
                    <th class="text-right">{{ __('Materias') }}</th>
                    <th class="text-right">{{ __('Estudiantes') }}</th>
                </x-slot:cabecera>
                @foreach ($carreras as $c)
                    <tr wire:key="carrera-{{ $c->id }}">
                        <td>
                            <a href="{{ route('catalogo.departamentos.carrera', ['departamento' => $departamento->id, 'carrera' => $c->id]) }}" wire:navigate class="text-marca-700 hover:underline">
                                <span class="font-semibold">{{ $c->code }}</span> · {{ $c->name }}
                            </a>
                        </td>
                        <td><div class="flex flex-wrap gap-1">@forelse ($c->planes as $p)<x-ui.badge>{{ $p }}</x-ui.badge>@empty<span class="text-texto-suave">—</span>@endforelse</div></td>
                        <td class="text-right">{{ $c->materias }}</td>
                        <td class="text-right">{{ $c->estudiantes }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @else
        <x-ui.encabezado :titulo="$carrera->name" :subtitulo="__('Materias del plan de estudios. Elija una para ver sus grupos teóricos.')">
            <div class="mt-2 flex flex-wrap gap-1">
                <x-ui.badge color="marca">{{ $carrera->code }}</x-ui.badge>
                <x-ui.badge color="azul">{{ $carrera->departamentoCodigo }}</x-ui.badge>
            </div>
        </x-ui.encabezado>

        <div class="grid gap-5 lg:grid-cols-2">
            <x-ui.card :titulo="__('Materias del plan')">
                <x-ui.table :vacio="__('La carrera no tiene materias en su plan de estudios.')">
                    <x-slot:cabecera>
                        <th>{{ __('Materia') }}</th>
                        <th class="text-right">{{ __('Ciclo') }}</th>
                        <th class="text-right">{{ __('Créditos') }}</th>
                    </x-slot:cabecera>
                    @foreach ($materias as $m)
                        <tr wire:key="materia-{{ $m->id }}" @class(['bg-marca-50' => $materiaId === $m->id])>
                            <td>
                                <button type="button" wire:click="elegirMateria({{ $m->id }})" class="text-left text-marca-700 hover:underline" aria-pressed="{{ $materiaId === $m->id ? 'true' : 'false' }}">
                                    <span class="font-semibold">{{ $m->code }}</span> · {{ $m->name }}
                                </button>
                                @unless ($m->is_active) <x-ui.badge>{{ __('Inactiva') }}</x-ui.badge>@endunless
                            </td>
                            <td class="text-right">{{ $m->ciclo ?? '—' }}</td>
                            <td class="text-right">{{ $m->credits }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </x-ui.card>

            <x-ui.card :titulo="$materia ? __('Grupos teóricos de :c', ['c' => $materia['asignatura']->code]) : __('Grupos teóricos')"
                :descripcion="__('Cada grupo teórico se abre en solo lectura.')">
                @if ($materia === null)
                    <p class="text-sm text-texto-suave">{{ __('Elija una materia para ver sus grupos teóricos.') }}</p>
                @else
                    <x-ui.field :etiqueta="__('Período')" para="periodoPanel" class="mb-4">
                        <x-ui.select id="periodoPanel" wire:model.live="periodoId">
                            <option value="">{{ __('El período en curso') }}</option>
                            @foreach ($periodos as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                        </x-ui.select>
                    </x-ui.field>
                    @if ($materia['periodo'] === null)
                        <p class="text-sm text-texto-suave">{{ __('No hay un período en curso. Elija un período.') }}</p>
                    @else
                        <x-ui.table :vacio="__('La materia no tiene grupos teóricos en :p.', ['p' => $materia['periodo']->name])">
                            <x-slot:cabecera>
                                <th>{{ __('Grupo') }}</th>
                                <th>{{ __('Docente') }}</th>
                                <th class="text-right">{{ __('Matriculados') }}</th>
                            </x-slot:cabecera>
                            @foreach ($materia['secciones'] as $s)
                                <tr wire:key="seccion-{{ $s->id }}">
                                    <td><a href="{{ route('secciones.contenidos', ['seccion' => $s->ulid]) }}" wire:navigate class="font-medium text-marca-700 hover:underline">{{ __('Grupo :g', ['g' => $s->code]) }}</a></td>
                                    <td>{{ $s->docentes ? implode(', ', $s->docentes) : __('Sin docente') }}</td>
                                    <td class="text-right">{{ __(':n de :m', ['n' => $s->inscritos, 'm' => $s->max_capacity]) }}</td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    @endif
                @endif
            </x-ui.card>
        </div>
    @endif
</div>
