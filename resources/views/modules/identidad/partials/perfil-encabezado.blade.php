{{-- Encabezado y pestañas de Mi perfil; lo comparten Perfil y Google Calendar (RF21). Espera $usuario y $pestanas. --}}
<div class="flex flex-wrap items-center gap-4">
    <x-ui.avatar :iniciales="$usuario->iniciales()" :foto="$usuario->avatar_url" tamano="lg" />
    <x-ui.encabezado :titulo="$usuario->fullName()" :subtitulo="$usuario->institutional_email">
        <div class="mt-2 flex flex-wrap gap-1">
            @foreach ($usuario->getRoleNames() as $rol)
                <x-ui.badge color="marca">{{ __('roles.'.$rol) }}</x-ui.badge>
            @endforeach
        </div>
    </x-ui.encabezado>
</div>

<x-ui.tabs :pestanas="$pestanas" :etiqueta="__('Secciones de mi perfil')" />
