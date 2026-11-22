{{-- Crear o editar una asignatura (RF07). En página: sus carreras y planes crecen con los datos (Layout base). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado
        :titulo="$editando ? __('Editar :c', ['c' => $codigo]) : __('Nueva asignatura')"
        :subtitulo="__('Los datos de la materia, en qué carreras y ciclos se lleva y qué materias conviene llevar antes.')"
    />

    <x-ui.formulario accion="guardar" :volver="route('catalogo')" :enviar="$editando ? __('Guardar cambios') : __('Crear asignatura')" class="max-w-5xl">
        <x-ui.card :titulo="__('Datos de la asignatura')">
            <div class="flex flex-col gap-4">
                <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] lg:grid-cols-[10rem_minmax(0,1fr)]">
                    <x-ui.field :etiqueta="__('Código')" para="codigo" error="codigo" requerido :ayuda="__('Único en la facultad, como PRN115.')">
                        <x-ui.input id="codigo" wire:model.blur="codigo" placeholder="PRN115" autocapitalize="characters" spellcheck="false" class="font-mono uppercase" />
                    </x-ui.field>
                    <x-ui.field :etiqueta="__('Nombre')" para="nombreAsig" error="nombre" requerido>
                        <x-ui.input id="nombreAsig" wire:model.blur="nombre" placeholder="{{ __('Programación I') }}" />
                    </x-ui.field>
                </div>
                <div @class(['grid gap-4', 'sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] lg:grid-cols-[10rem_minmax(0,1fr)]' => $eligeDepartamento])>
                    <x-ui.field :etiqueta="__('Créditos')" para="creditos" error="creditos" requerido>
                        <x-ui.input id="creditos" type="number" min="1" max="20" wire:model="creditos" class="sm:max-w-32" />
                    </x-ui.field>
                    @if ($eligeDepartamento)
                        <x-ui.field :etiqueta="__('Departamento que la imparte')" para="departamentoId" error="departamentoId" requerido>
                            <x-ui.select id="departamentoId" wire:model="departamentoId">
                                <option value="">{{ __('Elija…') }}</option>
                                @foreach ($departamentos as $d)<option value="{{ $d->id }}">{{ $d->code }} · {{ $d->name }}</option>@endforeach
                            </x-ui.select>
                        </x-ui.field>
                    @endif
                </div>
            </div>
        </x-ui.card>

        {{-- Planes de estudio (career_subjects): en qué carrera, plan y ciclo se lleva. Al crear, al menos uno. --}}
        <x-ui.card :titulo="__('Carreras y planes de estudio')" :descripcion="__('En qué carrera, plan y ciclo se lleva. Una materia compartida va en varias carreras, y sus grupos los cursan estudiantes de todas.')">
            <div class="flex flex-col gap-3">
                @if ($planes !== [])
                    <div class="hidden grid-cols-[minmax(0,2fr)_minmax(0,1.5fr)_5.5rem_2.5rem] gap-3 px-1 text-xs font-semibold uppercase tracking-wide text-texto-suave sm:grid">
                        <span>{{ __('Carrera') }}</span><span>{{ __('Plan') }}</span><span>{{ __('Ciclo') }}</span><span class="sr-only">{{ __('Quitar') }}</span>
                    </div>
                @endif
                @foreach ($planes as $i => $fila)
                    @php($carrera = $carreras->firstWhere('id', (int) ($fila['carrera'] ?? 0)))
                    <div wire:key="plan-fila-{{ $i }}" class="flex flex-col gap-2 rounded-lg bg-zinc-50 p-3 ring-1 ring-borde sm:bg-transparent sm:p-0 sm:ring-0">
                        <div class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1.5fr)_5.5rem_2.5rem] sm:items-start">
                            <div>
                                <label for="plan-carrera-{{ $i }}" class="mb-1 block text-xs font-medium text-texto-suave sm:sr-only">{{ __('Carrera') }}</label>
                                <x-ui.select id="plan-carrera-{{ $i }}" wire:model.live="planes.{{ $i }}.carrera">
                                    <option value="">{{ __('Elija la carrera…') }}</option>
                                    @foreach ($carreras as $c)<option value="{{ $c->id }}">{{ $c->code }} · {{ $c->name }}</option>@endforeach
                                </x-ui.select>
                            </div>
                            <div>
                                <label for="plan-plan-{{ $i }}" class="mb-1 block text-xs font-medium text-texto-suave sm:sr-only">{{ __('Plan') }}</label>
                                <x-ui.select id="plan-plan-{{ $i }}" wire:model="planes.{{ $i }}.plan" :disabled="! $carrera || $carrera->planes === []">
                                    <option value="">{{ $carrera ? __('Elija el plan…') : __('Primero la carrera') }}</option>
                                    @foreach ($carrera->planes ?? [] as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                                </x-ui.select>
                            </div>
                            <div>
                                <label for="plan-ciclo-{{ $i }}" class="mb-1 block text-xs font-medium text-texto-suave sm:sr-only">{{ __('Ciclo') }}</label>
                                <x-ui.input id="plan-ciclo-{{ $i }}" type="number" min="1" max="12" wire:model="planes.{{ $i }}.ciclo" />
                            </div>
                            <button type="button" wire:click="quitarPlan({{ $i }})" class="inline-flex h-10 items-center justify-center gap-1.5 justify-self-end rounded-lg px-2 text-sm text-texto-suave hover:bg-red-50 hover:text-red-700 sm:justify-self-auto sm:px-0" aria-label="{{ __('Quitar esta carrera') }}" title="{{ __('Quitar') }}">
                                    <x-ui.icon name="x-mark" class="size-5" /><span class="sm:sr-only">{{ __('Quitar') }}</span>
                                </button>
                        </div>
                        @if ($carrera && $carrera->planes === [])
                            <p class="text-xs text-red-700">{{ __('Esta carrera no tiene planes de estudio. Pida al Administrador que le registre uno.') }}</p>
                        @endif
                        @error('planes.'.$i.'.ciclo')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>
                @endforeach

                @if ($planes === [])
                    <p class="rounded-lg border border-dashed border-borde px-4 py-6 text-center text-sm text-texto-suave">{{ __('Aún no está en ninguna carrera. Agregue al menos una.') }}</p>
                @endif
                @error('planes')<x-ui.alert tipo="error">{{ $message }}</x-ui.alert>@enderror
                <div><x-ui.button tamano="sm" variante="secundario" icono="plus" wire:click="agregarPlan">{{ __('Agregar carrera') }}</x-ui.button></div>
            </div>
        </x-ui.card>

        {{-- H-11 (QA 27/10): casillas en lugar de <select multiple>, que exigía Ctrl e iba mal en pantallas táctiles. --}}
        <x-ui.card :titulo="__('Prerrequisitos')" :descripcion="__('Informativos: orientan al estudiante, pero no se exigen al matricular (ADR-078).')">
        <div x-data="{ filtro: '' }" class="flex flex-col gap-3">
            @if ($elegidas->isNotEmpty())
                <div class="flex flex-wrap gap-1.5" aria-label="{{ __('Prerrequisitos elegidos') }}">
                    @foreach ($elegidas as $p)
                        <x-ui.badge color="marca" wire:key="elegida-{{ $p->id }}">{{ $p->code }}</x-ui.badge>
                    @endforeach
                </div>
            @endif
            <x-ui.input id="filtro-prerrequisitos" type="search" x-model="filtro" :placeholder="__('Filtrar por código o nombre…')" :aria-label="__('Filtrar prerrequisitos')" />
            <div class="grid max-h-80 overflow-y-auto rounded-lg border border-borde p-1 md:grid-cols-2">
                @foreach ($todas as $t)
                    @continue($t->id === $editando)
                    <label wire:key="pre-{{ $t->id }}" x-show="filtro === '' || @js(mb_strtolower($t->code.' '.$t->name)).includes(filtro.toLowerCase())" class="flex items-center gap-3 rounded-md px-2 py-1.5 text-sm text-texto hover:bg-zinc-50">
                        <input type="checkbox" value="{{ $t->id }}" wire:model.live="prerrequisitos" class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600">
                        <span class="min-w-0"><span class="font-mono text-xs font-semibold">{{ $t->code }}</span> · {{ $t->name }}</span>
                    </label>
                @endforeach
            </div>
            @error('prerrequisitos')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            <p class="text-xs text-texto-suave">{{ trans_choice('{0} Ninguna elegida.|{1} 1 elegida.|[2,*] :count elegidas.', $elegidas->count()) }}</p>
        </div>
        </x-ui.card>
    </x-ui.formulario>
</div>
