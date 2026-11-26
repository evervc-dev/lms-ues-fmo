{{-- Detalle de una carrera: coordinador, docentes, materias con sus grupos teóricos y horarios (RF07, RF08). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="$carrera->name"
        :subtitulo="$periodo ? __('Período en curso: :periodo', ['periodo' => $periodo]) : __('No hay un período en curso.')">
        <div class="mt-2 flex flex-wrap gap-1">
            <x-ui.badge color="marca">{{ $carrera->code }}</x-ui.badge>
            <x-ui.badge color="azul">{{ $carrera->departamento }}</x-ui.badge>
            @foreach ($planes as $plan)
                <x-ui.badge>{{ $plan }}</x-ui.badge>
            @endforeach
        </div>
    </x-ui.encabezado>

    {{-- Una sola columna (revisión UX del 22/11/2026): el coordinador iba en una tarjeta propia que la cuadrícula estiraba
         hasta el alto de la tabla de docentes y quedaba un bloque vacío. Ahora es parte del resumen, y las listas van en
         pestañas a ancho completo. --}}
    @php($totalGrupos = $materias->sum(fn ($m) => count($m->grupos)))
    <x-ui.card>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="min-w-0">
                <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Coordinador de carrera') }}</dt>
                @if ($coordinador)
                    <dd class="mt-1 font-medium text-texto">{{ $coordinador->nombre }}</dd>
                    <dd class="truncate text-xs text-texto-suave" title="{{ $coordinador->correo }}">{{ $coordinador->correo }} · {{ __('desde el :fecha', ['fecha' => \Illuminate\Support\Carbon::parse($coordinador->desde)->format('d/m/Y')]) }}</dd>
                @else
                    <dd class="mt-1 text-sm text-texto-suave">{{ __('Sin coordinador vigente') }}</dd>
                @endif
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Materias del plan') }}</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums text-texto">{{ $materias->count() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Grupos del período') }}</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums text-texto">{{ $totalGrupos }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Docentes del período') }}</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums text-texto">{{ $docentes->count() }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <div x-data="{ pestana: 'materias', buscar: '' }" class="flex flex-col gap-4">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-borde">
            <nav class="-mb-px flex gap-1" aria-label="{{ __('Secciones de la carrera') }}">
                @foreach (['materias' => __('Materias y grupos'), 'docentes' => __('Docentes')] as $clave => $etiqueta)
                    <button type="button" x-on:click="pestana = @js($clave)" :aria-current="pestana === @js($clave) ? 'page' : null"
                        :class="pestana === @js($clave) ? 'border-marca-600 text-marca-700' : 'border-transparent text-texto-suave hover:text-texto'"
                        class="border-b-2 px-4 py-2.5 text-sm font-medium transition">{{ $etiqueta }}</button>
                @endforeach
            </nav>
            <div x-show="pestana === 'docentes'" x-cloak class="mb-2 w-full sm:w-72">
                <x-ui.search x-model="buscar" :placeholder="__('Nombre o correo…')" />
            </div>
        </div>

        <div x-show="pestana === 'docentes'" x-cloak>
            <x-ui.card>
                <x-ui.table :vacio="__('Ningún docente tiene grupos de la carrera en el período en curso.')">
                    <x-slot:cabecera>
                        <th>{{ __('Docente') }}</th>
                        <th>{{ __('Correo') }}</th>
                        <th class="text-right">{{ __('Grupos') }}</th>
                    </x-slot:cabecera>
                    @foreach ($docentes as $docente)
                        <tr wire:key="docente-{{ $docente->id }}" x-show="! buscar || @js(mb_strtolower($docente->nombre.' '.$docente->correo)).includes(buscar.toLowerCase())">
                            <td class="font-medium">{{ $docente->nombre }}</td>
                            <td class="text-texto-suave">{{ $docente->correo }}</td>
                            <td class="text-right tabular-nums">{{ $docente->grupos }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </x-ui.card>
        </div>

        <div x-show="pestana === 'materias'">
    <x-ui.card :descripcion="__('Materias del plan de estudios con sus grupos del período en curso.')">
        <x-ui.table :vacio="__('La carrera no tiene materias en su plan de estudios.')">
            <x-slot:cabecera>
                <th>{{ __('Grupo') }}</th>
                <th>{{ __('Docente') }}</th>
                <th>{{ __('Horario') }}</th>
                <th class="text-right">{{ __('Cupo') }}</th>
            </x-slot:cabecera>
            @foreach ($materias as $materia)
                <tr class="bg-zinc-50" wire:key="materia-{{ $materia->id }}">
                    <td colspan="4">
                        @if (in_array((int) $materia->id, $editables, true))
                            <a href="{{ route('catalogo.asignaturas.editar', ['asignatura' => $materia->code]) }}" wire:navigate class="text-marca-700 hover:underline"><span class="font-semibold">{{ $materia->code }}</span> · {{ $materia->name }}</a>
                        @else
                            <span class="font-semibold">{{ $materia->code }}</span> · {{ $materia->name }}
                        @endif
                        <span class="ml-1 text-xs text-texto-suave">{{ __('Ciclo :n · :c créditos', ['n' => $materia->ciclo ?? '—', 'c' => $materia->credits]) }}</span>
                        @if (! $materia->is_active)
                            <x-ui.badge class="ml-1">{{ __('Inactiva') }}</x-ui.badge>
                        @endif
                    </td>
                </tr>
                @forelse ($materia->grupos as $grupo)
                    <tr wire:key="grupo-{{ $grupo->id }}">
                        <td>{{ __('Grupo :g', ['g' => $grupo->grupo]) }}</td>
                        <td>{{ $grupo->docentes ? implode(', ', $grupo->docentes) : __('Sin docente') }}</td>
                        <td>
                            @if ($grupo->horario)
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($grupo->horario as $bloque)
                                        <x-ui.badge color="azul">{{ $bloque }}</x-ui.badge>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-texto-suave">{{ __('Sin horario') }}</span>
                            @endif
                        </td>
                        <td class="text-right">{{ __(':n de :m', ['n' => $grupo->inscritos, 'm' => $grupo->max_capacity]) }}</td>
                    </tr>
                @empty
                    <tr wire:key="materia-{{ $materia->id }}-sin-grupos">
                        <td colspan="4" class="text-texto-suave">{{ __('Sin grupos en el período en curso.') }}</td>
                    </tr>
                @endforelse
            @endforeach
        </x-ui.table>
    </x-ui.card>
        </div>
    </div>
</div>
