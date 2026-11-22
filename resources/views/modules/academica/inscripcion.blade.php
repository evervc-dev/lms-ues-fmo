{{-- Inscripción de materias (RF29, ADR-050): materias de la carrera del estudiante para el período en curso,
     buscables y agrupadas por el ciclo del plan. «Ciclo» queda solo para el nivel del plan; el semestre es el
     período («2026-II»), como en el resto de la plataforma (revisión UX del rol Estudiante). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Inscripción de materias')"
        :subtitulo="$periodo ? ($plan ? __('Período :p · materias de su :plan que se imparten en este período.', ['p' => $periodo, 'plan' => $plan]) : __('Período :p · materias de su carrera que se imparten en este período.', ['p' => $periodo])) : __('No hay un período en curso.')" />

    @if ($plazo['vencido'])
        <x-ui.alert tipo="aviso">{{ __('El plazo de inscripción terminó el :fecha. Si necesita inscribirse, pida una prórroga a su coordinador.', ['fecha' => $plazo['fecha']->format('d/m/Y')]) }}</x-ui.alert>
    @else
        <x-ui.alert tipo="info">{{ __('Inscripción abierta hasta el :fecha.', ['fecha' => $plazo['fecha']->format('d/m/Y')]) }}</x-ui.alert>
    @endif

    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <x-ui.search class="flex-1" wire:model.live.debounce.300ms="buscar" :placeholder="__('Buscar materia por código o nombre…')" />
        <label class="flex items-center gap-2 text-sm text-texto">
            <input type="checkbox" wire:model.live="soloConGrupos" class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600">
            {{ __('Solo con grupos abiertos') }}
        </label>
    </div>

    @if ($total === 0)
        <x-ui.card>
            @if ($buscando)
                <x-ui.empty-state icono="magnifying-glass" :titulo="__('Ninguna materia coincide con su búsqueda')">
                    {{ $soloConGrupos ? __('Quite «Solo con grupos abiertos» para ver todas las materias.') : __('Pruebe con el código de la materia, por ejemplo PRN115, o con una palabra de su nombre.') }}
                </x-ui.empty-state>
            @else
                <x-ui.empty-state icono="book-open" :titulo="__('No hay materias para inscribir')">
                    {{ __('Su carrera no tiene materias de este ciclo o no hay un período en curso. Si cree que es un error, consulte a su coordinador.') }}
                </x-ui.empty-state>
            @endif
        </x-ui.card>
    @else
        @foreach ($porCiclo as $cicloDelPlan => $materias)
            <section wire:key="ciclo-{{ $cicloDelPlan }}" class="flex flex-col gap-3">
                <h2 class="flex items-baseline gap-2 text-sm font-semibold uppercase tracking-wide text-texto-suave">
                    {{ $plan ? __('Ciclo :n del :plan', ['n' => $cicloDelPlan, 'plan' => $plan]) : __('Ciclo :n del plan', ['n' => $cicloDelPlan]) }}
                    <span class="text-xs font-normal normal-case tracking-normal">{{ trans_choice('{1} 1 materia|[2,*] :count materias', $materias->count()) }}</span>
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($materias as $m)
                        <a wire:key="inscribible-{{ $m->id }}" href="{{ route('inscripcion.show', ['asignatura' => $m->id]) }}" wire:navigate
                            @class(['flex flex-col gap-3 rounded-xl p-4 ring-1 ring-borde transition hover:ring-marca-300',
                                'bg-white' => $m->secciones > 0 || $m->inscrito,
                                'bg-zinc-50 opacity-70' => $m->secciones === 0 && ! $m->inscrito])>
                            <div class="flex flex-wrap gap-1">
                                <x-ui.badge color="marca">{{ $m->code }}</x-ui.badge>
                                @if ($m->inscrito)
                                    <x-ui.badge color="verde">{{ __('Inscrito') }}</x-ui.badge>
                                @elseif ($m->secciones === 0)
                                    <x-ui.badge color="ambar">{{ __('Sin grupos abiertos') }}</x-ui.badge>
                                @elseif (! $m->tiene_clave)
                                    <x-ui.badge>{{ __('Inscripción no habilitada') }}</x-ui.badge>
                                @endif
                            </div>
                            <h3 class="font-semibold text-texto">{{ $m->name }}</h3>
                            <p class="text-sm text-texto-suave">{{ trans_choice('{1} 1 crédito|[2,*] :count créditos', $m->credits) }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</div>
