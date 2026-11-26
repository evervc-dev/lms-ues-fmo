{{--
    «Qué sigue» del Inicio (RF24, QA 24/11/2026): de 3 a 5 pendientes ordenados por fecha, cada uno con el botón
    que lo resuelve. Variables: $pendientes (QueSigueService) y $fecha.
--}}
<x-ui.card class="mt-4" :titulo="__('Qué sigue')" :descripcion="__('Lo que espera su acción, de lo más urgente a lo menos.')">
    @forelse ($pendientes as $p)
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-borde py-2.5 last:border-0">
            <div class="flex min-w-0 items-start gap-3">
                <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-red-500' => $p['tono'] === 'error', 'bg-amber-500' => $p['tono'] === 'aviso', 'bg-marca-500' => ! in_array($p['tono'], ['error', 'aviso'], true)])></span>
                <div class="min-w-0 text-sm">
                    <p class="font-medium text-texto">{{ $p['titulo'] }}</p>
                    <p class="text-xs text-texto-suave">{{ $p['detalle'] }}@if ($p['fecha']) · {{ $fecha($p['fecha'])->isToday() ? __('hoy :h', ['h' => $fecha($p['fecha'])->format('H:i')]) : $fecha($p['fecha'])->translatedFormat('j M') }}@endif</p>
                </div>
            </div>
            <x-ui.button tamano="sm" :variante="$p['tono'] === 'error' ? 'primario' : 'secundario'" :href="$p['url']" :navegar="! $p['externo']">{{ $p['accion'] }}</x-ui.button>
        </div>
    @empty
        <p class="flex items-center gap-2 text-sm text-emerald-700"><x-ui.icon name="check-circle" class="size-4" />{{ __('Está al día: no hay nada pendiente.') }}</p>
    @endforelse
</x-ui.card>
