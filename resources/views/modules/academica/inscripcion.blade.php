{{-- Inscripción de materias (RF29, ADR-050): materias de la carrera del estudiante para el ciclo en curso,
     buscables y agrupadas por el ciclo del plan. --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Inscripción de materias')"
        :subtitulo="__('Ciclo :ciclo · materias de su carrera para este ciclo.', ['ciclo' => $ciclo === 2 ? 'II' : 'I'])" />

    @if ($plazo['vencido'])
        <x-ui.alert tipo="aviso">{{ __('El plazo de inscripción terminó el :fecha. Si necesita inscribirse, pida una prórroga a su coordinador.', ['fecha' => $plazo['fecha']->format('d/m/Y')]) }}</x-ui.alert>
    @else
        <x-ui.alert tipo="info">{{ __('Inscripción abierta hasta el :fecha.', ['fecha' => $plazo['fecha']->format('d/m/Y')]) }}</x-ui.alert>
    @endif

    <x-ui.search wire:model.live.debounce.300ms="buscar" :placeholder="__('Buscar materia por código o nombre…')" />

    @if ($total === 0)
        <x-ui.card>
            @if ($buscando)
                <x-ui.empty-state icono="magnifying-glass" :titulo="__('Ninguna materia coincide con su búsqueda')">
                    {{ __('Pruebe con el código de la materia, por ejemplo PRN115, o con una palabra de su nombre.') }}
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
                    {{ __('Ciclo :n', ['n' => $cicloDelPlan]) }}
                    <span class="text-xs font-normal normal-case tracking-normal">{{ trans_choice('{1} 1 materia|[2,*] :count materias', $materias->count()) }}</span>
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($materias as $m)
                        <a wire:key="inscribible-{{ $m->id }}" href="{{ route('inscripcion.show', ['asignatura' => $m->id]) }}" wire:navigate
                            class="flex flex-col gap-3 rounded-xl bg-white p-4 ring-1 ring-borde transition hover:ring-marca-300">
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
