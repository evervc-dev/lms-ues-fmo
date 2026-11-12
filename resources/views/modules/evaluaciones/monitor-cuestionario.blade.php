{{-- Monitor del cuestionario (RF16, ADR-042). --}}
@php
    $hora = fn ($f) => \Illuminate\Support\Carbon::parse($f)->format('d/m/Y H:i');
    $estados = ['sin_empezar' => ['gris', __('Sin empezar')], 'en_curso' => ['ambar', __('En curso')], 'enviado' => ['verde', __('Enviado')]];
@endphp
<div class="flex flex-col gap-4 lg:h-full" @if ($enVivo) wire:poll.15s.keep-alive @endif>
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <x-ui.badge>{{ __('Sin empezar: :n', ['n' => $resumen['sin_empezar']]) }}</x-ui.badge>
        <x-ui.badge color="ambar">{{ __('En curso: :n', ['n' => $resumen['en_curso']]) }}</x-ui.badge>
        <x-ui.badge color="verde">{{ __('Enviados: :n', ['n' => $resumen['enviados']]) }}</x-ui.badge>
        @if ($enVivo)<span class="text-xs text-texto-suave">{{ __('Se actualiza cada 15 s') }}</span>@endif
        <span class="ml-auto"><x-ui.badge :color="$revisionVisible ? 'verde' : 'gris'">
            {{ $revisionVisible ? __('Corrección visible para los estudiantes') : __('La corrección se mostrará el :f', ['f' => $hora($revisionDesde)]) }}
        </x-ui.badge></span>
    </div>

    @if ($resumen['en_curso'] > 0)
        <div class="flex flex-wrap items-end gap-2 rounded-lg bg-zinc-50 p-3 text-sm">
            <x-ui.field :etiqueta="__('Tiempo extra')" para="minutos-extra" error="minutos">
                <x-ui.select id="minutos-extra" wire:model="minutos">
                    @foreach ([5, 10, 15, 20, 30] as $m)<option value="{{ $m }}">{{ __(':m min', ['m' => $m]) }}</option>@endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.button tamano="sm" variante="secundario" wire:click="darTiempo(false)" :disabled="$seleccionados === []">{{ __('A los marcados (:n)', ['n' => count($seleccionados)]) }}</x-ui.button>
            <x-ui.button tamano="sm" variante="secundario" wire:click="darTiempo(true)" wire:confirm="{{ __('¿Dar tiempo extra a todos los intentos en curso?') }}">{{ __('A todos los que están en curso') }}</x-ui.button>
            <p class="w-full text-xs text-texto-suave">{{ __('Hasta :t min después del cierre (:f). La corrección se muestra 10 min después de que termine el último intento.', ['t' => \App\Modules\Evaluaciones\Services\CuestionarioService::TIEMPO_EXTRA_MAXIMO, 'f' => $hora($topeExtra)]) }}</p>
        </div>
    @endif

    <x-ui.table ajustar :vacio="__('No hay estudiantes matriculados.')">
        <x-slot:cabecera><th class="w-8"></th><th>{{ __('Estudiante') }}</th><th>{{ __('Estado') }}</th><th>{{ __('Respondidas') }}</th><th>{{ __('Tiempo restante') }}</th><th class="text-right">{{ __('Nota') }}</th><th></th></x-slot:cabecera>
        @foreach ($filas as $f)
            <tr wire:key="mon-{{ $f->matricula }}">
                <td>@if ($f->estado === 'en_curso')<input type="checkbox" value="{{ $f->intento->ulid }}" wire:model.live="seleccionados" class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600" aria-label="{{ __('Marcar') }}">@endif</td>
                <td>{{ $f->first_name }} {{ $f->last_name }}</td>
                <td><x-ui.badge :color="$estados[$f->estado][0]">{{ $estados[$f->estado][1] }}</x-ui.badge></td>
                <td>{{ $f->intento ? $f->respondidas.' / '.$f->preguntas : '—' }}</td>
                <td>
                    @if ($f->segundos !== null)
                        <span class="font-mono" wire:key="reloj-{{ $f->intento->ulid }}-{{ $f->segundos }}"
                            x-data="{ s: {{ $f->segundos }} }" x-init="setInterval(() => { if (s > 0) s-- }, 1000)"
                            x-text="`${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`" :class="s < 60 ? 'text-red-700' : ''"></span>
                    @else
                        —
                    @endif
                </td>
                <td class="text-right">{{ $f->intento && $f->intento->score !== null ? number_format((float) $f->intento->score, 1) : '—' }}</td>
                <td class="text-right">
                    @if ($f->estado === 'en_curso')
                        <x-ui.button tamano="sm" variante="peligro" wire:click="cerrar('{{ $f->intento->ulid }}')" wire:confirm="{{ __('Se enviará con lo que el estudiante guardó. ¿Cerrar el intento?') }}">{{ __('Cerrar') }}</x-ui.button>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</div>
