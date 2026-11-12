{{-- Carreras del ámbito del actor (RF07, RF08). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Carreras')"
        :subtitulo="__('Carreras de su ámbito, con su coordinador y su oferta del período en curso.')" />

    @if ($carreras->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icono="academic-cap" :titulo="__('No hay carreras')">
                {{ __('No tiene carreras asignadas en su departamento ni en su coordinación.') }}
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
                    <tr wire:key="carrera-{{ $carrera->id }}">
                        <td>
                            <a href="{{ route('carreras.show', ['carrera' => $carrera->id]) }}" wire:navigate class="font-medium text-marca-700 hover:underline">
                                <span class="font-semibold">{{ $carrera->code }}</span> · {{ $carrera->name }}
                            </a>
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
