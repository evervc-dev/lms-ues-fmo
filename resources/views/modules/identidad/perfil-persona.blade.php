{{-- Perfil de una persona (RF36, ADR-056). Nivel mínimo para Docente y Estudiante: sin carné, ciclo ni matrículas. --}}
<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-center gap-4">
        <x-ui.avatar :iniciales="$usuario->iniciales()" :foto="$usuario->avatar_url" tamano="lg" />
        <x-ui.encabezado :titulo="$usuario->fullName()" :subtitulo="$usuario->institutional_email" class="flex-1">
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach ($roles as $rol)
                    <x-ui.badge color="marca">{{ __('roles.'.$rol) }}</x-ui.badge>
                @endforeach
            </div>
            @if ($administra)
                <x-slot:acciones>
                    <x-ui.button variante="secundario" icono="user-circle" :href="route('usuarios.show', ['usuario' => $usuario->id])">{{ __('Administrar cuenta') }}</x-ui.button>
                </x-slot:acciones>
            @endif
        </x-ui.encabezado>
    </div>

    @if ($bloques === [])
        <x-ui.empty-state icono="user-circle" :titulo="__('Sin datos académicos')">{{ __('Esta persona no tiene un rol con datos académicos que mostrar.') }}</x-ui.empty-state>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        @isset($bloques['jefe_departamento'])
            <x-ui.card :titulo="__('Jefatura de departamento')">
                <p class="text-sm">{{ $bloques['jefe_departamento']['departamentos'] === [] ? __('Sin departamento asignado.') : implode(', ', $bloques['jefe_departamento']['departamentos']) }}</p>
            </x-ui.card>
        @endisset

        @isset($bloques['coordinador'])
            <x-ui.card :titulo="__('Coordinación de carrera')">
                <p class="text-sm">{{ $bloques['coordinador']['carreras'] === [] ? __('Sin carrera asignada.') : implode(', ', $bloques['coordinador']['carreras']) }}</p>
            </x-ui.card>
        @endisset

        @isset($bloques['docente'])
            @php($docente = $bloques['docente'])
            <x-ui.card :titulo="__('Docencia')">
                <dl class="flex flex-col gap-3 text-sm">
                    <div><dt class="text-texto-suave">{{ __('Departamento') }}</dt><dd>{{ $docente['departamento'] ?? '—' }}</dd></div>
                    <div><dt class="text-texto-suave">{{ __('Carreras') }}</dt><dd>{{ $docente['carreras'] === [] ? '—' : implode(', ', $docente['carreras']) }}</dd></div>
                    <div>
                        <dt class="text-texto-suave">{{ __('Materias que imparte este período') }}</dt>
                        <dd class="mt-1">
                            @forelse ($docente['materias'] as $m)
                                <p>{{ $m->code }} · {{ $m->grupo }} — {{ $m->name }}@if ($m->ciclos)<span class="text-texto-suave"> ({{ implode(', ', $m->ciclos) }})</span>@endif</p>
                            @empty
                                <p class="text-texto-suave">{{ __('No imparte materias en el período en curso.') }}</p>
                            @endforelse
                        </dd>
                    </div>
                </dl>
            </x-ui.card>
        @endisset

        @isset($bloques['estudiante'])
            @php($estudiante = $bloques['estudiante'])
            <x-ui.card :titulo="__('Estudios')">
                <dl class="flex flex-col gap-3 text-sm">
                    <div><dt class="text-texto-suave">{{ __('Carrera') }}</dt><dd>{{ $estudiante['carrera'] ?? '—' }}</dd></div>
                    @if ($nivel === 'completo')
                        <div class="grid grid-cols-2 gap-3">
                            <div><dt class="text-texto-suave">{{ __('Plan') }}</dt><dd>{{ $estudiante['plan'] ?? '—' }}</dd></div>
                            <div><dt class="text-texto-suave">{{ __('Ciclo') }}</dt><dd>{{ $estudiante['ciclo'] ?? '—' }}</dd></div>
                            <div><dt class="text-texto-suave">{{ __('Carné') }}</dt><dd>{{ $estudiante['carne'] ?? '—' }}</dd></div>
                            <div><dt class="text-texto-suave">{{ __('Año de ingreso') }}</dt><dd>{{ $estudiante['ingreso'] ? $estudiante['ingreso']['anio'].' ('.$estudiante['ingreso']['periodo'].')' : '—' }}</dd></div>
                        </div>
                        <div>
                            <dt class="text-texto-suave">{{ __('Materias inscritas este período') }}</dt>
                            <dd class="mt-1 flex flex-wrap gap-1">
                                @forelse ($estudiante['materias'] as $m)
                                    <x-ui.badge>{{ $m->code }} · {{ $m->grupo }}</x-ui.badge>
                                @empty
                                    <span class="text-texto-suave">{{ __('Sin materias inscritas en el período en curso.') }}</span>
                                @endforelse
                            </dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>
        @endisset
    </div>
</div>
