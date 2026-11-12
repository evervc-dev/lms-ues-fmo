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

    <div class="grid gap-5 lg:grid-cols-3">
        <x-ui.card :titulo="__('Coordinador de carrera')">
            @if ($coordinador)
                <p class="font-medium text-texto">{{ $coordinador->nombre }}</p>
                <p class="text-sm text-texto-suave">{{ $coordinador->correo }}</p>
                <p class="mt-2 text-xs text-texto-suave">{{ __('Nombrado el :fecha', ['fecha' => \Illuminate\Support\Carbon::parse($coordinador->desde)->format('d/m/Y')]) }}</p>
            @else
                <p class="text-sm text-texto-suave">{{ __('La carrera no tiene un coordinador vigente.') }}</p>
            @endif
        </x-ui.card>

        <x-ui.card class="lg:col-span-2" :titulo="__('Docentes')"
            :descripcion="__('Docentes con grupos de la carrera en el período en curso.')">
            <x-ui.table :vacio="__('Ningún docente tiene grupos de la carrera en el período en curso.')">
                <x-slot:cabecera>
                    <th>{{ __('Docente') }}</th>
                    <th>{{ __('Correo') }}</th>
                    <th class="text-right">{{ __('Grupos') }}</th>
                </x-slot:cabecera>
                @foreach ($docentes as $docente)
                    <tr wire:key="docente-{{ $docente->id }}">
                        <td class="font-medium">{{ $docente->nombre }}</td>
                        <td class="text-texto-suave">{{ $docente->correo }}</td>
                        <td class="text-right">{{ $docente->grupos }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    </div>

    <x-ui.card :titulo="__('Materias, grupos teóricos y horarios')"
        :descripcion="__('Materias del plan de estudios con sus grupos del período en curso.')">
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
                        <span class="font-semibold">{{ $materia->code }}</span> · {{ $materia->name }}
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
