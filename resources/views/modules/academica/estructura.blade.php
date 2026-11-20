{{-- Departamentos, carreras, planes y aulas (RF07). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Departamentos, carreras, planes y aulas')" :subtitulo="__('Estructura institucional del catálogo.')">
        <x-slot:acciones>
            <x-ui.button icono="building-library" :href="route('catalogo.departamentos')">{{ __('Panel académico') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.encabezado>

    <div class="grid gap-5 lg:grid-cols-2">
        <x-ui.card :titulo="__('Departamentos')" :descripcion="__('Un departamento archivado deja de ofrecerse a docentes y estudiantes; su historial se conserva.')">
            <x-slot:acciones>
                <x-ui.button tamano="sm" icono="plus" wire:click="nuevoDepartamento">{{ __('Nuevo departamento') }}</x-ui.button>
            </x-slot:acciones>
            <x-ui.table>
                <x-slot:cabecera><th>{{ __('Código') }}</th><th>{{ __('Nombre') }}</th><th class="text-right">{{ __('Asignaturas') }}</th><th class="text-right"><span class="sr-only">{{ __('Acciones') }}</span></th></x-slot:cabecera>
                @foreach ($departamentos as $d)
                    <tr wire:key="depto-{{ $d->id }}" @class(['text-texto-suave' => ! $d->is_active])>
                        <td class="font-semibold">{{ $d->code }}</td>
                        <td>{{ $d->name }}@unless ($d->is_active) <x-ui.badge>{{ __('Archivado') }}</x-ui.badge>@endunless</td>
                        <td class="text-right">{{ $d->asignaturas }}</td>
                        <td class="text-right whitespace-nowrap">
                            <x-ui.button tamano="sm" variante="fantasma" wire:click="editarDepartamento({{ $d->id }})">{{ __('Editar') }}</x-ui.button>
                            @if ($d->is_active)
                                <x-ui.button tamano="sm" variante="fantasma" wire:click="archivarDepartamento({{ $d->id }})" wire:confirm="{{ __('¿Archivar :c? Dejará de ofrecerse a docentes y estudiantes.', ['c' => $d->code]) }}">{{ __('Archivar') }}</x-ui.button>
                            @else
                                <x-ui.button tamano="sm" variante="fantasma" wire:click="reactivarDepartamento({{ $d->id }})">{{ __('Reactivar') }}</x-ui.button>
                            @endif
                            <x-ui.button tamano="sm" variante="fantasma" wire:click="eliminarDepartamento({{ $d->id }})" wire:confirm="{{ __('¿Eliminar :c? Solo se puede si no tiene carreras, asignaturas, docentes, jefaturas ni historial.', ['c' => $d->code]) }}">{{ __('Eliminar') }}</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>

        <x-ui.card :titulo="__('Carreras y planes')">
            <x-ui.table>
                <x-slot:cabecera><th>{{ __('Carrera') }}</th><th>{{ __('Depto.') }}</th><th>{{ __('Planes') }}</th><th class="text-right">{{ __('Estudiantes') }}</th></x-slot:cabecera>
                @foreach ($carreras as $c)
                    <tr><td><span class="font-semibold">{{ $c->code }}</span> · {{ $c->name }}</td><td>{{ $c->departamento }}</td>
                        <td><div class="flex flex-wrap gap-1">@foreach ($c->planes as $p)<x-ui.badge>{{ $p }}</x-ui.badge>@endforeach</div></td>
                        <td class="text-right">{{ $c->estudiantes }}</td></tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    </div>

    <x-ui.card :titulo="__('Aulas')" :descripcion="__('Se usan en los horarios de los grupos teóricos; el sistema evita choques de aula.')">
        <form wire:submit="agregarAula" class="mb-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <x-ui.field :etiqueta="__('Código')" para="aulaCodigo" error="aulaCodigo"><x-ui.input id="aulaCodigo" wire:model="aulaCodigo" placeholder="B-206" /></x-ui.field>
            <x-ui.field :etiqueta="__('Capacidad')" para="aulaCapacidad" error="aulaCapacidad"><x-ui.input id="aulaCapacidad" type="number" wire:model="aulaCapacidad" /></x-ui.field>
            <x-ui.button type="submit" icono="plus">{{ __('Agregar aula') }}</x-ui.button>
        </form>
        <div class="flex flex-wrap gap-2">
            @foreach ($aulas as $a)
                <span class="rounded-lg px-3 py-1.5 text-sm ring-1 ring-borde"><span class="font-semibold">{{ $a->code }}</span> · {{ $a->capacity }}</span>
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.modal nombre="departamento" :titulo="$departamentoEditando ? __('Editar departamento') : __('Nuevo departamento')">
        <form wire:submit="guardarDepartamento" id="form-departamento" class="grid gap-4 sm:grid-cols-[1fr_2fr]">
            <x-ui.field :etiqueta="__('Código')" para="departamentoCodigo" error="departamentoCodigo" requerido><x-ui.input id="departamentoCodigo" wire:model="departamentoCodigo" placeholder="DIA" /></x-ui.field>
            <x-ui.field :etiqueta="__('Nombre')" para="departamentoNombre" error="departamentoNombre" requerido><x-ui.input id="departamentoNombre" wire:model="departamentoNombre" /></x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'departamento')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-departamento">{{ __('Guardar') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
