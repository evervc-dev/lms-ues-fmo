{{-- Vista de sección › Revisiones (RF35). --}}
@php
    $fecha = fn ($f) => \Illuminate\Support\Carbon::parse($f)->timezone('America/El_Salvador')->format('d/m/Y H:i');
    $num = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $colores = ['pending' => 'ambar', 'resolved_changed' => 'verde', 'resolved_unchanged' => 'gris'];
@endphp
<div class="flex flex-col gap-5 lg:h-full">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    <x-ui.card ajustar :titulo="__('Solicitudes de revisión')" :descripcion="__('Los estudiantes pueden pedir revisión durante :d días desde que se publica la nota y antes de publicar el acta.', ['d' => \App\Modules\Calificaciones\Services\RevisionService::plazoDias()])">
        <x-slot:acciones>
            <x-ui.select wire:model.live="estado" :aria-label="__('Estado')">
                @foreach ($estados as $valor => $etiqueta)<option value="{{ $valor }}">{{ __($etiqueta) }}</option>@endforeach
                <option value="">{{ __('Todas') }}</option>
            </x-ui.select>
        </x-slot:acciones>
        <x-ui.table ajustar :vacio="__('No hay solicitudes con ese estado.')">
            <x-slot:cabecera><th>{{ __('Estudiante') }}</th><th>{{ __('Actividad') }}</th><th>{{ __('Ítems') }}</th><th>{{ __('Nota') }}</th><th>{{ __('Estado') }}</th><th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
            @foreach ($solicitudes as $s)
                <tr wire:key="sol-{{ $s->ulid }}">
                    <td>{{ $s->first_name }} {{ $s->last_name }}<p class="text-xs text-texto-suave">{{ $fecha($s->requested_at) }}</p></td>
                    <td>{{ $s->title }}<p class="text-xs text-texto-suave">{{ $s->assessment_type === 'quiz' ? __('Cuestionario') : __('Tarea') }}</p></td>
                    <td>{{ $s->total_items }}</td>
                    <td>{{ $num($s->score_before) }}@if ($s->score_after !== null) → {{ $num($s->score_after) }}@endif</td>
                    <td><x-ui.badge :color="$colores[$s->status]">{{ __($estados[$s->status]) }}</x-ui.badge></td>
                    <td class="text-right"><x-ui.button tamano="sm" :variante="$s->status === 'pending' ? 'primario' : 'secundario'" :href="route('secciones.revisiones.show', ['seccion' => $seccion, 'revision' => $s->ulid])">{{ $s->status === 'pending' ? __('Revisar') : __('Ver') }}</x-ui.button></td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

</div>
