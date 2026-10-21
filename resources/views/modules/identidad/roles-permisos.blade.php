{{-- Roles y permisos (RF03). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Roles y permisos')" :subtitulo="__('Marque o desmarque un permiso para concederlo o quitarlo. Cada cambio queda en la auditoría. Los permisos no se crean desde aquí.')" />

    <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($roles as $nombre => $rol)
            <x-ui.stat :etiqueta="__('roles.'.$nombre)" :valor="$rol['usuarios']" :detalle="trans_choice('{1} 1 permiso|[0,*] :count permisos', count($rol['permisos']))" />
        @endforeach
    </div>

    <x-ui.alert tipo="aviso">{{ __('El alcance de cada permiso (global, departamento, carrera, secciones o propio) lo define la Matriz RBAC y lo aplica el sistema; aquí solo se concede o se quita.') }}</x-ui.alert>

    <div class="flex flex-wrap items-center gap-2">
        <x-ui.search class="min-w-64 flex-1" wire:model.live.debounce.300ms="buscar" :placeholder="__('Buscar un permiso (asistencia, notas.publicar…)')" />
        @if ($buscar !== '')
            <x-ui.button tamano="sm" variante="fantasma" icono="x-mark" wire:click="limpiar">{{ __('Limpiar filtros') }}</x-ui.button>
        @endif
    </div>

    <x-ui.table ajustar :vacio="__('Ningún permiso coincide con esa búsqueda.')">
        <x-slot:cabecera>
            <th>{{ __('Permiso') }}</th>
            @foreach (array_keys($roles) as $nombre)
                <th class="text-center">{{ __('roles.'.$nombre) }}</th>
            @endforeach
        </x-slot:cabecera>
        @foreach ($grupos as $grupo => $permisos)
            <tr class="bg-zinc-50"><td colspan="{{ count($roles) + 1 }}" class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ $grupo }}</td></tr>
            @foreach ($permisos as $permiso)
                <tr wire:key="permiso-{{ $permiso }}">
                    <td class="font-mono text-xs">{{ $permiso }}</td>
                    @foreach ($roles as $nombre => $rol)
                        @php($tiene = in_array($permiso, $rol['permisos'], true))
                        @php($bloqueado = $nombre === 'administrador' && $permiso === 'roles.gestionar')
                        <td class="text-center">
                            <input type="checkbox" @checked($tiene) @disabled($bloqueado)
                                wire:click="alternar('{{ $nombre }}', '{{ $permiso }}')"
                                aria-label="{{ __(':permiso para :rol', ['permiso' => $permiso, 'rol' => __('roles.'.$nombre)]) }}"
                                class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600 disabled:opacity-50">
                        </td>
                    @endforeach
                </tr>
            @endforeach
        @endforeach
    </x-ui.table>
</div>
