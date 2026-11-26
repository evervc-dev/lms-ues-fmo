{{-- Catálogo de asignaturas (RF07). Categorizado por departamento y carrera. --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Catálogo de asignaturas')" :subtitulo="__('Código, créditos, carreras donde se lleva y prerrequisitos (informativos, no se exigen al matricular).')">
        <x-slot:acciones>
            @if ($gestionaEstructura)
                <x-ui.button variante="secundario" icono="building-library" :href="route('catalogo.estructura')">{{ __('Departamentos, carreras y aulas') }}</x-ui.button>
            @endif
            <x-ui.button icono="plus" :href="route('catalogo.asignaturas.crear')">{{ __('Nueva asignatura') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.encabezado>

    {{-- Activas y archivadas (ADR-092): una archivada no se ofrece al crear grupos ni en los planes. --}}
    <div class="flex gap-1 rounded-lg bg-white p-1 text-sm ring-1 ring-borde sm:self-start" role="tablist">
        @foreach ([0 => __('Activas'), 1 => __('Archivadas')] as $valor => $etiqueta)
            <button type="button" role="tab" wire:click="$set('archivadas', {{ $valor ? 'true' : 'false' }})" aria-selected="{{ $archivadas === (bool) $valor ? 'true' : 'false' }}"
                @class(['rounded-md px-3 py-1.5 font-medium', 'bg-marca-600 text-white' => $archivadas === (bool) $valor, 'text-texto-suave hover:bg-zinc-100' => $archivadas !== (bool) $valor])>{{ $etiqueta }}</button>
        @endforeach
    </div>

    {{-- Pestañas superiores por departamento para agilizar la navegación si el usuario gestiona múltiples departamentos --}}
    @if ($veDepartamento && $departamentos->count() > 1)
        <div class="flex flex-wrap items-center gap-1.5 border-b border-borde pb-2">
            <button
                type="button"
                wire:click="seleccionarDepartamento('')"
                @class([
                    'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition cursor-pointer',
                    'bg-marca-600 text-white shadow-xs' => $departamento === '',
                    'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 hover:text-zinc-900' => $departamento !== '',
                ])
            >
                <span>{{ __('Todos los departamentos') }}</span>
                <span @class([
                    'rounded-full px-2 py-0.5 text-xs font-semibold',
                    'bg-white/20 text-white' => $departamento === '',
                    'bg-zinc-200 text-zinc-700' => $departamento !== '',
                ])>{{ array_sum($conteosDepto) }}</span>
            </button>
            @foreach ($departamentos as $d)
                @php($totalDepto = $conteosDepto[$d->code] ?? 0)
                <button
                    type="button"
                    wire:click="seleccionarDepartamento('{{ $d->code }}')"
                    @class([
                        'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition cursor-pointer',
                        'bg-marca-600 text-white shadow-xs' => $departamento === $d->code,
                        'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 hover:text-zinc-900' => $departamento !== $d->code,
                    ])
                >
                    <span>{{ $d->code }} · {{ $d->name }}</span>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                        'bg-white/20 text-white' => $departamento === $d->code,
                        'bg-zinc-200 text-zinc-700' => $departamento !== $d->code,
                    ])>{{ $totalDepto }}</span>
                </button>
            @endforeach
        </div>
    @endif

    {{-- Barra de filtros --}}
    <x-ui.card>
        <div class="grid gap-3 @if ($veDepartamento && $departamentos->count() > 1) md:grid-cols-[1fr_auto_auto_auto] @else md:grid-cols-[1fr_auto_auto] @endif">
            <x-ui.search wire:model.live.debounce.300ms="buscar" :placeholder="__('Buscar por código o nombre…')" />

            @if ($veDepartamento && $departamentos->count() > 1)
                <x-ui.select wire:model.live="departamento" :aria-label="__('Departamento')">
                    <option value="">{{ __('Todos los departamentos') }}</option>
                    @foreach ($departamentos as $d)
                        <option value="{{ $d->code }}">{{ $d->code }} · {{ $d->name }}</option>
                    @endforeach
                </x-ui.select>
            @endif

            @php($deptoActivo = $departamentos->firstWhere('code', $departamento))
            @php($carrerasDisponibles = $deptoActivo ? $carreras->where('department_id', $deptoActivo->id) : $carreras)
            {{-- Solo las carreras de su alcance: todas para el Administrador, las de su departamento para el Jefe. --}}
            <x-ui.select wire:model.live="carrera" :aria-label="__('Carrera')">
                <option value="">{{ __('Todas las carreras') }}</option>
                @foreach ($carrerasDisponibles as $c)
                    <option value="{{ $c->id }}">{{ $c->code }} · {{ $c->name }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select wire:model.live="filtroCreditos" :aria-label="__('Créditos')">
                <option value="">{{ __('Todos los créditos') }}</option>
                @foreach ($valoresCreditos as $c)
                    <option value="{{ $c }}">{{ trans_choice('{1} :count crédito|[2,*] :count créditos', $c) }}</option>
                @endforeach
            </x-ui.select>
        </div>
    </x-ui.card>

    {{-- Listado agrupado por departamentos en tarjetas colapsables --}}
    @if ($asignaturas->isEmpty())
        <x-ui.card ajustar>
            <x-ui.table ajustar :vacio="__('No hay asignaturas con esos filtros.')">
                <x-slot:cabecera>
                    <th>{{ __('Código') }}</th>
                    <th>{{ __('Asignatura') }}</th>
                    @if ($veDepartamento)<th>{{ __('Depto.') }}</th>@endif
                    <th>{{ __('Carrera y plan') }}</th>
                    <th class="text-right">{{ __('Créditos') }}</th>
                    <th>{{ __('Prerrequisitos') }}</th>
                    <th class="text-right">{{ __('Grupos') }}</th>
                    <th></th>
                </x-slot:cabecera>
            </x-ui.table>
        </x-ui.card>
    @else
        @php($agrupadasPorDepto = $asignaturas->getCollection()->groupBy('departamento'))
        <div class="flex flex-col gap-4">
            @foreach ($agrupadasPorDepto as $deptoCodigo => $materiasDepto)
                @php($nombreDepto = $materiasDepto->first()->departamento_nombre ?? $deptoCodigo)
                <div x-data="{ abierto: true }" class="overflow-hidden rounded-xl bg-white shadow-xs ring-1 ring-borde">
                    <div class="flex items-center justify-between gap-3 border-b border-borde bg-zinc-50/75 px-4 py-3 sm:px-6">
                        <button
                            type="button"
                            x-on:click="abierto = ! abierto"
                            :aria-expanded="abierto.toString()"
                            class="group flex flex-1 items-center justify-between text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marca-600 cursor-pointer"
                        >
                            <div class="flex flex-wrap items-center gap-2.5">
                                <x-ui.badge color="marca" class="font-mono font-bold">{{ $deptoCodigo }}</x-ui.badge>
                                <span class="text-sm font-semibold text-zinc-900 group-hover:text-marca-700 transition-colors">
                                    {{ $nombreDepto }}
                                </span>
                                <span class="text-xs text-texto-suave">
                                    ({{ trans_choice('{1} :count asignatura en esta página|[2,*] :count asignaturas en esta página', $materiasDepto->count()) }})
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs font-medium text-texto-suave group-hover:text-texto">
                                <span x-text="abierto ? '{{ __('Contraer') }}' : '{{ __('Expandir') }}'" class="hidden sm:inline"></span>
                                <x-ui.icon name="chevron-down" class="size-4 transition-transform duration-200" ::class="abierto && 'rotate-180'" />
                            </div>
                        </button>
                    </div>

                    <div x-show="abierto" x-collapse>
                        <x-ui.table ajustar class="!ring-0 !rounded-none">
                            <x-slot:cabecera>
                                <th>{{ __('Código') }}</th>
                                <th>{{ __('Asignatura') }}</th>
                                @if ($veDepartamento)<th>{{ __('Depto.') }}</th>@endif
                                <th>{{ __('Carrera y plan') }}</th>
                                <th class="text-right">{{ __('Créditos') }}</th>
                                <th>{{ __('Prerrequisitos') }}</th>
                                <th class="text-right">{{ __('Grupos') }}</th>
                                <th></th>
                            </x-slot:cabecera>
                            @foreach ($materiasDepto as $a)
                                <tr wire:key="asig-{{ $a->id }}" @class(['text-texto-suave' => ! $a->is_active])>
                                    <td class="font-mono text-xs font-semibold">{{ $a->code }}</td>
                                    <td>
                                        <div class="font-medium text-zinc-900">{{ $a->name }}</div>
                                        @unless ($a->is_active)
                                            <x-ui.badge class="mt-0.5">{{ __('Archivada') }}</x-ui.badge>
                                        @endunless
                                    </td>
                                    @if ($veDepartamento)<td>{{ $a->departamento }}</td>@endif
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @forelse ($carrerasDe[$a->id] ?? [] as $c)
                                                <x-ui.badge color="marca">{{ $c }}</x-ui.badge>
                                            @empty
                                                <span class="text-texto-suave">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="text-right">{{ $a->credits }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @forelse ($prerrequisitosDe[$a->id] ?? [] as $p)
                                                <x-ui.badge>{{ $p }}</x-ui.badge>
                                            @empty
                                                <span class="text-texto-suave">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="text-right">{{ $a->secciones }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        <x-ui.button tamano="sm" variante="fantasma" :href="route('catalogo.asignaturas.editar', ['asignatura' => $a->code])">{{ __('Editar') }}</x-ui.button>
                                        @php($dependientes = $a->is_active ? ($dependientesDe[$a->id] ?? collect())->pluck('code') : collect())
                                        <x-ui.acciones-fila :etiqueta="__('Más acciones de :c', ['c' => $a->code])">
                                            @if ($dependientes->isNotEmpty())
                                                <x-ui.dropdown.item icono="x-circle" x-on:click="abierto = false" wire:click="alternarEstado({{ $a->id }})" wire:confirm="{{ __('¿Archivar :c? También se archivarán las que dependen de ella: :d.', ['c' => $a->code, 'd' => $dependientes->implode(', ')]) }}">{{ __('Archivar') }}</x-ui.dropdown.item>
                                            @else
                                                <x-ui.dropdown.item :icono="$a->is_active ? 'x-circle' : 'check-circle'" x-on:click="abierto = false" wire:click="alternarEstado({{ $a->id }})">{{ $a->is_active ? __('Archivar') : __('Desarchivar') }}</x-ui.dropdown.item>
                                            @endif
                                            <x-ui.dropdown.item icono="x-mark" peligro x-on:click="abierto = false" wire:click="eliminar({{ $a->id }})" wire:confirm="{{ __('¿Eliminar :c? Solo se puede si ningún grupo teórico, plan ni asignatura la usa.', ['c' => $a->code]) }}">{{ __('Eliminar') }}</x-ui.dropdown.item>
                                        </x-ui.acciones-fila>
                                    </td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    </div>
                </div>
            @endforeach

            {{ $asignaturas->links() }}
        </div>
    @endif
</div>
