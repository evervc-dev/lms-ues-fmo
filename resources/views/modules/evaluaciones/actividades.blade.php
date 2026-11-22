{{-- Vista de sección › Actividades (RF15, RF16). --}}
@php($estados = ['proxima' => ['azul', __('Próxima')], 'abierta' => ['verde', __('Abierta')], 'gracia' => ['ambar', __('En gracia')], 'cerrada' => ['gris', __('Cerrada')]])
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />
    @if ($gestiona)
        <div class="flex justify-end gap-2">
            <x-ui.button variante="secundario" icono="plus" :href="route('secciones.cuestionarios.crear', ['seccion' => $seccion])">{{ __('Nuevo cuestionario') }}</x-ui.button>
            <x-ui.button icono="plus" :href="route('secciones.tareas.crear', ['seccion' => $seccion])">{{ __('Nueva tarea') }}</x-ui.button>
        </div>
    @endif

    {{-- RF14 (ADR-066): evaluaciones copiadas de un período anterior; el estudiante no las ve hasta programarlas. --}}
    @if ($sinProgramar->isNotEmpty())
        <x-ui.card :titulo="__('Sin programar')" :descripcion="__('Copiadas de un período anterior. Asigne fechas, cómputo y peso: los estudiantes no las ven hasta que las programe.')">
            <ul class="divide-y divide-borde">
                @foreach ($sinProgramar as $a)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="sin-programar-{{ $a->id }}">
                        <span class="flex items-center gap-2 font-medium text-texto">
                            <x-ui.icon :name="$a->assessment_type === 'quiz' ? 'clipboard-document-check' : 'document-chart-bar'" class="size-4 text-marca-600" />{{ $a->title }}
                            <x-ui.badge color="ambar">{{ __('Sin programar') }}</x-ui.badge>
                            @if ($a->rubric_id)<x-ui.badge>{{ __('Con rúbrica') }}</x-ui.badge>@endif
                        </span>
                        <x-ui.button tamano="sm" variante="secundario" icono="calendar-days" wire:click="abrirProgramar('{{ $a->ulid }}')">{{ __('Programar') }}</x-ui.button>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        <x-ui.modal nombre="programar" :titulo="__('Programar evaluación')">
            <form wire:submit="programar" id="form-programar" class="flex flex-col gap-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field :etiqueta="__('Abre')" para="abreP" error="abre" requerido><x-ui.input id="abreP" type="datetime-local" wire:model="abre" /></x-ui.field>
                    <x-ui.field :etiqueta="__('Cierra')" para="cierraP" error="cierra" requerido><x-ui.input id="cierraP" type="datetime-local" wire:model="cierra" /></x-ui.field>
                </div>
                @if ($conGracia)
                    <x-ui.field :etiqueta="__('Fecha límite de gracia')" para="graciaP" error="gracia" requerido><x-ui.input id="graciaP" type="datetime-local" wire:model="gracia" /></x-ui.field>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field :etiqueta="__('Cómputo')" para="componenteP" error="componente" requerido>
                        <x-ui.select id="componenteP" wire:model="componente"><option value="">{{ __('Elija…') }}</option>@foreach ($computos as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</x-ui.select>
                    </x-ui.field>
                    <x-ui.field :etiqueta="__('Peso dentro del cómputo (%)')" para="pesoP" error="peso" requerido><x-ui.input id="pesoP" type="number" step="0.5" wire:model="peso" /></x-ui.field>
                </div>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'programar')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-programar">{{ __('Programar') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif

    @forelse ($computos as $c)
        @php($sumaPonderada = (float) $c->actividades->sum('weight_percent'))
        <x-ui.card :titulo="$c->name.' · '.rtrim(rtrim(number_format((float) $c->weight_percent, 2, '.', ''), '0'), '.').' %'" :descripcion="\Illuminate\Support\Carbon::parse($c->starts_on)->format('d/m').' – '.\Illuminate\Support\Carbon::parse($c->ends_on)->format('d/m/Y')">
            @if ($gestiona && $c->actividades->isNotEmpty())
                <x-slot:acciones>
                    @if (abs($sumaPonderada - 100) < 0.01)
                        <x-ui.badge color="verde" :title="__('Las actividades cubren el 100 % del cómputo')">{{ __('Ponderado: 100 %') }}</x-ui.badge>
                    @elseif ($sumaPonderada > 100)
                        <x-ui.badge color="rojo" :title="__('Pasa del 100 % del cómputo')">{{ __('Ponderado: :n %', ['n' => rtrim(rtrim(number_format($sumaPonderada, 2, '.', ''), '0'), '.')]) }}</x-ui.badge>
                    @else
                        <x-ui.badge color="ambar" :title="__('Falta asignar el :n % del cómputo', ['n' => rtrim(rtrim(number_format(100 - $sumaPonderada, 2, '.', ''), '0'), '.')])">{{ __('Ponderado: :n %', ['n' => rtrim(rtrim(number_format($sumaPonderada, 2, '.', ''), '0'), '.')]) }}</x-ui.badge>
                    @endif
                </x-slot:acciones>
            @endif
            @if ($c->actividades->isEmpty())
                <p class="text-sm text-texto-suave">{{ __('Sin actividades en este cómputo.') }}</p>
            @else
                <ul class="divide-y divide-borde">
                    @foreach ($c->actividades as $a)
                        @php($estado = $servicio->estado($a))
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="act-{{ $a->id }}">
                            <a href="{{ route($a->assessment_type === 'quiz' ? 'secciones.cuestionarios.show' : 'secciones.tareas.show', ['seccion' => $seccion, $a->assessment_type === 'quiz' ? 'cuestionario' : 'tarea' => $a->ulid]) }}" wire:navigate class="min-w-0">
                                <span class="flex items-center gap-2 font-medium text-texto hover:text-marca-700">
                                    <x-ui.icon :name="$a->assessment_type === 'quiz' ? 'clipboard-document-check' : 'document-chart-bar'" class="size-4 text-marca-600" />{{ $a->title }}
                                    @if ($a->is_anonymous)<x-ui.badge>{{ __('Anónima') }}</x-ui.badge>@endif
                                </span>
                                <span class="text-xs text-texto-suave">{{ __('Cierra :f · :p % del cómputo', ['f' => \Illuminate\Support\Carbon::parse($a->closes_at)->timezone('America/El_Salvador')->format('d/m/Y H:i'), 'p' => rtrim(rtrim(number_format((float) $a->weight_percent, 2, '.', ''), '0'), '.')]) }}</span>
                            </a>
                            <span class="flex items-center gap-2">
                                <x-ui.badge :color="$estados[$estado][0]">{{ $estados[$estado][1] }}</x-ui.badge>
                                @isset($a->hecha)
                                    @if ($a->nota !== null)<x-ui.badge color="marca">{{ __('Nota :n', ['n' => number_format((float) $a->nota, 1)]) }}</x-ui.badge>
                                    @elseif ($a->hecha)<x-ui.badge color="verde">{{ __('Entregada') }}</x-ui.badge>
                                    @else<x-ui.badge>{{ __('Pendiente') }}</x-ui.badge>@endif
                                @else
                                    <span class="text-xs text-texto-suave">{{ $a->assessment_type === 'quiz' ? trans_choice('{1} 1 intento|[0,*] :count intentos', $a->intentos) : trans_choice('{1} 1 entrega|[0,*] :count entregas', $a->entregas) }}</span>
                                    @if ($gestiona)
                                        <span class="flex items-center gap-1 sm:ml-2">
                                            @if ($a->assessment_type === 'assignment')
                                                <x-ui.button tamano="sm" variante="fantasma" icono="pencil-square" :href="route('secciones.tareas.editar', ['seccion' => $seccion, 'tarea' => $a->ulid])" :aria-label="__('Editar tarea :t', ['t' => $a->title])" :title="__('Editar tarea')" />
                                                <x-ui.button tamano="sm" variante="secundario" :href="route('secciones.tareas.entregas', ['seccion' => $seccion, 'tarea' => $a->ulid])">{{ __('Entregas') }}</x-ui.button>
                                            @else
                                                <x-ui.button tamano="sm" variante="fantasma" icono="pencil-square" :href="route('secciones.cuestionarios.editar', ['seccion' => $seccion, 'cuestionario' => $a->ulid])" :aria-label="__('Editar cuestionario :t', ['t' => $a->title])" :title="__('Editar cuestionario')" />
                                                <x-ui.button tamano="sm" variante="secundario" :href="route('secciones.cuestionarios.show', ['seccion' => $seccion, 'cuestionario' => $a->ulid])">{{ __('Ver') }}</x-ui.button>
                                            @endif
                                        </span>
                                    @endif
                                @endisset
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    @empty
        {{-- Sin jerga interna para el estudiante: «cómputos» es un término del docente. --}}
        <x-ui.card><x-ui.empty-state icono="clipboard-document-list" :titulo="$gestiona ? __('El grupo teórico no tiene cómputos configurados') : __('Su docente aún no ha publicado actividades.')" /></x-ui.card>
    @endforelse
</div>
