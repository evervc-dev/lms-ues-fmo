{{-- Departamentos de la universidad para el Administrador (RF07, ADR-083). Con asignaturas.gestionar G también los
     crea, edita y elimina aquí: antes solo se podía en Catálogo › Estructura y no se encontraba (reporte del 22/11/2026). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Departamentos')"
        :subtitulo="__('Departamentos de la universidad, con su jefe y su oferta del período en curso. Entre a uno para ver sus carreras.')">
        @if ($gestiona)
            <x-slot:acciones>
                <x-ui.button icono="plus" wire:click="nuevoDepartamento">{{ __('Nuevo departamento') }}</x-ui.button>
            </x-slot:acciones>
        @endif
    </x-ui.encabezado>

    @if ($departamentos->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icono="building-library" :titulo="__('No hay departamentos')">
                {{ $gestiona ? __('Todavía no hay departamentos registrados. Cree el primero con «Nuevo departamento».') : __('Todavía no hay departamentos registrados.') }}
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
                    @if ($gestiona)<th class="text-right"><span class="sr-only">{{ __('Acciones') }}</span></th>@endif
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
                        @if ($gestiona)
                            <td class="whitespace-nowrap text-right">
                                <x-ui.button tamano="sm" variante="fantasma" icono="pencil-square" wire:click="editarDepartamento({{ $departamento->id }})">{{ __('Editar') }}</x-ui.button>
                                {{-- Solo se ofrece eliminar el que nada usa; el Service igual lo verifica (CP-RF07-10). --}}
                                @if ($departamento->carreras === 0 && $departamento->materias === 0 && ! $departamento->jefe)
                                    <x-ui.button tamano="sm" variante="fantasma" wire:click="eliminarDepartamento({{ $departamento->id }})" wire:confirm="{{ __('¿Eliminar :c? Solo se puede si no tiene carreras, asignaturas, docentes, jefaturas ni historial.', ['c' => $departamento->code]) }}">{{ __('Eliminar') }}</x-ui.button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif

    @if ($gestiona)
        @include('modules.academica.partials.departamento-modal')
    @endif
</div>
