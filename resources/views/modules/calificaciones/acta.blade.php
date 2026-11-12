{{-- Acta de la sección (RF34, maqueta 48). --}}
@php
    $estados = ['draft' => ['gris', __('Borrador')], 'published' => ['azul', __('Publicada, en validación')], 'returned' => ['ambar', __('Devuelta')], 'validated' => ['verde', __('Validada')]];
    $estado = $acta->status ?? 'draft';
@endphp
<div class="flex flex-col gap-5 lg:h-full">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    <div class="grid gap-3 sm:grid-cols-4">
        <x-ui.stat :etiqueta="__('Estudiantes')" :valor="$filas->count()" icono="users" />
        <x-ui.stat :etiqueta="__('Aprobados')" :valor="$filas->where('aprobado', true)->count()" icono="check-circle" tono="exito" />
        <x-ui.stat :etiqueta="__('Reprobados')" :valor="$filas->where('aprobado', false)->count()" icono="x-circle" tono="error" />
        <x-ui.stat :etiqueta="__('Promedio')" :valor="$filas->isEmpty() ? '—' : number_format($filas->avg('final'), 1)" icono="chart-bar" />
    </div>

    <x-ui.card ajustar :titulo="__('Acta de notas')">
        <x-slot:acciones>
            <x-ui.badge :color="$estados[$estado][0]">{{ $estados[$estado][1] }}</x-ui.badge>
            @if (in_array($estado, ['draft', 'returned'], true))
                <x-ui.button tamano="sm" wire:click="publicar" wire:confirm="{{ $faltantes > 0 ? __(':n notas faltantes contarán como 0. ¿Publicar el acta de todos modos?', ['n' => $faltantes]) : __('Se publicará el acta y no podrá cambiar notas hasta que el Coordinador la devuelva. ¿Publicar el acta?') }}">{{ __('Publicar acta') }}</x-ui.button>
            @endif
        </x-slot:acciones>
        @if ($faltantes > 0 && in_array($estado, ['draft', 'returned'], true))
            <x-ui.alert class="mb-4" tipo="aviso">{{ __(':n notas faltantes contarán como 0', ['n' => $faltantes]) }}</x-ui.alert>
        @endif
        @if ($estado === 'returned' && $acta->return_reason)
            <x-ui.alert class="mb-4" tipo="aviso" :titulo="__('Observaciones del Coordinador')">{{ $acta->return_reason }}</x-ui.alert>
        @endif
        <x-ui.table ajustar :vacio="__('No hay estudiantes activos.')">
            <x-slot:cabecera><th>{{ __('Estudiante') }}</th><th>{{ __('Carné') }}</th><th class="text-right">{{ __('Nota final') }}</th><th>{{ __('Resultado') }}</th></x-slot:cabecera>
            @foreach ($filas as $f)
                <tr><td>{{ $f->last_name }}, {{ $f->first_name }}</td><td>{{ $f->student_code }}</td><td class="text-right font-semibold">{{ number_format($f->final, 1) }}</td>
                    <td><x-ui.badge :color="$f->aprobado ? 'verde' : 'rojo'">{{ $f->aprobado ? __('Aprobado') : __('Reprobado') }}</x-ui.badge></td></tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>
</div>
