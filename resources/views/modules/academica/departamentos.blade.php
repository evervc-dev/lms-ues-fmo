{{-- Departamentos de la universidad para el Administrador (RF07, ADR-083). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Departamentos')"
        :subtitulo="__('Departamentos de la universidad, con su jefe y su oferta del período en curso. Entre a uno para ver sus carreras.')" />

    @if ($departamentos->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icono="building-library" :titulo="__('No hay departamentos')">
                {{ __('Todavía no hay departamentos registrados. Puede crearlos en Catálogo › Departamentos, carreras y aulas.') }}
            </x-ui.empty-state>
        </x-ui.card>
    @else
        {{-- Sin título propio: repetía el de la página (revisión UX del 22/11/2026). --}}
        <x-ui.card>
            <x-ui.table>
                <x-slot:cabecera>
                    <th>{{ __('Departamento') }}</th>
                    <th>{{ __('Jefe') }}</th>
                    <th class="text-right">{{ __('Carreras') }}</th>
                    <th class="text-right">{{ __('Materias') }}</th>
                    <th class="text-right">{{ __('Grupos') }}</th>
                </x-slot:cabecera>
                @foreach ($departamentos as $departamento)
                    <tr wire:key="departamento-{{ $departamento->id }}">
                        <td>
                            <a href="{{ route('carreras', ['departamento' => $departamento->id]) }}" wire:navigate class="font-medium text-marca-700 hover:underline">
                                <span class="font-semibold">{{ $departamento->code }}</span> · {{ $departamento->name }}
                            </a>
                        </td>
                        <td>
                            @if ($departamento->jefe)
                                {{ $departamento->jefe }}
                            @else
                                <x-ui.badge color="ambar">{{ __('Sin jefe') }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="text-right">{{ $departamento->carreras }}</td>
                        <td class="text-right">{{ $departamento->materias }}</td>
                        <td class="text-right">{{ $departamento->grupos }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
