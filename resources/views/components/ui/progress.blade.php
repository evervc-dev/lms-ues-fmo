{{-- Barra de avance de 6 px (guía §7). <x-ui.progress :valor="72" etiqueta="Avance de PRN115" :mostrar="true" tono="marca|exito|aviso|error" /> --}}
@props(['valor' => 0, 'etiqueta' => null, 'mostrar' => true, 'tono' => 'marca'])

@php
    $valor = max(0, min(100, (float) $valor));
    $relleno = match ($tono) { 'exito' => 'bg-emerald-600', 'aviso' => 'bg-amber-500', 'error' => 'bg-red-700', default => 'bg-marca-600' };
@endphp

<div {{ $attributes->class(['flex items-center gap-3']) }}>
    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-200" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $valor }}" @if ($etiqueta) aria-label="{{ $etiqueta }}" @endif>
        <div class="h-full rounded-full {{ $relleno }}" style="width: {{ $valor }}%"></div>
    </div>
    @if ($mostrar)
        <span class="w-10 text-right text-xs font-medium text-texto-suave">{{ rtrim(rtrim(number_format($valor, 1, '.', ''), '0'), '.') }} %</span>
    @endif
</div>
