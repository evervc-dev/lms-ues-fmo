{{-- Áreas de asistencia (RF26, maqueta 34). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Áreas de asistencia')" :subtitulo="__('El estudiante debe estar dentro de un área activa para marcar con QR. Defínala por centro y radio o por polígono, con margen por la imprecisión del GPS.')">
        <x-slot:acciones><x-ui.button icono="plus" :href="route('areas-asistencia.crear')">{{ __('Nueva área') }}</x-ui.button></x-slot:acciones>
    </x-ui.encabezado>
    {{-- QA sesión 4: se leía como un área por carrera o materia. Es la geocerca del campus (RF26). --}}
    <x-ui.alert tipo="info" :titulo="__('Las áreas valen para todas las clases presenciales')">
        {{ __('No se asignan a una carrera, asignatura ni docente: el QR de cualquier sesión presencial se acepta si el estudiante está dentro de cualquier área activa. El nombre solo sirve para reconocerla en esta lista. Para dejar de aceptar un lugar, desactive su área.') }}
    </x-ui.alert>
    <x-ui.table ajustar :vacio="__('No hay áreas configuradas.')">
        <x-slot:cabecera><th>{{ __('Área') }}</th><th>{{ __('Centro') }}</th><th>{{ __('Forma') }}</th><th>{{ __('Estado') }}</th><th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
        @foreach ($areas as $a)
            <tr wire:key="area-{{ $a->id }}">
                <td class="font-medium">{{ $a->name }}</td>
                <td class="font-mono text-xs">{{ number_format((float) $a->center_lat, 5) }}, {{ number_format((float) $a->center_lng, 5) }}</td>
                <td>{{ $a->area_type === 'polygon' ? __('Polígono · :n vértices', ['n' => count(json_decode((string) $a->polygon, true) ?? [])]) : __('Círculo · :m m', ['m' => $a->radius_m]) }}</td>
                <td><x-ui.badge :color="$a->is_active ? 'verde' : 'gris'">{{ $a->is_active ? __('Activa') : __('Inactiva') }}</x-ui.badge></td>
                <td class="text-right whitespace-nowrap">
                    <x-ui.button tamano="sm" variante="fantasma" :href="route('areas-asistencia.editar', ['area' => $a->id])">{{ __('Editar') }}</x-ui.button>
                    <x-ui.button tamano="sm" variante="secundario" wire:click="alternar({{ $a->id }})">{{ $a->is_active ? __('Desactivar') : __('Activar') }}</x-ui.button>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</div>
