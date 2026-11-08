{{-- Usuarios (RF04, maqueta 29). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Usuarios')" :subtitulo="__('Cuentas institucionales @ues.edu.sv. Las cuentas se desactivan, nunca se eliminan.')">
        @if ($importa || $puedeCrear)
            <x-slot:acciones>
                @if ($importa)
                    <x-ui.button variante="secundario" icono="arrow-up-tray" :href="route('usuarios.importar')">{{ __('Importar estudiantes (CSV)') }}</x-ui.button>
                @endif
                @if ($puedeCrear)
                    <x-ui.button icono="plus" :href="route('usuarios.crear')">{{ __('Nuevo usuario') }}</x-ui.button>
                @endif
            </x-slot:acciones>
        @endif
    </x-ui.encabezado>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat :etiqueta="__('Cuentas')" :valor="number_format($resumen['total'])" icono="users"
            :detalle="__(':n sin rol', ['n' => $resumen['sinRol']])" />
        <x-ui.stat :etiqueta="__('Activas')" :valor="number_format($resumen['activos'])" icono="check-circle" tono="exito"
            :detalle="__(':n inactivas', ['n' => $resumen['total'] - $resumen['activos']])" />
        <x-ui.stat :etiqueta="__('Estudiantes')" :valor="number_format($resumen['porRol']['estudiante'] ?? 0)" icono="academic-cap" />
        <x-ui.stat :etiqueta="__('Docentes')" :valor="number_format($resumen['porRol']['docente'] ?? 0)" icono="book-open" />
    </div>

    <x-ui.card ajustar>
        <div class="mb-4 flex flex-col gap-2">
            <div class="grid gap-3 md:grid-cols-[1fr_auto_auto_auto]">
                <x-ui.search wire:model.live.debounce.300ms="buscar" :placeholder="__('Buscar por nombre o correo…')" />
                <x-ui.select wire:model.live="rol" :aria-label="__('Rol')">
                    <option value="">{{ __('Todos los roles') }}</option>
                    @foreach (['administrador', 'jefe_departamento', 'coordinador', 'docente', 'estudiante', 'soporte_tecnico'] as $opcion)
                        <option value="{{ $opcion }}">{{ __('roles.'.$opcion) }}</option>
                    @endforeach
                    <option value="{{ \App\Modules\Identidad\Services\UsuarioService::SIN_ROL }}">{{ __('Sin rol') }}</option>
                </x-ui.select>
                <x-ui.select wire:model.live="estado" :aria-label="__('Estado')">
                    <option value="">{{ __('Todos los estados') }}</option>
                    <option value="activos">{{ __('Activos') }}</option>
                    <option value="inactivos">{{ __('Inactivos') }}</option>
                </x-ui.select>
                <x-ui.select wire:model.live="telefono" :aria-label="__('Teléfono de asistencia')">
                    <option value="">{{ __('Con y sin teléfono') }}</option>
                    <option value="sin">{{ __('Estudiantes sin teléfono') }}</option>
                    <option value="con">{{ __('Estudiantes con teléfono') }}</option>
                </x-ui.select>
            </div>
            @if ($buscar !== '' || $rol !== '' || $estado !== '' || $telefono !== '')
                <div class="flex justify-end">
                    <x-ui.button tamano="sm" variante="fantasma" icono="x-mark" wire:click="limpiar">{{ __('Limpiar filtros') }}</x-ui.button>
                </div>
            @endif
        </div>

        @if ($telefono !== '')
            <x-ui.alert class="mb-4" tipo="info">{{ $telefono === 'sin'
                ? __('Estudiantes con carrera activa que todavía no registraron su teléfono de asistencia: no pueden marcar con QR. Escríbales a su correo institucional para que lo registren desde Mi perfil.')
                : __('Estudiantes con carrera activa que ya tienen un teléfono de asistencia registrado.') }}</x-ui.alert>
        @endif

        <x-ui.table ajustar :vacio="__('No hay usuarios con esos filtros.')">
            <x-slot:cabecera>
                <th>{{ __('Usuario') }}</th><th>{{ __('Roles') }}</th><th>{{ __('Estado') }}</th><th>{{ __('Último acceso') }}</th><th class="text-right">{{ __('Acciones') }}</th>
            </x-slot:cabecera>
            @foreach ($this->usuarios as $usuario)
                <tr wire:key="usuario-{{ $usuario->id }}">
                    <td>
                        {{-- QA sesión 4: un nombre sin espacios (p. ej. 80 «m» seguidas) estiraba la columna y dejaba
                             «Desactivar» fuera de la vista; ahora el nombre se parte y la columna tiene tope. --}}
                        <div class="flex max-w-xs items-center gap-3">
                            <x-ui.avatar :iniciales="$usuario->iniciales()" :foto="$usuario->avatar_url" tamano="sm" />
                            <div class="min-w-0 [overflow-wrap:anywhere]">
                                <a href="{{ route('usuarios.show', $usuario->id) }}" wire:navigate class="font-medium text-texto hover:text-marca-700 hover:underline">{{ $usuario->fullName() }}</a>
                                <p class="text-xs text-texto-suave">{{ $usuario->institutional_email }}</p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($usuario->roles as $rolUsuario)
                                <x-ui.badge color="marca">{{ __('roles.'.$rolUsuario->name) }}</x-ui.badge>
                            @endforeach
                        </div>
                    </td>
                    <td>
                        <x-ui.badge :color="$usuario->is_active ? 'verde' : 'gris'">{{ $usuario->is_active ? __('Activo') : __('Inactivo') }}</x-ui.badge>
                    </td>
                    <td class="text-texto-suave">{{ $usuario->last_login_at?->timezone('America/El_Salvador')->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="text-right">
                        @if ($controlTotal && $usuario->id !== auth()->id())
                            @if ($usuario->is_active)
                                <x-ui.button tamano="sm" variante="peligro" wire:click="confirmarDesactivar({{ $usuario->id }})">{{ __('Desactivar') }}</x-ui.button>
                            @else
                                <x-ui.button tamano="sm" variante="fantasma" wire:click="reactivar({{ $usuario->id }})" wire:confirm="{{ __('¿Reactivar la cuenta de :n? Volverá a poder entrar.', ['n' => $usuario->fullName()]) }}">{{ __('Reactivar') }}</x-ui.button>
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        {{ $this->usuarios->links() }}
    </x-ui.card>

    @if ($controlTotal)
        <x-ui.modal nombre="desactivar-usuario" :titulo="__('Desactivar cuenta')">
            <form wire:submit="desactivar" id="form-desactivar" class="flex flex-col gap-4">
                <p class="text-sm text-texto-suave">{{ __('La persona no podrá entrar. Sus notas, entregas y asistencia se conservan y el cambio queda en la auditoría.') }}</p>
                <x-ui.field :etiqueta="__('Motivo')" para="motivo" error="motivo" requerido>
                    <x-ui.textarea id="motivo" wire:model="motivo" />
                </x-ui.field>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'desactivar-usuario')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-desactivar" variante="peligro-solido">{{ __('Desactivar') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif
</div>
