{{-- Secciones por ámbito. --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="$titulo" :subtitulo="$soloLectura ? __('Grupos teóricos de su carrera en el período en curso. Puede consultar sus contenidos, notas, asistencia y progreso sin modificarlos, y gestionar la matrícula de sus estudiantes.') : __('Grupos teóricos del período en curso.')" />

    @if ($pestanas)
        <x-ui.tabs :pestanas="$pestanas" />
    @endif

    <x-ui.search wire:model.live.debounce.300ms="buscar" :placeholder="__('Buscar por código o asignatura…')" class="max-w-md" />

    @if ($secciones->isEmpty())
        <x-ui.card><x-ui.empty-state icono="academic-cap" :titulo="__('No hay grupos teóricos')">{{ __('No tiene grupos teóricos en el período en curso con esos filtros.') }}</x-ui.empty-state></x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($secciones as $s)
                <a href="{{ route($ambito === 'departamento' ? 'secciones.estudiantes' : 'secciones.contenidos', ['seccion' => $s->ulid]) }}" wire:navigate wire:key="sec-{{ $s->id }}"
                    class="flex flex-col gap-3 rounded-xl bg-white p-4 ring-1 ring-borde transition hover:ring-marca-300">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex flex-wrap gap-1"><x-ui.badge color="marca">{{ $s->code }}</x-ui.badge><x-ui.badge>{{ __('Grupo :g', ['g' => $s->grupo]) }}</x-ui.badge>@if ($s->plan)<x-ui.badge color="azul">{{ $s->plan }}</x-ui.badge>@endif</div>
                            <h2 class="mt-2 font-semibold text-texto">{{ $s->name }}</h2>
                        </div>
                        {{-- Rojo solo cuando las alertas son una parte grande del grupo (20 % o más); si no, en ámbar. Con casi
                             todas las tarjetas en rojo, ninguna destacaba (revisión UX del 22/11/2026). --}}
                        @if ($s->alertas > 0)
                            <x-ui.badge :color="$s->inscritos > 0 && $s->alertas / $s->inscritos >= 0.2 ? 'rojo' : 'ambar'" class="shrink-0">{{ trans_choice('{1} 1 en alerta|[2,*] :count en alerta', $s->alertas) }}</x-ui.badge>
                        @endif
                    </div>
                    <p class="text-sm text-texto-suave">{{ $s->docente ?? __('Sin docente') }}{{ $s->horario ? ' · '.$s->horario : '' }}</p>
                    <x-ui.progress :valor="$s->max_capacity ? 100 * $s->inscritos / $s->max_capacity : 0" :mostrar="false" :etiqueta="__('Cupo usado')" />
                    <p class="text-xs text-texto-suave">{{ __(':n de :m inscritos', ['n' => $s->inscritos, 'm' => $s->max_capacity]) }}</p>
                </a>
            @endforeach
        </div>
    @endif
</div>
