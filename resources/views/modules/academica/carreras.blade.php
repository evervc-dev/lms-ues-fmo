{{-- Carreras del ámbito del actor (RF07, RF08). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="$departamentoElegido ? __('Carreras de :d', ['d' => $departamentoElegido->code]) : __('Carreras')"
        :subtitulo="$departamentoElegido
            ? __(':nombre: sus carreras, con su coordinador y su oferta del período en curso.', ['nombre' => $departamentoElegido->name])
            : __('Carreras de su ámbito, con su coordinador y su oferta del período en curso.')">
        @if ($departamentoElegido)
            <x-ui.button variante="secundario" icono="building-library" :href="route('departamentos')">{{ __('Todos los departamentos') }}</x-ui.button>
        @endif
    </x-ui.encabezado>

    @if ($carreras->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icono="academic-cap" :titulo="__('No hay carreras')">
                {{ $departamentoElegido ? __('Este departamento todavía no tiene carreras.') : __('No tiene carreras asignadas en su departamento ni en su coordinación.') }}
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card :titulo="__('Carreras')" :descripcion="__('Entre a una carrera para ver sus docentes, materias, grupos teóricos y horarios.')">
            <x-ui.table>
                <x-slot:cabecera>
                    <th>{{ __('Carrera') }}</th>
                    <th>{{ __('Departamento') }}</th>
                    <th>{{ __('Coordinador') }}</th>
                    <th class="text-right">{{ __('Materias') }}</th>
                    <th class="text-right">{{ __('Grupos') }}</th>
                    <th class="text-right">{{ __('Docentes') }}</th>
                </x-slot:cabecera>
                @foreach ($carreras as $carrera)
                    {{-- Sin grupos este período se atenúa y se rotula: ICI con 0 en todo parecía un error de datos (QA Jefe 21/11). --}}
                    <tr wire:key="carrera-{{ $carrera->id }}" @class(['text-texto-suave' => $carrera->grupos === 0])>
                        <td>
                            <a href="{{ route('carreras.show', ['carrera' => $carrera->id]) }}" wire:navigate class="font-medium text-marca-700 hover:underline">
                                <span class="font-semibold">{{ $carrera->code }}</span> · {{ $carrera->name }}
                            </a>
                            @if ($carrera->materias === 0)
                                <x-ui.badge class="ml-1">{{ __('Sin materias en su plan') }}</x-ui.badge>
                            @elseif ($carrera->grupos === 0)
                                <x-ui.badge class="ml-1">{{ __('Sin oferta este período') }}</x-ui.badge>
                            @endif
                        </td>
                        <td>{{ $carrera->departamentoCodigo }}</td>
                        <td>
                            @if ($carrera->coordinador)
                                {{ $carrera->coordinador }}
                            @else
                                <x-ui.badge color="ambar">{{ __('Sin coordinador') }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="text-right">{{ $carrera->materias }}</td>
                        <td class="text-right">{{ $carrera->grupos }}</td>
                        <td class="text-right">{{ $carrera->docentes }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
