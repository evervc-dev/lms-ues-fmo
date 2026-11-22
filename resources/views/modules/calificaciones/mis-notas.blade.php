{{-- Mis notas (RF17, maquetas 11 y 12). --}}
@php($fmt = fn ($n) => number_format((float) $n, 1))
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Mis notas')" :subtitulo="__('Boleta del período en curso con las notas publicadas. Se aprueba con :m.', ['m' => $fmt($minima)])" />

    @if ($materias->isEmpty())
        <x-ui.card><x-ui.empty-state icono="chart-bar" :titulo="__('Aún no tiene materias en el período')" /></x-ui.card>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($materias as $m)
                <a href="{{ route('secciones.notas', ['seccion' => $m->ulid]) }}" wire:navigate class="rounded-xl bg-white p-4 ring-1 ring-borde transition hover:ring-marca-300" wire:key="nota-{{ $m->seccion_id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div><x-ui.badge color="marca">{{ $m->code }}</x-ui.badge><h2 class="mt-1 font-semibold text-texto">{{ $m->name }}</h2></div>
                        {{-- Sin ninguna nota publicada no hay promedio: un 0.0 en rojo parecía una materia reprobada. --}}
                        @if (collect($m->computos)->every(fn ($c) => $c->pendiente))
                            <span class="text-right text-sm text-texto-suave">{{ __('Sin notas aún') }}</span>
                        @else
                            <span @class(['text-2xl font-semibold', 'text-emerald-700' => $m->final >= $minima, 'text-red-700' => $m->final < $minima])>{{ $fmt($m->final) }}</span>
                        @endif
                    </div>
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                        {{-- Un cómputo sin notas en el grupo nuevo conserva el del grupo anterior (ADR-062). --}}
                        @foreach ($m->computos as $c)<div class="rounded-lg bg-zinc-50 p-2"><dt class="text-texto-suave">{{ $c->nombre }} · {{ rtrim(rtrim(number_format($c->peso, 2), '0'), '.') }} %</dt><dd @class(['text-sm font-semibold', 'font-normal text-texto-suave' => $c->pendiente])>{{ $c->pendiente ? __('Pendiente') : $fmt($c->nota) }}</dd>
                            @if ($c->heredadoDe)<dd class="mt-1"><x-ui.badge color="azul" :title="__('Esta nota la trae de su grupo anterior.')">{{ __('del grupo :g', ['g' => $c->heredadoDe]) }}</x-ui.badge></dd>@endif
                        </div>@endforeach
                    </dl>
                </a>
            @endforeach
        </div>

        {{-- Actividad por actividad: qué puso el docente y dónde ver su retroalimentación (QA 17/11). --}}
        <x-ui.card :titulo="__('Notas por actividad')" :descripcion="__('Cada nota publicada en el período. Abra una actividad para ver su retroalimentación y su rúbrica.')">
            <x-ui.table :vacio="__('Todavía no le han publicado ninguna nota.')">
                <x-slot:cabecera><th>{{ __('Asignatura') }}</th><th>{{ __('Actividad') }}</th><th>{{ __('Cómputo') }}</th><th class="text-right">{{ __('Nota') }}</th></x-slot:cabecera>
                @foreach ($materias as $m)
                    @foreach ($m->actividades as $a)
                        <tr wire:key="act-{{ $m->seccion_id }}-{{ $loop->index }}">
                            <td class="text-texto-suave">{{ $m->code }}</td>
                            <td>
                                @php([$ruta, $parametro] = $a->tipo === 'quiz' ? ['secciones.cuestionarios.show', 'cuestionario'] : ['secciones.tareas.show', 'tarea'])
                                @if ($a->ulid)
                                    <a href="{{ route($ruta, ['seccion' => $m->ulid, $parametro => $a->ulid]) }}" wire:navigate class="text-marca-700 hover:underline">{{ $a->titulo }}</a>
                                @else
                                    {{ $a->titulo }}
                                @endif
                            </td>
                            <td class="text-texto-suave">{{ $a->computo }}</td>
                            <td @class(['text-right font-semibold', 'text-emerald-700' => $a->nota !== null && $a->nota >= $minima, 'text-red-700' => $a->nota !== null && $a->nota < $minima])>{{ $a->nota === null ? '—' : $fmt($a->nota) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif

    <x-ui.card :titulo="__('Historial')" :descripcion="__('Notas finales de los períodos cerrados.')">
        <x-ui.table :vacio="__('Aún no hay períodos cerrados.')">
            <x-slot:cabecera><th>{{ __('Período') }}</th><th>{{ __('Asignatura') }}</th><th class="text-right">{{ __('Nota final') }}</th><th>{{ __('Resultado') }}</th></x-slot:cabecera>
            @foreach ($historial as $h)
                <tr><td>{{ $h->periodo }}</td><td><span class="font-semibold">{{ $h->code }}</span> · {{ $h->name }}</td><td class="text-right">{{ $fmt($h->final_score) }}</td>
                    <td><x-ui.badge :color="$h->result === 'passed' ? 'verde' : 'rojo'">{{ $h->result === 'passed' ? __('Aprobada') : __('Reprobada') }}</x-ui.badge></td></tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>
</div>
