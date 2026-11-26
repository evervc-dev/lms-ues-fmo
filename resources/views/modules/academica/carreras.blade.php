{{-- Carreras del ámbito del actor (RF07, RF08). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="$departamentoElegido ? __('Carreras de :d', ['d' => $departamentoElegido->code]) : __('Carreras')"
        :subtitulo="$departamentoElegido
            ? __(':nombre: sus carreras, con su coordinador y su oferta del período en curso. Entre a una para ver sus docentes, materias y grupos.', ['nombre' => $departamentoElegido->name])
            : __('Carreras de su ámbito, con su coordinador y su oferta del período en curso. Entre a una para ver sus docentes, materias y grupos.')">
        <div class="flex flex-wrap gap-2">
            @if ($departamentoElegido)
                <x-ui.button variante="secundario" icono="building-library" :href="route('departamentos')">{{ __('Todos los departamentos') }}</x-ui.button>
            @endif
            @if ($gestiona)
                <x-ui.button icono="plus" wire:click="nuevaCarrera">{{ __('Nueva carrera') }}</x-ui.button>
            @endif
        </div>
    </x-ui.encabezado>

    @if ($carreras->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icono="academic-cap" :titulo="__('No hay carreras')">
                {{ $departamentoElegido ? __('Este departamento todavía no tiene carreras.') : __('No tiene carreras asignadas en su departamento ni en su coordinación.') }}
            </x-ui.empty-state>
        </x-ui.card>
    @else
        {{-- Sin título propio: repetía el de la página (revisión UX del 22/11/2026). --}}
        <x-ui.card>
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
                            <p class="text-xs text-texto-suave">
                                {{ $carrera->planes === [] ? __('Sin plan de estudios') : implode(' · ', $carrera->planes) }}
                                @if ($gestiona)
                                    · <button type="button" wire:click="nuevoPlan({{ $carrera->id }})" class="font-medium text-marca-700 hover:underline">{{ __('Agregar plan') }}</button>
                                @endif
                            </p>
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

    @if ($gestiona)
        <x-ui.modal nombre="nueva-carrera" :titulo="__('Nueva carrera')">
            <form wire:submit="crearCarrera" id="form-carrera" class="flex flex-col gap-4">
                <x-ui.field :etiqueta="__('Departamento')" para="carreraDepartamento" error="carreraDepartamento" requerido>
                    <x-ui.select id="carreraDepartamento" wire:model="carreraDepartamento">
                        <option value="">{{ __('Elija…') }}</option>
                        @foreach ($departamentos as $d)<option value="{{ $d->id }}">{{ $d->code }} · {{ $d->name }}</option>@endforeach
                    </x-ui.select>
                </x-ui.field>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.field :etiqueta="__('Código')" para="carreraCodigo" error="carreraCodigo" :ayuda="__('Como ISI o LAE.')" requerido><x-ui.input id="carreraCodigo" wire:model="carreraCodigo" maxlength="20" class="uppercase" /></x-ui.field>
                    <x-ui.field class="sm:col-span-2" :etiqueta="__('Nombre')" para="carreraNombre" error="carreraNombre" requerido><x-ui.input id="carreraNombre" wire:model="carreraNombre" :placeholder="__('Licenciatura en…')" /></x-ui.field>
                </div>
                <x-ui.field :etiqueta="__('Primer plan de estudios')" para="carreraPlan" error="carreraPlan" :ayuda="__('El pensum vigente, como «Plan 25». Después puede agregar otros.')" requerido><x-ui.input id="carreraPlan" wire:model="carreraPlan" placeholder="Plan 25" /></x-ui.field>
                <p class="text-xs text-texto-suave">{{ __('Las materias se agregan a la carrera desde el Catálogo, en «Carreras y planes de estudio» de cada asignatura. El coordinador se asigna desde Usuarios.') }}</p>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'nueva-carrera')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-carrera">{{ __('Crear carrera') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>

        <x-ui.modal nombre="nuevo-plan" :titulo="$carreraDelPlan ? __('Nuevo plan de :c', ['c' => $carreraDelPlan]) : __('Nuevo plan')">
            <form wire:submit="agregarPlan" id="form-plan" class="flex flex-col gap-4">
                <x-ui.field :etiqueta="__('Nombre del plan')" para="planNombre" error="planNombre" requerido><x-ui.input id="planNombre" wire:model="planNombre" placeholder="Plan 25" /></x-ui.field>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'nuevo-plan')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-plan">{{ __('Agregar plan') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif
</div>
