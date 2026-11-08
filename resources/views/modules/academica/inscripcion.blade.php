{{-- Inscripción de materias (RF29, ADR-050): materias de la carrera del estudiante para el ciclo en curso. --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Inscripción de materias')"
        :subtitulo="__('Ciclo :ciclo · materias de su carrera para este ciclo.', ['ciclo' => $ciclo === 2 ? 'II' : 'I'])" />

    @if ($plazo['vencido'])
        <x-ui.alert tipo="aviso">{{ __('El plazo de inscripción terminó el :fecha. Si necesita inscribirse, pida una prórroga a su coordinador.', ['fecha' => $plazo['fecha']->format('d/m/Y')]) }}</x-ui.alert>
    @else
        <x-ui.alert tipo="info">{{ __('Inscripción abierta hasta el :fecha.', ['fecha' => $plazo['fecha']->format('d/m/Y')]) }}</x-ui.alert>
    @endif

    @if ($materias->isEmpty())
        <x-ui.card><x-ui.empty-state icono="book-open" :titulo="__('No hay materias para inscribir')">{{ __('Su carrera no tiene materias de este ciclo o no hay un período en curso. Si cree que es un error, consulte a su coordinador.') }}</x-ui.empty-state></x-ui.card>
    @else
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
                    <h2 class="font-semibold text-texto">{{ $m->name }}</h2>
                    <p class="text-sm text-texto-suave">{{ __('Ciclo :n del plan', ['n' => $m->cycle]) }} · {{ trans_choice('{1} 1 crédito|[2,*] :count créditos', $m->credits) }}</p>
                </a>
            @endforeach
        </div>
    @endif
</div>
