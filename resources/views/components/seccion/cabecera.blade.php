{{--
    Cabecera de la Vista de sección (docs/13 Layout/Vista de sección.md, maqueta 06).
    Recibe el resumen de App\Modules\Academica\Services\SeccionService::resumen(). Slot «acciones» para los botones del rol.
--}}
@props(['seccion'])

<section {{ $attributes->class(['rounded-xl bg-white ring-1 ring-borde']) }}>
    <div class="flex flex-wrap items-start justify-between gap-4 p-4 sm:p-5">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.badge color="marca">{{ $seccion['codigo'] }}</x-ui.badge>
                <x-ui.badge>{{ __('Grupo :grupo', ['grupo' => $seccion['grupo']]) }}</x-ui.badge>
                <x-ui.badge :color="$seccion['periodoCerrado'] ? 'gris' : 'verde'">{{ $seccion['periodo'] }}</x-ui.badge>
            </div>
            <h2 class="mt-2 text-xl font-semibold text-texto">{{ $seccion['asignatura'] }}</h2>
            <dl class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-texto-suave">
                @if ($seccion['docentes'])
                    <div class="flex items-center gap-1.5"><dt class="sr-only">{{ __('Docentes') }}</dt><x-ui.icon name="academic-cap" class="size-4" /><dd>{{ implode(', ', $seccion['docentes']) }}</dd></div>
                @endif
                @if ($seccion['horario'])
                    <div class="flex items-center gap-1.5"><dt class="sr-only">{{ __('Horario') }}</dt><x-ui.icon name="clock" class="size-4" /><dd>{{ implode(' · ', $seccion['horario']) }}</dd></div>
                @endif
            </dl>
        </div>
        @if (isset($acciones) && $acciones->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2">{{ $acciones }}</div>
        @endif
    </div>
    @if ($seccion['periodoCerrado'])
        <p class="border-t border-borde bg-zinc-50 px-4 py-2 text-sm text-texto-suave sm:px-5">{{ __('Período cerrado · solo lectura') }}</p>
    @endif
</section>
