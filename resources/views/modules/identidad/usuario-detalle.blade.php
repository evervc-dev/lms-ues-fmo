{{-- Detalle de usuario (RF04). --}}
<div class="flex flex-col gap-5">
    <div class="flex flex-wrap items-center gap-4">
        <x-ui.avatar :iniciales="$usuario->iniciales()" :foto="$usuario->avatar_url" tamano="lg" />
        <x-ui.encabezado :titulo="$usuario->fullName()" :subtitulo="$usuario->institutional_email" class="flex-1">
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach ($usuario->roles as $rol)
                    <x-ui.badge color="marca">{{ __('roles.'.$rol->name) }}</x-ui.badge>
                @endforeach
                <x-ui.badge :color="$usuario->is_active ? 'verde' : 'gris'">{{ $usuario->is_active ? __('Activo') : __('Inactivo') }}</x-ui.badge>
                @if ($esEmergencia)
                    <x-ui.badge color="rojo">{{ __('Cuenta de emergencia') }}</x-ui.badge>
                @endif
                @if ($creaDocentes)
                    <x-ui.badge color="ambar">{{ __('Puede crear docentes') }}</x-ui.badge>
                @endif
            </div>
            @if ($verPerfil || $editaDatos || $cambiaRoles || $concedeCrearDocente)
                <x-slot:acciones>
                    @if ($verPerfil)
                        <x-ui.button variante="fantasma" icono="eye" :href="route('personas.show', ['usuario' => $usuario->id])">{{ __('Ver perfil') }}</x-ui.button>
                    @endif
                    @if ($concedeCrearDocente)
                        <x-ui.button variante="secundario" icono="academic-cap" wire:click="permitirCrearDocentes({{ $creaDocentes ? 'false' : 'true' }})" wire:confirm="{{ $creaDocentes ? __('¿Retirar el permiso de crear docentes? El cambio queda en la auditoría.') : __('¿Permitir que esta persona cree cuentas de docente? El cambio queda en la auditoría.') }}">
                            {{ $creaDocentes ? __('Retirar «crear docentes»') : __('Permitir crear docentes') }}
                        </x-ui.button>
                    @endif
                    @if ($editaDatos)
                        <x-ui.button variante="secundario" icono="user-circle" wire:click="editarDatos">{{ __('Editar datos') }}</x-ui.button>
                    @endif
                    @if ($cambiaRoles && ! ($esEmergencia || $esPropio))
                        <x-ui.button icono="shield-check" :href="route('usuarios.roles', ['usuario' => $usuario->id])">{{ __('Roles y asignaciones') }}</x-ui.button>
                    @endif
                </x-slot:acciones>
            @endif
        </x-ui.encabezado>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <x-ui.card :titulo="__('Asignaciones vigentes')">
            @if ($asignaGrupos)
                {{-- Atajo de RF08: asignar un grupo teórico sin ir a Carga académica. --}}
                <x-slot:acciones>
                    <x-ui.button tamano="sm" variante="secundario" icono="plus" wire:click="abrirAsignarGrupo">{{ __('Asignar grupo teórico') }}</x-ui.button>
                </x-slot:acciones>
            @endif
            <dl class="flex flex-col gap-3 text-sm">
                @if ($estudiante)
                    <div><dt class="text-texto-suave">{{ __('Estudiante') }}</dt><dd>{{ $estudiante->carrera }} · {{ $estudiante->plan }} · {{ __('ciclo :ciclo', ['ciclo' => $estudiante->ciclo]) }} · {{ __('carné :carne', ['carne' => $estudiante->carne]) }}</dd></div>
                    @isset($perfil['estudiante'])
                        <div><dt class="text-texto-suave">{{ __('Año de ingreso') }}</dt><dd>{{ $perfil['estudiante']['ingreso'] ? $perfil['estudiante']['ingreso']['anio'].' ('.$perfil['estudiante']['ingreso']['periodo'].')' : '—' }}</dd></div>
                        <div>
                            <dt class="text-texto-suave">{{ __('Materias inscritas este período') }}</dt>
                            <dd class="mt-1 flex flex-wrap gap-1">
                                @forelse ($perfil['estudiante']['materias'] as $m)
                                    <x-ui.badge>{{ $m->code }} · {{ $m->grupo }}</x-ui.badge>
                                @empty
                                    <span class="text-texto-suave">{{ __('Sin materias inscritas en el período en curso.') }}</span>
                                @endforelse
                            </dd>
                        </div>
                    @endisset
                @endif
                @if ($docenteEn && in_array('docente', $rolesActuales, true))
                    <div><dt class="text-texto-suave">{{ __('Docente del departamento') }}</dt><dd>{{ $docenteEn }}</dd></div>
                @endif
                @if ($coordina)
                    <div><dt class="text-texto-suave">{{ __('Coordina') }}</dt><dd>{{ implode(', ', $coordina) }}</dd></div>
                @endif
                @if ($dirige)
                    <div><dt class="text-texto-suave">{{ __('Dirige') }}</dt><dd>{{ implode(', ', $dirige) }}</dd></div>
                @endif
                @if ($secciones->isNotEmpty())
                    @if (($perfil['docente']['carreras'] ?? []) !== [])
                        <div><dt class="text-texto-suave">{{ __('Carreras donde imparte') }}</dt><dd>{{ implode(', ', $perfil['docente']['carreras']) }}</dd></div>
                    @endif
                    <div>
                        <dt class="text-texto-suave">{{ __('Grupos teóricos del período') }}</dt>
                        <dd class="mt-1 flex flex-col gap-1">
                            @foreach ($perfil['docente']['materias'] ?? [] as $m)
                                <p>{{ $m->code }} · {{ $m->grupo }} — {{ $m->name }}{{ $m->is_responsible ? '' : ' ('.__('co-docente').')' }}@if ($m->ciclos)<span class="text-texto-suave"> ({{ implode(', ', $m->ciclos) }})</span>@endif</p>
                            @endforeach
                        </dd>
                    </div>
                @endif
                @if (! $estudiante && ! ($docenteEn && in_array('docente', $rolesActuales, true)) && ! $coordina && ! $dirige && $secciones->isEmpty())
                    <p class="text-texto-suave">{{ __('Sin asignaciones vigentes.') }}</p>
                @endif
            </dl>
        </x-ui.card>

        <x-ui.card :titulo="__('Cuenta')">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-texto-suave">{{ __('Último acceso') }}</dt><dd>{{ $usuario->last_login_at?->timezone('America/El_Salvador')->format('d/m/Y H:i') ?? '—' }}</dd></div>
                <div><dt class="text-texto-suave">{{ __('Creada') }}</dt><dd>{{ $usuario->created_at?->timezone('America/El_Salvador')->format('d/m/Y') }}</dd></div>
                @if ($verAccesos)
                    <div><dt class="text-texto-suave">{{ __('Teléfono de asistencia') }}</dt><dd>{{ $dispositivo ? __('Registrado el :fecha', ['fecha' => \Illuminate\Support\Carbon::parse($dispositivo->created_at)->format('d/m/Y')]) : '—' }}</dd></div>
                @endif
                <div><dt class="text-texto-suave">{{ __('Desactivada') }}</dt><dd>{{ $usuario->deactivated_at?->format('d/m/Y') ?? '—' }}</dd></div>
            </dl>
        </x-ui.card>
    </div>

    @if ($verAccesos)
        <x-ui.card :titulo="__('Últimos accesos')">
            <x-ui.table :vacio="__('Sin accesos registrados.')">
                <x-slot:cabecera><th>{{ __('Fecha') }}</th><th>{{ __('IP') }}</th><th>{{ __('Resultado') }}</th></x-slot:cabecera>
                @foreach ($accesos as $acceso)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($acceso->occurred_at)->timezone('America/El_Salvador')->format('d/m/Y H:i') }}</td>
                        <td class="font-mono text-xs">{{ $acceso->ip_address }}</td>
                        <td><x-ui.badge :color="$acceso->result === 'success' ? 'verde' : 'rojo'">{{ $acceso->result === 'success' ? __('Correcto') : __('Rechazado') }}</x-ui.badge></td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif

    @if ($asignaGrupos)
        <x-ui.modal nombre="asignar-grupo" ancho="lg" :titulo="__('Asignar grupo teórico')" :subtitulo="__('A :n, en los grupos de su alcance.', ['n' => $usuario->fullName()])">
            <div class="flex flex-col gap-4">
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem]">
                    <x-ui.search wire:model.live.debounce.300ms="buscarGrupo" :placeholder="__('Buscar materia por código o nombre…')" />
                    @if ($periodosGrupo->count() > 1)
                        <x-ui.select wire:model.live="periodoGrupo" :aria-label="__('Período')">
                            @foreach ($periodosGrupo as $p)<option value="{{ $p->id }}">{{ $p->name }}{{ $p->status === 'planned' ? ' · '.__('en preparación') : '' }}</option>@endforeach
                        </x-ui.select>
                    @endif
                </div>
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" wire:model.live="responsableGrupo" class="mt-0.5 size-4 rounded border-borde text-marca-600 focus:ring-marca-600">
                    <span class="flex flex-col"><span class="font-medium text-texto">{{ __('Como responsable') }}</span><span class="text-xs text-texto-suave">{{ __('Si el grupo ya tiene responsable, ese pasa a co-docente.') }}</span></span>
                </label>
                <ul class="flex max-h-96 flex-col divide-y divide-borde overflow-y-auto rounded-lg ring-1 ring-borde">
                    @forelse ($gruposParaAsignar as $g)
                        @php($yaEsta = $g->docentes->contains('id', $usuario->id))
                        <li wire:key="grupo-{{ $g->id }}" class="flex flex-col gap-2 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 text-sm">
                                <p class="font-medium text-texto"><span class="font-mono text-xs font-semibold">{{ $g->code }}</span> · {{ __('Grupo :g', ['g' => $g->grupo]) }} — {{ $g->name }}</p>
                                <p class="text-xs text-texto-suave">
                                    {{ $g->horario !== [] ? implode(' · ', $g->horario) : __('Sin horario') }}
                                    · {{ $g->docentes->isEmpty() ? __('sin docente') : $g->docentes->map(fn ($d) => $d->first_name.' '.$d->last_name)->implode(', ') }}
                                </p>
                            </div>
                            @if ($yaEsta)
                                <x-ui.badge color="verde">{{ __('Ya lo imparte') }}</x-ui.badge>
                            @else
                                <x-ui.button tamano="sm" wire:click="asignarGrupo({{ $g->id }})" wire:loading.attr="disabled" wire:target="asignarGrupo({{ $g->id }})">{{ __('Asignar') }}</x-ui.button>
                            @endif
                        </li>
                    @empty
                        <li class="px-3 py-6 text-center text-sm text-texto-suave">{{ $buscarGrupo !== '' ? __('Ningún grupo coincide con la búsqueda.') : __('No hay grupos teóricos en su alcance para este período.') }}</li>
                    @endforelse
                </ul>
                <p class="text-xs text-texto-suave">{{ __('Se rechaza si choca con el horario de otro grupo que ya imparte o si el grupo llegó al máximo de docentes.') }}</p>
            </div>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'asignar-grupo')">{{ __('Listo') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif

    @if ($editaDatos)
        <x-ui.modal nombre="editar-datos" :titulo="__('Editar datos')">
            <form wire:submit="guardarDatos" id="form-editar-datos" class="flex flex-col gap-4">
                <x-ui.field :etiqueta="__('Correo institucional')" para="correo-fijo" :ayuda="__('El correo institucional no se puede modificar.')">
                    <x-ui.input id="correo-fijo" :value="$usuario->institutional_email" disabled />
                </x-ui.field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field :etiqueta="__('Nombres')" para="nombres" error="nombres" requerido><x-ui.input id="nombres" wire:model="nombres" maxlength="80" /></x-ui.field>
                    <x-ui.field :etiqueta="__('Apellidos')" para="apellidos" error="apellidos" requerido><x-ui.input id="apellidos" wire:model="apellidos" maxlength="80" /></x-ui.field>
                </div>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'editar-datos')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-editar-datos">{{ __('Guardar') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif

</div>
