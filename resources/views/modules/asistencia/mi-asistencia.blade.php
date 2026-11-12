{{-- Mi asistencia (RF22, maquetas 13 y 14). --}}
@php($niveles = ['bien' => ['exito', 'verde', __('Al día')], 'aviso' => ['aviso', 'ambar', __('Aviso preventivo (< 85 %)')], 'riesgo' => ['aviso', 'ambar', __('Aviso crítico (< 78 %)')], 'critico' => ['error', 'rojo', __('Bajo el mínimo (< 75 %)')], 'sin_datos' => ['neutro', 'gris', __('Sin clases aún')]])
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Mi asistencia')" :subtitulo="__('Mínimo 75 % por cómputo. La tardanza cuenta como media asistencia y las justificadas no cuentan como falta.')" />
    @if ($materias->isEmpty())
        <x-ui.card><x-ui.empty-state icono="clipboard-document-check" :titulo="__('Aún no tiene materias en el período')" /></x-ui.card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($materias as $m)
                <a href="{{ route('secciones.asistencia', ['seccion' => $m->ulid]) }}" wire:navigate class="flex flex-col gap-3 rounded-xl bg-white p-4 ring-1 ring-borde transition hover:ring-marca-300" wire:key="mia-{{ $m->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <div><x-ui.badge color="marca">{{ $m->code }}</x-ui.badge><h2 class="mt-1 font-semibold text-texto">{{ $m->name }}</h2></div>
                        <span class="text-2xl font-semibold">{{ $m->porcentaje !== null ? $m->porcentaje.' %' : '—' }}</span>
                    </div>
                    <x-ui.progress :valor="$m->porcentaje ?? 0" :mostrar="false" :tono="['bien' => 'exito', 'aviso' => 'aviso', 'riesgo' => 'aviso', 'critico' => 'error', 'sin_datos' => 'marca'][$m->nivel]" />
                    <div class="flex items-center justify-between text-xs text-texto-suave">
                        <span>{{ trans_choice('{1} 1 clase|[0,*] :count clases', $m->clases) }} · {{ trans_choice('{1} 1 falta|[0,*] :count faltas', $m->faltas) }}</span>
                        <x-ui.badge :color="$niveles[$m->nivel][1]">{{ $niveles[$m->nivel][2] }}</x-ui.badge>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
