{{-- Inicio: panel por rol (RF24). Bloques reales por rol del usuario, en el orden del menú. --}}
@php
    $fecha = fn ($f) => \Illuminate\Support\Carbon::parse($f)->timezone('America/El_Salvador');
    $alertas = ['attendance_85' => __('Asistencia < 85 %'), 'attendance_78' => __('Asistencia < 78 %'), 'attendance_75' => __('Asistencia < 75 %'), 'grade_risk' => __('Riesgo de reprobar')];
    $varios = count($bloques) > 1;
@endphp
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Hola, :nombre', ['nombre' => $usuario->first_name])" :subtitulo="now()->timezone('America/El_Salvador')->translatedFormat('l j \d\e F')" />

    @if ($bloques === [])
        <x-ui.card><x-ui.empty-state icono="home" :titulo="__('Su cuenta está activa pero aún no tiene un rol asignado')">{{ __('Comuníquese con su coordinación o con Soporte técnico.') }}</x-ui.empty-state></x-ui.card>
    @endif

    {{-- Con un solo rol no hace falta plegar nada: el bloque va directo y el documento mantiene su
         jerarquía de encabezados (h1 → h2). El acordeón aparece solo cuando hay varios roles. --}}
    <x-ui.accordion @class(['!bg-transparent !ring-0 [&>div]:mb-4 [&>div]:rounded-xl [&>div]:bg-white [&>div]:ring-1 [&>div]:ring-borde divide-y-0', 'contents' => ! $varios])>
        @foreach ($bloques as $rol => $b)
            @php($datos = ['rol' => $rol, 'b' => $b, 'fecha' => $fecha, 'alertas' => $alertas])
            @if ($varios)
                <x-ui.accordion.item :titulo="__('roles.'.$rol)" :abierto="true" :recordar="'panel.'.$rol" wire:key="bloque-{{ $rol }}">
                    @include('modules.reportes.partials.bloque-rol', $datos)
                </x-ui.accordion.item>
            @else
                @include('modules.reportes.partials.bloque-rol', $datos)
            @endif
        @endforeach
    </x-ui.accordion>
</div>
