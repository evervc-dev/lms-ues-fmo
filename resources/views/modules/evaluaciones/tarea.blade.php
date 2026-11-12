{{-- Tarea: detalle y entrega (RF15, maquetas 08 y 09). --}}
@php($fecha = fn ($f) => \Illuminate\Support\Carbon::parse($f)->timezone('America/El_Salvador')->format('d/m/Y H:i'))
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    <div class="grid gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <x-ui.card :titulo="$tarea->title">
            <x-slot:acciones>
                @if ($gestiona)<x-ui.button tamano="sm" :href="route('secciones.tareas.entregas', ['seccion' => $resumen['ulid'], 'tarea' => $tarea->ulid])">{{ __('Ver entregas') }}</x-ui.button>@endif
            </x-slot:acciones>
            @if ($tarea->instructions)<p class="whitespace-pre-line text-sm text-texto">{{ $tarea->instructions }}</p>@endif
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-texto-suave">{{ __('Abre') }}</dt><dd>{{ $fecha($tarea->opens_at) }}</dd></div>
                <div><dt class="text-texto-suave">{{ __('Cierra') }}</dt><dd class="font-semibold">{{ $fecha($tarea->closes_at) }}</dd></div>
                <div><dt class="text-texto-suave">{{ __('Política de entrega') }}</dt><dd>{{ __($politicas[$tarea->late_policy]) }}@if ($tarea->late_policy === 'grace_penalty') ({{ $tarea->late_penalty_percent }} %)@endif @if ($tarea->grace_until) · {{ __('hasta :f', ['f' => $fecha($tarea->grace_until)]) }}@endif</dd></div>
                <div><dt class="text-texto-suave">{{ __('Archivos permitidos') }}</dt><dd>{{ $tarea->extensiones ? implode(', ', $tarea->extensiones) : __('cualquiera') }} · {{ __('máx. :n MB', ['n' => $tarea->max_file_mb]) }}</dd></div>
                <div><dt class="text-texto-suave">{{ __('Intentos') }}</dt><dd>{{ $tarea->max_attempts }}</dd></div>
                <div><dt class="text-texto-suave">{{ __('Peso') }}</dt><dd>{{ rtrim(rtrim(number_format((float) $tarea->weight_percent, 2, '.', ''), '0'), '.') }} % {{ __('del cómputo') }}</dd></div>
            </dl>
        </x-ui.card>

        @if ($esEstudiante)
            <x-ui.card :titulo="$tarea->is_group ? __('Entrega de mi equipo') : __('Mi entrega')">
                <div class="flex flex-col gap-4 text-sm">
                    {{-- RF15: en una tarea grupal, la entrega de cualquier integrante cuenta para todo el equipo. --}}
                    @if ($tarea->is_group)
                        @if ($equipo)
                            <p><span class="font-medium">{{ __('Equipo :e', ['e' => $equipo->name]) }}:</span> {{ $equipo->integrantes->map(fn ($i) => $i->first_name.' '.$i->last_name)->join(', ') }}. {{ __('Lo que entregue cualquiera de ustedes cuenta para todo el equipo.') }}</p>
                        @else
                            <x-ui.alert tipo="info">{{ __('Es una tarea grupal y todavía no tiene equipo. Su docente arma los equipos; cuando le asigne uno, podrá entregar.') }}</x-ui.alert>
                        @endif
                    @endif
                    @if ($entrega)
                        <x-ui.alert :tipo="$entrega->is_late ? 'aviso' : 'exito'" :titulo="$entrega->is_late ? __('Entregada tarde') : __('Entregada')">
                            {{ $fecha($entrega->submitted_at) }} · {{ $archivos->pluck('original_name')->join(', ') }}
                            @if ($entrega->late_penalty_applied > 0) · {{ __('penalización :p %', ['p' => rtrim(rtrim(number_format((float) $entrega->late_penalty_applied, 2, '.', ''), '0'), '.')]) }}@endif
                        </x-ui.alert>
                        @if ($estado === 'abierta')<x-ui.button variante="peligro" tamano="sm" wire:click="cancelar" wire:confirm="{{ __('¿Cancelar su entrega?') }}">{{ __('Cancelar entrega') }}</x-ui.button>@endif
                    @endif

                    @if ($nota)
                        <div class="rounded-lg bg-marca-50 p-4 text-center ring-1 ring-marca-200">
                            <p class="text-xs uppercase text-texto-suave">{{ __('Nota') }}</p>
                            <p class="text-3xl font-semibold text-marca-700">{{ number_format((float) $nota->score, 1) }}</p>
                            @if ($nota->score_before_penalty !== null && (float) $nota->score_before_penalty > (float) $nota->score)
                                <p class="mt-1 text-xs text-texto-suave">{{ __(':antes antes de la penalización por entrega tardía', ['antes' => number_format((float) $nota->score_before_penalty, 1)]) }}</p>
                            @endif
                        </div>
                    @endif
                    @if ($desglose->isNotEmpty())
                        <x-ui.table>
                            <x-slot:cabecera><th>{{ __('Criterio') }}</th><th>{{ __('Nivel') }}</th><th class="text-right">{{ __('Puntos') }}</th></x-slot:cabecera>
                            @foreach ($desglose as $fila)
                                <tr wire:key="desglose-{{ $fila->id }}"><td>{{ $fila->criterio }}</td><td>{{ $fila->nivel }}</td><td class="text-right">{{ rtrim(rtrim(number_format((float) $fila->points, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format((float) $fila->maximo, 2), '0'), '.') }}</td></tr>
                            @endforeach
                        </x-ui.table>
                    @endif
                    @foreach ($retroalimentacion as $comentario)<x-ui.alert tipo="info" :titulo="__('Retroalimentación')">{{ $comentario }}</x-ui.alert>@endforeach

                    @if (in_array($estado, ['abierta', 'gracia', 'prorroga'], true) && ! $entrega)
                        @if ($estado === 'gracia')<x-ui.alert tipo="aviso">{{ __('La tarea cerró: si entrega ahora quedará marcada como tardía.') }}</x-ui.alert>@endif
                        @if ($estado === 'prorroga')<x-ui.alert tipo="info">{{ __('Tiene prórroga hasta el :f: su entrega cuenta a tiempo.', ['f' => $prorroga->timezone('America/El_Salvador')->format('d/m/Y H:i')]) }}</x-ui.alert>@endif
                        <form wire:submit="entregar" class="flex flex-col gap-3">
                            <x-ui.field :etiqueta="__('Archivo')" para="archivoEntrega" error="archivo" requerido>
                                <input id="archivoEntrega" type="file" wire:model="archivo" class="text-sm" @if ($tarea->extensiones) accept="{{ collect($tarea->extensiones)->map(fn ($e) => '.'.$e)->join(',') }}" @endif>
                            </x-ui.field>
                            <div wire:loading wire:target="archivo" class="text-xs text-texto-suave">{{ __('Subiendo…') }}</div>
                            <x-ui.button type="submit" icono="arrow-up-tray">{{ __('Enviar entrega') }}</x-ui.button>
                        </form>
                    @elseif (! $entrega)
                        <p class="text-texto-suave">{{ $estado === 'proxima' ? __('La tarea aún no abre.') : __('La tarea cerró sin entrega.') }}</p>
                    @endif
                </div>
            </x-ui.card>
            @if ($entrega)
                <livewire:calificaciones.solicitud-revision :seccion="$seccion" :actividad="$tarea->ulid" :key="'revision-'.$tarea->ulid" />
            @endif
        @endif
    </div>
</div>
