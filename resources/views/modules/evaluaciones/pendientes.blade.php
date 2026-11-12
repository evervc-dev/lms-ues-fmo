{{-- Pendientes de calificar (maqueta 18). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Pendientes de calificar')" :subtitulo="trans_choice('{0} No tiene nada pendiente.|{1} 1 entrega o ensayo por calificar.|[2,*] :count entregas o ensayos por calificar.', $pendientes->sum('pendientes'))" />
    <x-ui.card>
        @if ($pendientes->isEmpty())
            <x-ui.empty-state icono="check-circle" :titulo="__('Todo al día')">{{ __('No hay entregas ni ensayos esperando su calificación.') }}</x-ui.empty-state>
        @else
            <ul class="divide-y divide-borde">
                @foreach ($pendientes as $p)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="pend-{{ $p->tipo }}-{{ $p->id }}">
                        <span class="min-w-0">
                            <span class="flex items-center gap-2 font-medium text-texto">{{ $p->title }} @if ($p->is_anonymous)<x-ui.badge>{{ __('Anónima') }}</x-ui.badge>@endif</span>
                            <span class="text-xs text-texto-suave">{{ $p->code }} · {{ $p->grupo }} · {{ $p->tipo === 'tarea' ? __('Tarea') : __('Cuestionario (ensayos)') }} · {{ __('cerró :f', ['f' => \Illuminate\Support\Carbon::parse($p->closes_at)->format('d/m/Y')]) }}</span>
                        </span>
                        <span class="flex items-center gap-3">
                            <x-ui.badge color="marca">{{ $p->pendientes }}</x-ui.badge>
                            <x-ui.button tamano="sm" :href="$p->tipo === 'tarea' ? route('secciones.tareas.entregas', ['seccion' => $p->seccion, 'tarea' => $p->ulid]) : route('secciones.cuestionarios.show', ['seccion' => $p->seccion, 'cuestionario' => $p->ulid])">{{ __('Calificar') }}</x-ui.button>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    @if ($revisiones->isNotEmpty())
        <x-ui.card :titulo="__('Solicitudes de revisión')">
            <ul class="divide-y divide-borde">
                @foreach ($revisiones as $r)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="rev-{{ $r->seccion }}">
                        <span class="text-sm"><span class="font-medium">{{ $r->code }} · {{ $r->grupo }}</span>
                            <span class="block text-xs text-texto-suave">{{ __('la más antigua del :f', ['f' => \Illuminate\Support\Carbon::parse($r->desde)->timezone('America/El_Salvador')->format('d/m/Y')]) }}</span></span>
                        <span class="flex items-center gap-3">
                            <x-ui.badge color="ambar">{{ $r->pendientes }}</x-ui.badge>
                            <x-ui.button tamano="sm" :href="route('secciones.revisiones', ['seccion' => $r->seccion])">{{ __('Revisar') }}</x-ui.button>
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</div>
