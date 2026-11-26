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
                    @if (\App\Modules\Identidad\Services\UsuarioService::rolesLaxos())
                        {{-- LMS_ROLES_LAXOS (solo pruebas): quitar un rol libera lo que lo retenía. --}}
                        <x-ui.alert tipo="aviso" class="mb-3" :titulo="__('Modo de pruebas')">{{ __('Quitar Estudiante retira sus matrículas activas y quitar Docente termina sus grupos teóricos del período, sin pedir que lo haga antes. Las notas y la asistencia anteriores se conservan.') }}</x-ui.alert>
                    @endif
                    {{-- ADR-081 (amplía ADR-053): primero el tipo de cuenta. Estudiante y Soporte Técnico son exclusivos; con
                         «Personal académico» se marcan uno o más de los cuatro roles que se combinan. Un tipo se ofrece
                         solo si quien edita puede dar ese rol y no obliga a quitar roles que no puede cambiar. --}}
                    @php($personal = ['administrador', 'jefe_departamento', 'coordinador', 'docente'])
                    @php($fijos = array_values(array_diff($roles, $rolesAsignables)))
                    @php($tipos = [
                        'personal' => ['icono' => 'users', 'titulo' => __('Personal académico'), 'texto' => __('Puede combinar varios roles.'),
                            'puede' => array_intersect($personal, $rolesAsignables) !== [] && array_diff($fijos, $personal) === []],
                        'estudiante' => ['icono' => 'academic-cap', 'titulo' => __('roles.estudiante'), 'texto' => __('Solo este rol.'),
                            'puede' => in_array('estudiante', $rolesAsignables, true) && array_diff($fijos, ['estudiante']) === []],
                        'soporte_tecnico' => ['icono' => 'puzzle-piece', 'titulo' => __('roles.soporte_tecnico'), 'texto' => __('Solo este rol.'),
                            'puede' => in_array('soporte_tecnico', $rolesAsignables, true) && array_diff($fijos, ['soporte_tecnico']) === []],
                    ])
                    @php($descripciones = [
                        'administrador' => ['icono' => 'shield-check', 'texto' => __('Gestiona toda la plataforma.')],
                        'jefe_departamento' => ['icono' => 'building-library', 'texto' => __('Autoriza actas y da roles en su departamento.')],
                        'coordinador' => ['icono' => 'clipboard-document-check', 'texto' => __('Valida las actas de su carrera.')],
                        'docente' => ['icono' => 'book-open', 'texto' => __('Imparte grupos teóricos.')],
                    ])

                    <p class="mb-2 text-sm font-medium text-texto">{{ __('Tipo de cuenta') }}</p>
                    <div class="grid gap-2 sm:grid-cols-3" role="radiogroup" aria-label="{{ __('Tipo de cuenta') }}">
                        @foreach ($tipos as $valor => $t)
                            @php($elegido = $tipoCuenta === $valor)
                            <label wire:key="tipo-{{ $valor }}" @class([
                                'flex cursor-pointer items-start gap-3 rounded-xl p-3 ring-1 transition',
                                'bg-marca-50 ring-2 ring-marca-600' => $elegido,
                                'bg-white ring-borde hover:ring-marca-300' => ! $elegido && $t['puede'],
                                'cursor-not-allowed bg-zinc-50 opacity-60 ring-borde' => ! $elegido && ! $t['puede'],
                            ])>
                                <input type="radio" value="{{ $valor }}" wire:model.live="tipoCuenta" @disabled(! $t['puede'] && ! $elegido) class="sr-only">
                                <span @class(['grid size-9 shrink-0 place-items-center rounded-lg', 'bg-marca-600 text-white' => $elegido, 'bg-zinc-100 text-texto-suave' => ! $elegido])>
                                    <x-ui.icon :name="$t['icono']" class="size-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-texto">{{ $t['titulo'] }}</span>
                                    <span class="block text-xs text-texto-suave">{{ $t['texto'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @if ($tipoCuenta === 'personal')
                        <p class="mb-2 mt-5 text-sm font-medium text-texto">{{ __('Roles del personal') }} <span class="font-normal text-texto-suave">{{ __('· marque uno o más') }}</span></p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($personal as $opcion)
                                @php($marcado = in_array($opcion, $roles, true))
                                @php($editable = in_array($opcion, $rolesAsignables, true))
                                <label wire:key="rol-{{ $opcion }}" @class([
                                    'flex items-start gap-3 rounded-xl p-3 ring-1 transition',
                                    'cursor-pointer' => $editable,
                                    'bg-marca-50 ring-marca-300' => $marcado,
                                    'bg-white ring-borde hover:ring-marca-300' => ! $marcado && $editable,
                                    'cursor-not-allowed opacity-60' => ! $editable,
                                    'bg-zinc-50 ring-borde' => ! $marcado && ! $editable,
                                ])>
                                    <input type="checkbox" value="{{ $opcion }}" wire:model.live="roles" @disabled(! $editable) class="mt-1 size-4 rounded border-borde text-marca-600 focus:ring-marca-600">
                                    <x-ui.icon :name="$descripciones[$opcion]['icono']" @class(['mt-0.5 size-5 shrink-0', 'text-marca-700' => $marcado, 'text-texto-suave' => ! $marcado]) />
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-texto">{{ __('roles.'.$opcion) }}</span>
                                        <span class="block text-xs text-texto-suave">{{ $descripciones[$opcion]['texto'] }}{{ $editable ? '' : ' '.__('(no lo puede cambiar)') }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @elseif ($tipoCuenta === null)
                        <p class="mt-3 text-xs text-texto-suave">{{ __('Elija el tipo de cuenta para ver sus roles.') }}</p>
                    @endif
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
                                        · {{ $carne ? mb_strtoupper($carne) : __('carné por asignar') }}
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
