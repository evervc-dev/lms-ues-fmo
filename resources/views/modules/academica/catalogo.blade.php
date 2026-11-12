{{-- Catálogo de asignaturas (RF07). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Catálogo de asignaturas')" :subtitulo="__('Los prerrequisitos se exigen al matricular: el estudiante debe tenerlos aprobados.')">
        <x-slot:acciones>
            <x-ui.button variante="secundario" icono="building-library" :href="route('catalogo.estructura')">{{ __('Departamentos, carreras y aulas') }}</x-ui.button>
            <x-ui.button icono="plus" wire:click="nueva">{{ __('Nueva asignatura') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.encabezado>

    <x-ui.card ajustar>
        <div class="mb-4 grid gap-3 md:grid-cols-[1fr_auto_auto]">
            <x-ui.search wire:model.live.debounce.300ms="buscar" :placeholder="__('Buscar por código o nombre…')" />
            <x-ui.select wire:model.live="departamento" :aria-label="__('Departamento')">
                <option value="">{{ __('Todos los departamentos') }}</option>
                @foreach ($departamentos as $d)<option value="{{ $d->id }}">{{ $d->code }} · {{ $d->name }}</option>@endforeach
            </x-ui.select>
            <x-ui.select wire:model.live="filtroCreditos" :aria-label="__('Créditos')">
                <option value="">{{ __('Todos los créditos') }}</option>
                @foreach ($valoresCreditos as $c)<option value="{{ $c }}">{{ trans_choice('{1} :count crédito|[2,*] :count créditos', $c) }}</option>@endforeach
            </x-ui.select>
        </div>
        <x-ui.table ajustar :vacio="__('No hay asignaturas con esos filtros.')">
            <x-slot:cabecera><th>{{ __('Código') }}</th><th>{{ __('Asignatura') }}</th><th>{{ __('Depto.') }}</th><th class="text-right">{{ __('Créditos') }}</th><th>{{ __('Prerrequisitos') }}</th><th class="text-right">{{ __('Secciones') }}</th><th></th></x-slot:cabecera>
            @foreach ($asignaturas as $a)
                <tr wire:key="asig-{{ $a->id }}" @class(['text-texto-suave' => ! $a->is_active])>
                    <td class="font-mono text-xs font-semibold">{{ $a->code }}</td>
                    <td>{{ $a->name }}@unless ($a->is_active) <x-ui.badge>{{ __('Inactiva') }}</x-ui.badge>@endunless</td>
                    <td>{{ $a->departamento }}</td>
                    <td class="text-right">{{ $a->credits }}</td>
                    <td><div class="flex flex-wrap gap-1">@forelse ($prerrequisitosDe[$a->id] ?? [] as $p)<x-ui.badge>{{ $p }}</x-ui.badge>@empty<span class="text-texto-suave">—</span>@endforelse</div></td>
                    <td class="text-right">{{ $a->secciones }}</td>
                    <td class="text-right whitespace-nowrap">
                        <x-ui.button tamano="sm" variante="fantasma" wire:click="editar({{ $a->id }})">{{ __('Editar') }}</x-ui.button>
                        <x-ui.button tamano="sm" variante="fantasma" wire:click="alternarEstado({{ $a->id }})">{{ $a->is_active ? __('Desactivar') : __('Reactivar') }}</x-ui.button>
                        <x-ui.button tamano="sm" variante="fantasma" wire:click="eliminar({{ $a->id }})" wire:confirm="{{ __('¿Eliminar :c? Solo se puede si ninguna sección, plan ni asignatura la usa.', ['c' => $a->code]) }}">{{ __('Eliminar') }}</x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        {{ $asignaturas->links() }}
    </x-ui.card>

    <x-ui.modal nombre="asignatura" :titulo="$editando ? __('Editar asignatura') : __('Nueva asignatura')" ancho="lg">
        <form wire:submit="guardar" id="form-asignatura" class="flex flex-col gap-4">
            <div class="grid gap-4 sm:grid-cols-[1fr_2fr]">
                <x-ui.field :etiqueta="__('Código')" para="codigo" error="codigo" requerido><x-ui.input id="codigo" wire:model="codigo" placeholder="PRN115" /></x-ui.field>
                <x-ui.field :etiqueta="__('Nombre')" para="nombreAsig" error="nombre" requerido><x-ui.input id="nombreAsig" wire:model="nombre" /></x-ui.field>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field :etiqueta="__('Créditos')" para="creditos" error="creditos" requerido><x-ui.input id="creditos" type="number" min="1" max="20" wire:model="creditos" /></x-ui.field>
                <x-ui.field :etiqueta="__('Departamento')" para="departamentoId" error="departamentoId" requerido>
                    <x-ui.select id="departamentoId" wire:model="departamentoId">
                        <option value="">{{ __('Elija…') }}</option>
                        @foreach ($departamentos as $d)<option value="{{ $d->id }}">{{ $d->code }} · {{ $d->name }}</option>@endforeach
                    </x-ui.select>
                </x-ui.field>
            </div>
            {{-- H-11 (QA 27/10): casillas en lugar de <select multiple>, que exigía Ctrl e iba mal en pantallas táctiles. --}}
            <x-ui.field :etiqueta="__('Prerrequisitos')" para="filtro-prerrequisitos" error="prerrequisitos" :ayuda="__('Marque todas las que correspondan.')">
                <div x-data="{ filtro: '' }" class="flex flex-col gap-2">
                    <x-ui.input id="filtro-prerrequisitos" type="search" x-model="filtro" :placeholder="__('Filtrar por código o nombre…')" />
                    <div class="max-h-52 overflow-y-auto rounded-lg border border-borde p-2">
                        @foreach ($todas as $t)
                            @continue($t->id === $editando)
                            <label wire:key="pre-{{ $t->id }}" x-show="filtro === '' || @js(mb_strtolower($t->code.' '.$t->name)).includes(filtro.toLowerCase())" class="flex items-center gap-2 rounded px-1 py-1 text-sm text-texto hover:bg-zinc-50">
                                <input type="checkbox" value="{{ $t->id }}" wire:model="prerrequisitos" class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600">
                                <span><span class="font-medium">{{ $t->code }}</span> · {{ $t->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'asignatura')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-asignatura">{{ __('Guardar') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
