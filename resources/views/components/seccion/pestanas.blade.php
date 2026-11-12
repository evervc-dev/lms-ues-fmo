{{-- Pestañas de la Vista de sección según el permiso del usuario (App\Support\Navegacion\Vistas::pestanas()). --}}
@props(['pestanas'])

<x-ui.tabs :pestanas="$pestanas" :etiqueta="__('Secciones del curso')" {{ $attributes }} />
