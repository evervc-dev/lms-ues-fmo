{{-- Diagnóstico de asistencia (RF20, maqueta 31). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Diagnóstico de asistencia')" :subtitulo="__('Cada intento de marcación con su motivo exacto y la distancia al área. Solo lectura: no se puede cambiar la asistencia desde aquí.')">
        <x-slot:acciones><x-ui.exportar :pdf="false" /></x-slot:acciones>
    </x-ui.encabezado>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat :etiqueta="__('Registros de hoy')" :valor="$hoy['ok'] ?? 0" icono="check-circle" tono="exito" />
        <x-ui.stat :etiqueta="__('Código vencido')" :valor="$hoy['token_expired'] ?? 0" icono="clock" tono="aviso" />
        <x-ui.stat :etiqueta="__('Fuera del área')" :valor="$hoy['out_of_range'] ?? 0" icono="map-pin" tono="error" />
        <x-ui.stat :etiqueta="__('Dispositivo inválido')" :valor="$hoy['invalid_device'] ?? 0" icono="device-phone-mobile" tono="error" />
    </div>

    <x-ui.card ajustar>
        <div class="mb-3 grid gap-3 md:grid-cols-4">
            <x-ui.search class="md:col-span-2" wire:model.live.debounce.300ms="buscar" :placeholder="__('Buscar estudiante por correo, carné o nombre…')" />
            <x-ui.select wire:model.live="motivo" :aria-label="__('Resultado')">
                <option value="{{ \App\Modules\Asistencia\Services\DiagnosticoService::SOLO_RECHAZOS }}">{{ __('Solo los rechazos') }}</option>
                <option value="">{{ __('Todos los resultados') }}</option>
                @foreach ($motivos as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ __($etiqueta) }}</option>
                @endforeach
            </x-ui.select>
            <div class="grid grid-cols-2 gap-2">
                <x-ui.input type="date" wire:model.live="desde" :aria-label="__('Desde')" />
                <x-ui.input type="date" wire:model.live="hasta" :aria-label="__('Hasta')" />
            </div>
        </div>

        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-texto-suave">
                @if ($conRango)
                    {{ __('Intentos del rango de fechas elegido (primeros :n).', ['n' => \App\Modules\Asistencia\Services\DiagnosticoService::MAXIMO_PANTALLA]) }}
                @elseif ($buscar !== '')
                    {{ __('Últimos intentos del estudiante buscado, de cualquier fecha (primeros :n).', ['n' => \App\Modules\Asistencia\Services\DiagnosticoService::MAXIMO_PANTALLA]) }}
                @else
                    {{ __('Mostrando los intentos de hoy. Elija un rango de fechas para ver otros días.') }}
                @endif
            </p>
            <div class="flex gap-2">
                <x-ui.button tamano="sm" variante="fantasma" wire:click="hoy">{{ __('Hoy') }}</x-ui.button>
                <x-ui.button tamano="sm" variante="fantasma" wire:click="limpiar">{{ __('Limpiar filtros') }}</x-ui.button>
            </div>
        </div>

        <x-ui.table ajustar :vacio="__('No hay intentos con esos filtros.')">
            <x-slot:cabecera><th>{{ __('Fecha y hora') }}</th><th>{{ __('Estudiante') }}</th><th>{{ __('Sesión') }}</th><th>{{ __('Resultado') }}</th><th class="text-right">{{ __('Distancia') }}</th></x-slot:cabecera>
            @foreach ($intentos as $i)
                <tr wire:key="intento-{{ $i->id }}">
                    <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($i->occurred_at)->timezone('America/El_Salvador')->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $i->first_name }} {{ $i->last_name }}<p class="text-xs text-texto-suave">{{ $i->student_code ?? $i->institutional_email }}</p></td>
                    <td>{{ $i->materia }} · {{ $i->grupo }}<p class="text-xs text-texto-suave">{{ \Illuminate\Support\Carbon::parse($i->session_date)->format('d/m/Y') }}</p></td>
                    <td><x-ui.badge :color="$i->result === 'ok' ? 'verde' : ($i->result === 'token_expired' ? 'ambar' : 'rojo')">{{ __($motivos[$i->result] ?? $i->result) }}</x-ui.badge></td>
                    <td class="text-right">{{ $i->distance_m !== null ? number_format($i->distance_m).' m' : '—' }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>
</div>
