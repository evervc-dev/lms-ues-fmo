{{-- Roles y asignaciones de una cuenta (RF04, ADR-037). Formulario por pasos. --}}
@php($conAmbito = ['coordinador', 'jefe_departamento', 'docente', 'estudiante'])
@php($marcados = array_values(array_intersect($conAmbito, array_intersect($roles, $rolesAsignables))))
@php($nombreDe = fn ($coleccion, $id) => $coleccion->firstWhere('id', $id)?->name ?? '—')

<div class="flex flex-col gap-5">
    <x-ui.encabezado
        :titulo="__('Roles y asignaciones')"
        :subtitulo="__('Cuenta de :nombre.', ['nombre' => $usuario->fullName()])"
    />

    <x-ui.formulario accion="guardar" :volver="route('usuarios.show', ['usuario' => $usuario->id])" :pasos="$this->pasos()" :paso="$paso">
        <x-ui.formulario.paso :numero="1">
            <x-ui.card :titulo="__('Roles')" :descripcion="__('Los roles con ámbito requieren su asignación. Al quitar Coordinador o Jefe se cierra su vigencia; los perfiles docente y de estudiante se conservan como historial.')">
                <fieldset>
                    <legend class="sr-only">{{ __('Roles') }}</legend>
                    @if (count($rolesAsignables) < count($rolesDisponibles))
                        <p class="mb-2 text-xs text-texto-suave">{{ __('Solo puede cambiar los roles que tiene habilitados; los demás se muestran sin edición.') }}</p>
                    @endif
                    {{-- ADR-053: el alcance de sus secciones y el de sus matrículas no pueden recaer en la misma persona. --}}
                    <p class="mb-2 text-xs text-texto-suave">{{ __('El rol Estudiante no se combina con el de Docente ni con el de Soporte Técnico.') }}</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($rolesDisponibles as $opcion)
                            <label class="flex items-center gap-2 text-sm" wire:key="rol-{{ $opcion }}">
                                <input type="checkbox" value="{{ $opcion }}" wire:model.live="roles" @disabled(! in_array($opcion, $rolesAsignables, true)) class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600 disabled:opacity-50">
                                {{ __('roles.'.$opcion) }}
                            </label>
                        @endforeach
                    </div>
                    @error('roles')
                        <p class="mt-2 text-xs font-medium text-red-700">{{ $message }}</p>
                    @enderror
                </fieldset>
            </x-ui.card>
        </x-ui.formulario.paso>

        <x-ui.formulario.paso :numero="2">
            @forelse ($marcados as $rolAsignacion)
                <x-ui.card :titulo="__('roles.'.$rolAsignacion)" wire:key="asignacion-{{ $rolAsignacion }}">
                    <div class="flex flex-col gap-4">
                        @include('modules.identidad.partials.asignacion', [
                            'rolAsignacion' => $rolAsignacion,
                            'opciones' => $this->opciones,
                            'prefijoError' => 'asignaciones.'.$rolAsignacion,
                        ])
                    </div>
                </x-ui.card>
            @empty
                <x-ui.card :titulo="__('Asignaciones')">
                    <x-ui.alert tipo="info">{{ __('Ninguno de los roles marcados necesita datos de ámbito. Continúe al resumen.') }}</x-ui.alert>
                </x-ui.card>
            @endforelse
        </x-ui.formulario.paso>

        <x-ui.formulario.paso :numero="3">
            <x-ui.card :titulo="__('Revisar y guardar')" :descripcion="__('Si algo no está bien, vuelva al paso correspondiente desde el indicador de arriba.')">
                <dl class="flex flex-col gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Roles') }}</dt>
                        <dd class="mt-1 flex flex-wrap gap-1">
                            @forelse ($roles as $rolMarcado)
                                <x-ui.badge color="marca">{{ __('roles.'.$rolMarcado) }}</x-ui.badge>
                            @empty
                                —
                            @endforelse
                        </dd>
                    </div>

                    @foreach ($marcados as $rolAsignacion)
                        <div wire:key="resumen-{{ $rolAsignacion }}">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('roles.'.$rolAsignacion) }}</dt>
                            <dd class="mt-1 text-texto">
                                @switch($rolAsignacion)
                                    @case('coordinador') {{ $nombreDe($this->opciones['carreras'], $carreraCoordina) }} @break
                                    @case('jefe_departamento') {{ $nombreDe($this->opciones['departamentos'], $departamentoJefatura) }} @break
                                    @case('docente') {{ $nombreDe($this->opciones['departamentos'], $departamentoDocente) }} @break
                                    @case('estudiante')
                                        {{ $nombreDe($this->opciones['carreras'], $carreraEstudiante) }}
                                        · {{ $nombreDe($this->opciones['planes'], $planEstudiante) }}
                                        · {{ __('ciclo :c', ['c' => $cicloEstudiante ?? '—']) }}
                                        · {{ $carne ?: __('carné por asignar') }}
                                @endswitch
                            </dd>
                        </div>
                    @endforeach
                </dl>
                @php($relevos = $this->relevos)
                @foreach (array_intersect(array_keys($relevos), $marcados) as $rolRelevo)
                    <div class="mt-4">@include('modules.identidad.partials.relevo', ['rol' => $rolRelevo, 'titular' => $relevos[$rolRelevo]])</div>
                @endforeach

                <x-ui.alert class="mt-4" tipo="aviso">{{ __('Los roles que usted no puede asignar no se tocan y conservan su asignación.') }}</x-ui.alert>
            </x-ui.card>
        </x-ui.formulario.paso>
    </x-ui.formulario>
</div>
