{{-- Departamentos, carreras, planes y aulas (RF07). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Departamentos, carreras, planes y aulas')" :subtitulo="__('Estructura institucional del catálogo.')" />

    <div class="grid gap-5 lg:grid-cols-2">
        <x-ui.card :titulo="__('Departamentos')">
            <x-ui.table>
                <x-slot:cabecera><th>{{ __('Código') }}</th><th>{{ __('Nombre') }}</th><th class="text-right">{{ __('Asignaturas') }}</th></x-slot:cabecera>
                @foreach ($departamentos as $d)
                    <tr><td class="font-semibold">{{ $d->code }}</td><td>{{ $d->name }}</td><td class="text-right">{{ $d->asignaturas }}</td></tr>
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

    <x-ui.card :titulo="__('Aulas')" :descripcion="__('Se usan en los horarios de las secciones; el sistema evita choques de aula.')">
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
</div>
