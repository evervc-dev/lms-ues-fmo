{{-- Mi plan (RF37): el plan de estudios por ciclo con lo aprobado (amarillo, con su nota), lo que cursa (azul, con su
     nota actual), lo reprobado (rojo, con la última nota) y lo pendiente. Al tocar una materia se marcan sus
     prerrequisitos (borde rojo) y las materias que desbloquea (borde azul), como en la oferta académica de la UES. --}}
@php
    $fmt = fn ($n) => number_format((float) $n, 1);
    $estilos = [
        'aprobada' => ['tarjeta' => 'bg-amber-50 ring-amber-300', 'cabeza' => 'bg-amber-100 text-amber-900', 'etiqueta' => __('Aprobada')],
        'en_curso' => ['tarjeta' => 'bg-sky-50 ring-sky-300', 'cabeza' => 'bg-sky-100 text-sky-900', 'etiqueta' => __('En curso')],
        'reprobada' => ['tarjeta' => 'bg-red-50 ring-red-300', 'cabeza' => 'bg-red-100 text-red-900', 'etiqueta' => __('Reprobada')],
        'pendiente' => ['tarjeta' => 'bg-white ring-borde', 'cabeza' => 'bg-zinc-100 text-texto-suave', 'etiqueta' => __('Pendiente')],
    ];
@endphp
<div class="flex flex-col gap-5" x-data="{ sel: null, pre: [], dep: [], elegir(id, pre, dep) { if (this.sel === id) { this.sel = null; this.pre = []; this.dep = []; return; } this.sel = id; this.pre = pre; this.dep = dep; } }">
    <x-ui.encabezado :titulo="__('Mi plan')"
        :subtitulo="$plan ? __('Plan de estudios por ciclo: lo aprobado, lo que cursa y lo que le falta. Toque una materia para ver sus prerrequisitos.') : __('Todavía no tiene una carrera activa asignada.')">
        @if ($planes->count() > 1)
            <x-slot:acciones>
                <x-ui.select wire:model.live="planId" :aria-label="__('Plan de estudios')">
                    @foreach ($planes as $p)
                        <option value="{{ $p->propio ? '' : $p->id }}">{{ $p->name }}{{ $p->propio ? ' · '.__('su plan') : '' }}</option>
                    @endforeach
                </x-ui.select>
            </x-slot:acciones>
        @endif
    </x-ui.encabezado>

    @if (! $plan)
        <x-ui.card><x-ui.empty-state icono="table-cells" :titulo="__('Sin plan de estudios')">{{ __('Cuando su coordinación le asigne una carrera y un plan, aquí verá su avance.') }}</x-ui.empty-state></x-ui.card>
    @else
        @if (! $plan->propio)
            <x-ui.alert tipo="info">{{ __('Está viendo el :p, que no es su plan. Lo aprobado cuenta igual si es la misma asignatura.', ['p' => $plan->name]) }}</x-ui.alert>
        @endif

        {{-- Resumen y leyenda en una franja compacta: con cuatro tarjetas de indicadores la malla quedaba debajo del
             primer pantallazo (pedido del usuario del 22/11/2026). --}}
        <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 rounded-xl bg-white px-4 py-2.5 ring-1 ring-borde">
            <dl class="flex flex-wrap items-baseline gap-x-5 gap-y-1 text-sm">
                <div class="flex items-baseline gap-1.5"><dt class="text-texto-suave">{{ __('Aprobadas') }}</dt><dd class="font-semibold tabular-nums text-texto">{{ $resumen['aprobadas'] }}/{{ $resumen['total'] }}</dd></div>
                <div class="flex items-baseline gap-1.5"><dt class="text-texto-suave">{{ __('UV') }}</dt><dd class="font-semibold tabular-nums text-texto">{{ $resumen['uvAprobadas'] }}/{{ $resumen['uvTotal'] }}</dd></div>
                <div class="flex items-baseline gap-1.5" title="{{ __('Promedio de lo aprobado ponderado por UV') }}"><dt class="text-texto-suave">{{ __('CUM') }}</dt><dd class="font-semibold tabular-nums text-texto">{{ $resumen['cum'] === null ? '—' : $fmt($resumen['cum']) }}</dd></div>
                <div class="flex items-baseline gap-1.5"><dt class="text-texto-suave">{{ __('En curso') }}</dt><dd class="font-semibold tabular-nums text-texto">{{ $resumen['enCurso'] }}</dd></div>
            </dl>
            {{-- Leyenda: el color dice el estado; el borde, la relación con la materia elegida. --}}
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-texto-suave">
                @foreach ($estilos as $clave => $e)
                    <span class="inline-flex items-center gap-1"><span @class(['size-2.5 rounded-sm ring-1', $e['tarjeta']])></span>{{ $e['etiqueta'] }}</span>
                @endforeach
                <span class="inline-flex items-center gap-1"><span class="size-2.5 rounded-sm outline outline-2 outline-red-500"></span>{{ __('Prerrequisito') }}</span>
                <span class="inline-flex items-center gap-1"><span class="size-2.5 rounded-sm outline outline-2 outline-blue-500"></span>{{ __('Es prerrequisito de') }}</span>
            </div>
        </div>

        {{-- Escritorio: una columna por ciclo, con desplazamiento a los lados si no caben. Teléfono: un ciclo debajo de otro. --}}
        {{-- El relleno deja ver completos el borde de la tarjeta elegida y los de sus prerrequisitos: el contenedor con
             desplazamiento lateral recortaba el contorno de las tarjetas de la orilla (QA 22/11). --}}
        <div class="overflow-x-auto p-1.5 pb-3">
            <div class="flex flex-col gap-5 md:min-w-max md:flex-row md:items-start md:gap-3">
                @foreach ($ciclos as $ciclo => $materias)
                    <section wire:key="ciclo-{{ $ciclo }}" class="flex flex-col gap-2 md:w-44">
                        <h2 class="rounded-lg bg-zinc-700 px-3 py-1.5 text-center text-sm font-semibold text-white">{{ __('Ciclo :n', ['n' => $ciclo]) }}</h2>
                        <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-1">
                            @foreach ($materias as $m)
                                @php($e = $estilos[$m->estado])
                                <button type="button" wire:key="materia-{{ $m->id }}"
                                    x-on:click="elegir({{ $m->id }}, @js($m->prerrequisitos), @js($m->dependientes))"
                                    :class="{
                                        'outline outline-2 outline-offset-2 outline-red-500': pre.includes({{ $m->id }}),
                                        'outline outline-2 outline-offset-2 outline-blue-500': dep.includes({{ $m->id }}),
                                        'outline outline-2 outline-offset-2 outline-zinc-900': sel === {{ $m->id }},
                                    }"
                                    @class(['flex flex-col overflow-hidden rounded-lg text-left ring-1 transition hover:shadow-sm', $e['tarjeta']])
                                    aria-label="{{ $m->code }} {{ $m->name }}: {{ $e['etiqueta'] }}{{ $m->nota !== null ? ' '.$fmt($m->nota) : '' }}">
                                    <span @class(['flex items-center justify-between gap-2 px-2.5 py-1 font-mono text-[11px] font-semibold', $e['cabeza']])>
                                        {{ $m->code }}
                                        <span class="font-sans font-normal">{{ __(':n UV', ['n' => $m->credits]) }}</span>
                                    </span>
                                    <span class="break-words px-2.5 py-2 text-sm leading-snug text-texto">{{ $m->name }}</span>
                                    <span class="mt-auto flex items-baseline justify-between gap-2 border-t border-black/5 px-2.5 py-1.5 text-xs">
                                        <span class="text-texto-suave">{{ $e['etiqueta'] }}</span>
                                        @if ($m->nota !== null)
                                            <span @class(['text-sm font-semibold tabular-nums', 'text-amber-800' => $m->estado === 'aprobada', 'text-sky-800' => $m->estado === 'en_curso', 'text-red-700' => $m->estado === 'reprobada'])>{{ $fmt($m->nota) }}</span>
                                        @elseif ($m->estado === 'en_curso')
                                            <span class="text-texto-suave">{{ __('sin notas aún') }}</span>
                                        @endif
                                    </span>
                                    @if ($m->estado === 'en_curso' && $m->nota !== null && $m->evaluado < 100)
                                        <span class="px-2.5 pb-1.5 text-[11px] text-texto-suave">{{ __(':p % evaluado', ['p' => rtrim(rtrim(number_format($m->evaluado, 1), '0'), '.')]) }}</span>
                                    @endif
                                    @if ($m->codigosPrerrequisitos)
                                        <span class="border-t border-black/5 px-2.5 py-1 text-[11px] text-texto-suave">{{ __('Requiere :c', ['c' => implode(', ', $m->codigosPrerrequisitos)]) }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    @endif
</div>
