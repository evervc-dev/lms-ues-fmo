{{--
    Recuadro grande de una materia del estudiante (RF24, maqueta 02 «Mis materias activas»).
    Variables: $m (fila de PanelService::materiasDelEstudiante).
--}}
@php($dias = ['', __('Lun'), __('Mar'), __('Mié'), __('Jue'), __('Vie'), __('Sáb'), __('Dom')])

<article class="flex flex-col gap-3 rounded-xl bg-white p-4 ring-1 ring-borde transition hover:ring-marca-300">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-1.5">
                <x-ui.badge color="marca">{{ $m->code }}</x-ui.badge>
                <span class="text-xs text-texto-suave">{{ __('Grupo :g', ['g' => $m->grupo]) }}</span>
            </div>
            <h3 class="mt-1.5 truncate text-base font-semibold text-texto">{{ $m->name }}</h3>
            @if ($m->docente)
                <p class="truncate text-sm text-texto-suave">{{ $m->docente }}</p>
            @endif
        </div>

        @if ($m->porcentaje !== null)
            <span @class([
                'shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold',
                'bg-red-50 text-red-700' => $m->nivel === 'critico',
                'bg-amber-50 text-amber-700' => in_array($m->nivel, ['aviso', 'riesgo'], true),
                'bg-emerald-50 text-emerald-700' => $m->nivel === 'bien',
            ])>{{ __('asistencia :p %', ['p' => $m->porcentaje]) }}</span>
        @endif
    </div>

    <div>
        <div class="flex items-baseline justify-between text-xs">
            <span class="text-texto-suave">{{ __('Avance del curso') }}</span>
            <span class="font-semibold text-texto">{{ $m->avance !== null ? $m->avance.' %' : '—' }}</span>
        </div>
        <x-ui.progress class="mt-1" :valor="$m->avance ?? 0" :mostrar="false"
            :etiqueta="__('Avance de :m', ['m' => $m->code])" />
        <p class="mt-1 text-xs text-texto-suave">
            {{ $m->recursos['total'] > 0
                ? __(':h de :t recursos', ['h' => $m->recursos['hechos'], 't' => $m->recursos['total']])
                : __('Aún sin material publicado') }}
        </p>
    </div>

    <div class="mt-auto flex items-center justify-between gap-3 border-t border-borde pt-3">
        <p class="truncate text-xs text-texto-suave">
            @forelse ($m->horario as $h)
                {{ $dias[$h->weekday] ?? '' }} {{ \Illuminate\Support\Str::of($h->start_time)->substr(0, 5) }}@if (! $loop->last) · @endif
            @empty
                {{ __('Sin horario asignado') }}
            @endforelse
        </p>
        <a href="{{ route('secciones.contenidos', ['seccion' => $m->ulid]) }}" wire:navigate
            class="shrink-0 text-sm font-medium text-marca-700 hover:underline">{{ __('Ingresar →') }}</a>
    </div>
</article>
