{{-- Catálogo de asignaturas (RF07). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Catálogo de asignaturas')" :subtitulo="__('Código, créditos, carreras donde se lleva y prerrequisitos (informativos, no se exigen al matricular).')">
        <x-slot:acciones>
            @if ($gestionaEstructura)
                <x-ui.button variante="secundario" icono="building-library" :href="route('catalogo.estructura')">{{ __('Departamentos, carreras y aulas') }}</x-ui.button>
            @endif
            <x-ui.button icono="plus" :href="route('catalogo.asignaturas.crear')">{{ __('Nueva asignatura') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.encabezado>

    <x-ui.card ajustar>
        <div class="mb-4 grid gap-3 md:grid-cols-[1fr_auto_auto]">
            <x-ui.search wire:model.live.debounce.300ms="buscar" :placeholder="__('Buscar por código o nombre…')" />
            {{-- Solo las carreras de su alcance: todas para el Administrador, las de su departamento para el Jefe. --}}
            <x-ui.select wire:model.live="carrera" :aria-label="__('Carrera')">
                <option value="">{{ __('Todas las carreras') }}</option>
                @foreach ($carreras as $c)<option value="{{ $c->id }}">{{ $c->code }} · {{ $c->name }}</option>@endforeach
            </x-ui.select>
            <x-ui.select wire:model.live="filtroCreditos" :aria-label="__('Créditos')">
                <option value="">{{ __('Todos los créditos') }}</option>
                @foreach ($valoresCreditos as $c)<option value="{{ $c }}">{{ trans_choice('{1} :count crédito|[2,*] :count créditos', $c) }}</option>@endforeach
            </x-ui.select>
        </div>
        <x-ui.table ajustar :vacio="__('No hay asignaturas con esos filtros.')">
            <x-slot:cabecera><th>{{ __('Código') }}</th><th>{{ __('Asignatura') }}</th>@if ($veDepartamento)<th>{{ __('Depto.') }}</th>@endif<th>{{ __('Carreras') }}</th><th class="text-right">{{ __('Créditos') }}</th><th>{{ __('Prerrequisitos') }}</th><th class="text-right">{{ __('Grupos') }}</th><th></th></x-slot:cabecera>
            @foreach ($asignaturas as $a)
                <tr wire:key="asig-{{ $a->id }}" @class(['text-texto-suave' => ! $a->is_active])>
                    <td class="font-mono text-xs font-semibold">{{ $a->code }}</td>
                    <td>{{ $a->name }}@unless ($a->is_active) <x-ui.badge>{{ __('Inactiva') }}</x-ui.badge>@endunless</td>
                    @if ($veDepartamento)<td>{{ $a->departamento }}</td>@endif
                    <td><div class="flex flex-wrap gap-1">@forelse ($carrerasDe[$a->id] ?? [] as $c)<x-ui.badge color="marca">{{ $c }}</x-ui.badge>@empty<span class="text-texto-suave">—</span>@endforelse</div></td>
                    <td class="text-right">{{ $a->credits }}</td>
                    <td><div class="flex flex-wrap gap-1">@forelse ($prerrequisitosDe[$a->id] ?? [] as $p)<x-ui.badge>{{ $p }}</x-ui.badge>@empty<span class="text-texto-suave">—</span>@endforelse</div></td>
                    <td class="text-right">{{ $a->secciones }}</td>
                    <td class="text-right whitespace-nowrap">
                        <x-ui.button tamano="sm" variante="fantasma" :href="route('catalogo.asignaturas.editar', ['asignatura' => $a->code])">{{ __('Editar') }}</x-ui.button>
                        @php($dependientes = $a->is_active ? ($dependientesDe[$a->id] ?? collect())->pluck('code') : collect())
                        @if ($dependientes->isNotEmpty())
                            <x-ui.button tamano="sm" variante="fantasma" wire:click="alternarEstado({{ $a->id }})" wire:confirm="{{ __('¿Desactivar :c? También se desactivarán las que dependen de ella: :d.', ['c' => $a->code, 'd' => $dependientes->implode(', ')]) }}">{{ __('Desactivar') }}</x-ui.button>
                        @else
                            <x-ui.button tamano="sm" variante="fantasma" wire:click="alternarEstado({{ $a->id }})">{{ $a->is_active ? __('Desactivar') : __('Reactivar') }}</x-ui.button>
                        @endif
                        <x-ui.button tamano="sm" variante="fantasma" wire:click="eliminar({{ $a->id }})" wire:confirm="{{ __('¿Eliminar :c? Solo se puede si ningún grupo teórico, plan ni asignatura la usa.', ['c' => $a->code]) }}">{{ __('Eliminar') }}</x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        {{ $asignaturas->links() }}
    </x-ui.card>
</div>
