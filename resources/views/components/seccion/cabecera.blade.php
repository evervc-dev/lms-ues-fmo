{{--
    Cabecera de la Vista de sección (docs/13 Layout/Vista de sección.md, maqueta 06).
    Recibe el resumen de App\Modules\Academica\Services\SeccionService::resumen(). Slot «acciones» para los botones del rol.
--}}
@props(['seccion'])

<section {{ $attributes->class(['shrink-0 rounded-xl bg-white ring-1 ring-borde']) }}>
    {{-- En el teléfono es compacta: ocupaba casi media pantalla en cada pestaña antes del contenido (revisión UX del
         22/11/2026). El período y el plan se ven desde 640 px; el docente y el horario van en «Docente y horario». --}}
    <div class="flex flex-wrap items-start justify-between gap-3 p-3 sm:gap-4 sm:p-5">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.badge color="marca">{{ $seccion['codigo'] }}</x-ui.badge>
                <x-ui.badge>{{ __('Grupo :grupo', ['grupo' => $seccion['grupo']]) }}</x-ui.badge>
                <x-ui.badge class="max-sm:hidden" :color="$seccion['periodoCerrado'] ? 'gris' : 'verde'">{{ $seccion['periodo'] }}</x-ui.badge>
                @if ($seccion['plan'] ?? null)
                    <x-ui.badge class="max-sm:hidden" color="azul" :title="__('Plan de estudios')">{{ $seccion['plan'] }}</x-ui.badge>
                @endif
            </div>
            <h2 class="mt-1.5 text-lg font-semibold text-texto sm:mt-2 sm:text-xl">{{ $seccion['asignatura'] }}</h2>
            @if ($seccion['docentes'] || $seccion['horario'])
                <details class="mt-1 sm:hidden">
                    <summary class="cursor-pointer text-sm text-texto-suave">{{ __('Docente y horario') }}</summary>
                    <dl class="mt-1 flex flex-col gap-1 text-sm text-texto-suave">
                        @if ($seccion['docentes'])<div><dt class="sr-only">{{ __('Docentes') }}</dt><dd>{{ implode(', ', $seccion['docentes']) }}</dd></div>@endif
                        @if ($seccion['horario'])<div><dt class="sr-only">{{ __('Horario') }}</dt><dd>{{ implode(' · ', $seccion['horario']) }}</dd></div>@endif
                    </dl>
                </details>
            @endif
            <dl class="mt-2 hidden flex-wrap gap-x-5 gap-y-1 text-sm text-texto-suave sm:flex">
                @if ($seccion['docentes'])
                    <div class="flex items-center gap-1.5"><dt class="sr-only">{{ __('Docentes') }}</dt><x-ui.icon name="academic-cap" class="size-4" /><dd>{{ implode(', ', $seccion['docentes']) }}</dd></div>
                @endif
                @if ($seccion['horario'])
                    <div class="flex items-center gap-1.5"><dt class="sr-only">{{ __('Horario') }}</dt><x-ui.icon name="clock" class="size-4" /><dd class="flex flex-wrap gap-x-1">@foreach ($seccion['horario'] as $franja)<span class="whitespace-nowrap">{{ $franja }}@if (! $loop->last) ·@endif</span>@endforeach</dd></div>
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
