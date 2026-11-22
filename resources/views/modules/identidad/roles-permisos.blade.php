{{-- Roles y permisos (RF03). Los cambios se acumulan y se guardan juntos (QA admin 21/11). --}}
@php($pendientes = count($cambios))
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Roles y permisos')" :subtitulo="__('Marque o desmarque permisos y pulse «Guardar cambios». Cada cambio queda en la auditoría. Los permisos no se crean desde aquí.')" />

    <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach ($roles as $nombre => $rol)
            <x-ui.stat :etiqueta="__('roles.'.$nombre)" :valor="number_format($rol['usuarios'])"
                :detalle="trans_choice('{1} 1 cuenta con este rol|[0,*] :count cuentas con este rol', $rol['usuarios']).' · '.trans_choice('{1} 1 permiso|[0,*] :count permisos', count($rol['permisos']))" />
        @endforeach
    </div>

    <x-ui.alert tipo="info">{{ __('Cada permiso se aplica con el alcance propio del rol: todo el sistema, su departamento, su carrera, sus grupos teóricos o solo lo suyo. Aquí solo se concede o se quita.') }}</x-ui.alert>

    <div class="flex flex-wrap items-center gap-2">
        <x-ui.search class="min-w-64 flex-1" wire:model.live.debounce.300ms="buscar" :placeholder="__('Buscar un permiso (asistencia, publicar actas…)')" />
        @if ($buscar !== '')
            <x-ui.button tamano="sm" variante="fantasma" icono="x-mark" wire:click="limpiar">{{ __('Limpiar filtros') }}</x-ui.button>
        @endif
        {{-- En el teléfono no caben las seis columnas: se elige el rol y se ve solo esa. --}}
        <x-ui.select class="w-full sm:hidden" wire:model.live="rolMovil" :aria-label="__('Rol')">
            @foreach (array_keys($roles) as $nombre)
                <option value="{{ $nombre }}">{{ __('Rol: :rol', ['rol' => __('roles.'.$nombre)]) }}</option>
            @endforeach
        </x-ui.select>
    </div>

    @if ($pendientes > 0)
        <div class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-amber-200" role="status">
            <p class="text-sm font-medium text-amber-900">{{ trans_choice('{1} Tiene 1 cambio sin guardar.|[2,*] Tiene :count cambios sin guardar.', $pendientes) }}</p>
            <div class="flex gap-2">
                <x-ui.button tamano="sm" variante="secundario" wire:click="descartar">{{ __('Descartar') }}</x-ui.button>
                <x-ui.button tamano="sm" icono="check-circle" wire:click="guardar" wire:loading.attr="disabled">{{ __('Guardar cambios') }}</x-ui.button>
            </div>
        </div>
    @endif

    <x-ui.table ajustar :vacio="__('Ningún permiso coincide con esa búsqueda.')">
        <x-slot:cabecera>
            <th>{{ __('Permiso') }}</th>
            @foreach (array_keys($roles) as $nombre)
                <th @class(['text-center', 'hidden sm:table-cell' => $nombre !== $rolMovil])>{{ __('roles.'.$nombre) }}</th>
            @endforeach
        </x-slot:cabecera>
        @foreach ($grupos as $grupo => $permisos)
            <tr class="bg-zinc-50"><td colspan="{{ count($roles) + 1 }}" class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ \App\Modules\Identidad\Services\CatalogoPermisos::grupo($grupo) }}</td></tr>
            @foreach ($permisos as $permiso)
                @php($etiqueta = \App\Modules\Identidad\Services\CatalogoPermisos::de($permiso))
                <tr wire:key="permiso-{{ $permiso }}">
                    <td>
                        <p class="text-sm font-medium text-texto">{{ $etiqueta['nombre'] }}</p>
                        @if ($etiqueta['descripcion'] !== '')
                            <p class="text-xs text-texto-suave">{{ $etiqueta['descripcion'] }}</p>
                        @endif
                        <p class="font-mono text-[11px] text-texto-desactivado">{{ $permiso }}</p>
                    </td>
                    @foreach ($roles as $nombre => $rol)
                        @php($clave = $nombre.'|'.$permiso)
                        @php($cambiado = array_key_exists($clave, $cambios))
                        @php($tiene = $cambios[$clave] ?? in_array($permiso, $rol['permisos'], true))
                        @php($bloqueado = $nombre === 'administrador' && $permiso === 'roles.gestionar')
                        <td @class(['text-center', 'bg-amber-50' => $cambiado, 'hidden sm:table-cell' => $nombre !== $rolMovil])>
                            <input type="checkbox" @checked($tiene) @disabled($bloqueado)
                                wire:click="marcar('{{ $nombre }}', '{{ $permiso }}')"
                                aria-label="{{ __(':permiso para :rol', ['permiso' => $etiqueta['nombre'], 'rol' => __('roles.'.$nombre)]) }}"
                                @if ($cambiado) title="{{ __('Cambio sin guardar') }}" @endif
                                class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600 disabled:opacity-50">
                        </td>
                    @endforeach
                </tr>
            @endforeach
        @endforeach
    </x-ui.table>
</div>
