{{-- Crear cuenta institucional (RF04, maqueta 29). Formulario por pasos. --}}
@php($nombreDe = fn ($coleccion, $id) => $coleccion->firstWhere('id', $id))

<div class="flex flex-col gap-5">
    <x-ui.encabezado
        :titulo="__('Nuevo usuario')"
        :subtitulo="__('La cuenta debe ser institucional @ues.edu.sv. Se crea activa y con el rol que elija; los roles se cambian después desde la ficha.')"
    />

    <x-ui.formulario accion="crear" :volver="route('usuarios')" :enviar="__('Crear cuenta')" :pasos="$this->pasos()" :paso="$paso">
        <x-ui.formulario.paso :numero="1">
            <x-ui.card :titulo="__('Datos de la cuenta')">
                <div class="flex flex-col gap-4">
                    <x-ui.field :etiqueta="__('Correo institucional')" para="correo" error="correo" :ayuda="__('Escriba solo la parte antes de la @; el dominio :dominio se agrega solo.', ['dominio' => $dominio])" requerido>
                        <div class="relative">
                            <x-ui.input id="correo" wire:model.blur="correo" placeholder="gs21004" autocomplete="off" autocapitalize="none" spellcheck="false" class="pr-28" />
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-zinc-500">{{ $dominio }}</span>
                        </div>
                    </x-ui.field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field :etiqueta="__('Nombres')" para="nombres" error="nombres" requerido><x-ui.input id="nombres" wire:model="nombres" /></x-ui.field>
                        <x-ui.field :etiqueta="__('Apellidos')" para="apellidos" error="apellidos" requerido><x-ui.input id="apellidos" wire:model="apellidos" /></x-ui.field>
                    </div>
                </div>
            </x-ui.card>
        </x-ui.formulario.paso>

        <x-ui.formulario.paso :numero="2">
            <x-ui.card :titulo="__('Rol y asignación')" :descripcion="__('Cada rol pide los datos de su ámbito: la carrera, el departamento o el plan de estudios.')">
                <div class="flex flex-col gap-4">
                    <x-ui.field :etiqueta="__('Rol')" para="rolNuevo" error="rolNuevo" requerido>
                        <x-ui.select id="rolNuevo" wire:model.live="rolNuevo">
                            @foreach ($this->rolesCreables as $opcion)
                                <option value="{{ $opcion }}">{{ __('roles.'.$opcion) }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    @include('modules.identidad.partials.asignacion', [
                        'rolAsignacion' => $rolNuevo,
                        'carneFijo' => false,
                        'opciones' => $this->opciones,
                        'prefijoError' => 'asignacion',
                    ])
                </div>
            </x-ui.card>
        </x-ui.formulario.paso>

        <x-ui.formulario.paso :numero="3">
            <x-ui.card :titulo="__('Revisar y crear')" :descripcion="__('Si algo no está bien, vuelva al paso correspondiente desde el indicador de arriba.')">
                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Correo institucional') }}</dt>
                        <dd class="mt-1 text-texto">{{ $correo !== '' ? $correo.$dominio : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Nombre') }}</dt>
                        <dd class="mt-1 text-texto">{{ trim($nombres.' '.$apellidos) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Rol') }}</dt>
                        <dd class="mt-1"><x-ui.badge color="marca">{{ $rolNuevo ? __('roles.'.$rolNuevo) : '—' }}</x-ui.badge></dd>
                    </div>

                    @switch($rolNuevo)
                        @case('coordinador')
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Carrera que coordina') }}</dt>
                                <dd class="mt-1 text-texto">{{ $nombreDe($this->opciones['carreras'], $carreraCoordina)?->name ?? '—' }}</dd>
                            </div>
                            @break
                        @case('jefe_departamento')
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Departamento que dirige') }}</dt>
                                <dd class="mt-1 text-texto">{{ $nombreDe($this->opciones['departamentos'], $departamentoJefatura)?->name ?? '—' }}</dd>
                            </div>
                            @break
                        @case('docente')
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Departamento') }}</dt>
                                <dd class="mt-1 text-texto">{{ $nombreDe($this->opciones['departamentos'], $departamentoDocente)?->name ?? '—' }}</dd>
                            </div>
                            @break
                        @case('estudiante')
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Carrera') }}</dt>
                                <dd class="mt-1 text-texto">{{ $nombreDe($this->opciones['carreras'], $carreraEstudiante)?->name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Plan de estudios') }}</dt>
                                <dd class="mt-1 text-texto">{{ $nombreDe($this->opciones['planes'], $planEstudiante)?->name ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Ciclo') }}</dt>
                                <dd class="mt-1 text-texto">{{ $cicloEstudiante ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Carné') }}</dt>
                                <dd class="mt-1 text-texto">{{ $this->carneDelCorreo ?? ($carne ?: '—') }}</dd>
                            </div>
                    @endswitch
                </dl>
                @php($relevos = $this->relevos)
                @isset($relevos[$rolNuevo])
                    <div class="mt-4">@include('modules.identidad.partials.relevo', ['rol' => $rolNuevo, 'titular' => $relevos[$rolNuevo]])</div>
                @endisset

                <x-ui.alert class="mt-4" tipo="info">{{ __('La cuenta entra con Google y queda activa desde el primer ingreso.') }}</x-ui.alert>
            </x-ui.card>
        </x-ui.formulario.paso>
    </x-ui.formulario>
</div>
