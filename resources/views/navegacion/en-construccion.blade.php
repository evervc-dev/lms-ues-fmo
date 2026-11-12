{{--
    Página provisional del registro de vistas (ADR-035). Se reemplaza al implementar la vista con una
    ruta del mismo nombre en su módulo. Muestra lo que el equipo necesita: RF, tarea, hito y maqueta.
--}}
@php
    $titulo = __($vista['titulo']);
@endphp

@if ($layout === 'exam')
    <x-layouts::exam :title="$titulo">
        @include('navegacion.partials.ficha')
    </x-layouts::exam>
@elseif ($layout === 'projection')
    <x-layouts::projection :title="$titulo">
        <div class="w-full rounded-2xl bg-white p-6 text-texto">@include('navegacion.partials.ficha')</div>
    </x-layouts::projection>
@else
    <x-layouts::app :title="$seccion ? null : $titulo" :migas="$migas">
        @if ($seccion)
            <x-seccion.cabecera :seccion="$seccion" />
            <x-seccion.pestanas :pestanas="$pestanas" />
            <h1 class="text-xl font-semibold text-texto">{{ $titulo }}</h1>
        @endif
        @include('navegacion.partials.ficha')
    </x-layouts::app>
@endif
