{{--
    «Próximas evaluaciones» del Inicio (ADR-094): las fechas del calendario de evaluaciones que le aplican al docente o
    al estudiante y que aún no pasan, con el enlace al calendario completo. Variables: $evaluaciones y $fecha.
--}}
<x-ui.card :titulo="__('Próximas evaluaciones')">
    @if (auth()->user()->checkPermissionTo('periodos.calendario.ver') || auth()->user()->checkPermissionTo('periodos.calendario.gestionar'))
        <x-slot:acciones><x-ui.button tamano="sm" variante="fantasma" :href="route('periodos.calendario')">{{ __('Ver calendario') }}</x-ui.button></x-slot:acciones>
    @endif
    @forelse ($evaluaciones as $e)
        <p class="flex items-center justify-between gap-2 border-b border-borde py-1.5 text-sm last:border-0">
            <span class="min-w-0">{{ $e->title }} <span class="block text-xs text-texto-suave">{{ $e->ambito }}</span></span>
            <span class="shrink-0 text-texto-suave">{{ $fecha($e->starts_at)->translatedFormat($e->ends_at ? 'D j M, H:i' : 'D j M') }}</span>
        </p>
    @empty
        <p class="text-sm text-texto-suave">{{ __('No hay evaluaciones próximas en el calendario.') }}</p>
    @endforelse
</x-ui.card>
