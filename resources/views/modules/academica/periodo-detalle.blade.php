{{-- Detalle del período (RF06, RF34). --}}
@php
    $orden = array_keys($etapas);
    $actual = array_search($periodo->preparation_stage, $orden, true);
@endphp
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Período :nombre', ['nombre' => $periodo->name])"
        :subtitulo="\Illuminate\Support\Carbon::parse($periodo->start_date)->format('d/m/Y').' – '.\Illuminate\Support\Carbon::parse($periodo->end_date)->format('d/m/Y')">
        <div class="mt-2"><x-ui.badge :color="['active' => 'verde', 'planned' => 'azul', 'closed' => 'gris'][$periodo->status]">{{ __($estados[$periodo->status]) }}</x-ui.badge></div>
        <x-slot:acciones>
            <x-ui.exportar />
            @if ($periodo->status === 'planned' && $periodo->preparation_stage !== 'ready')
                <x-ui.button variante="secundario" wire:click="avanzar" wire:confirm="{{ __('¿Pasar el período a :etapa? La etapa no se devuelve desde aquí.', ['etapa' => __($etapas[$orden[$actual + 1]])]) }}">{{ __('Pasar a :etapa', ['etapa' => __($etapas[$orden[$actual + 1]])]) }}</x-ui.button>
            @endif
            @if ($periodo->status === 'planned' && $periodo->preparation_stage === 'ready')
                <x-ui.button wire:click="activar" wire:confirm="{{ __('El período quedará en curso. ¿Continuar?') }}">{{ __('Poner en curso') }}</x-ui.button>
            @endif
            @if ($periodo->status === 'active')
                <x-ui.button wire:click="cerrar" wire:confirm="{{ __('Se congelarán las notas finales y el período quedará cerrado. ¿Continuar?') }}">{{ __('Cerrar período') }}</x-ui.button>
            @endif
            @if ($periodo->status === 'closed')
                <x-ui.button variante="secundario" x-on:click="$dispatch('abrir-modal', 'reabrir-periodo')">{{ __('Reabrir período') }}</x-ui.button>
            @endif
        </x-slot:acciones>
    </x-ui.encabezado>

    <div class="grid gap-3 sm:grid-cols-3">
        <x-ui.stat :etiqueta="__('Secciones')" :valor="$secciones" icono="building-office" />
        <x-ui.stat :etiqueta="__('Matrículas activas')" :valor="number_format($matriculas)" icono="users" />
        <x-ui.stat :etiqueta="__('Cierres autorizados')" :valor="$cierres->where('secciones', '>', 0)->whereNotNull('authorized_at')->count().' / '.$cierres->where('secciones', '>', 0)->count()" icono="lock-closed" :tono="$todosAutorizados ? 'exito' : 'aviso'" />
    </div>

    @if ($periodo->status === 'planned')
        <x-ui.card :titulo="__('Preparación')">
            <ol class="grid gap-3 sm:grid-cols-4">
                @foreach ($etapas as $clave => $etiqueta)
                    @php($i = array_search($clave, $orden, true))
                    <li @class(['rounded-lg p-3 ring-1', 'bg-emerald-50 ring-emerald-200' => $i < $actual, 'bg-marca-50 ring-marca-200' => $i === $actual, 'ring-borde' => $i > $actual])>
                        <p class="text-xs font-semibold uppercase text-texto-suave">{{ __('Etapa :n', ['n' => $i + 1]) }}</p>
                        <p class="font-medium text-texto">{{ __($etiqueta) }}</p>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        <x-ui.card :titulo="__('Cómputos')" :descripcion="__('Pesos provisionales hasta el reglamento académico.')">
            <x-ui.table>
                <x-slot:cabecera><th>{{ __('Cómputo') }}</th><th>{{ __('Fechas') }}</th><th class="text-right">{{ __('Peso') }}</th></x-slot:cabecera>
                @foreach ($computos as $c)
                    <tr><td>{{ $c->name }}</td><td>{{ \Illuminate\Support\Carbon::parse($c->starts_on)->format('d/m') }} – {{ \Illuminate\Support\Carbon::parse($c->ends_on)->format('d/m/Y') }}</td><td class="text-right">{{ rtrim(rtrim(number_format((float) $c->weight_percent, 2, '.', ''), '0'), '.') }} %</td></tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>

        <x-ui.card :titulo="__('Cierres departamentales')" :descripcion="__('El período solo se cierra cuando todos los departamentos autorizaron (RF34).')">
            <ul class="divide-y divide-borde text-sm">
                @foreach ($cierres as $c)
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <span><span class="font-medium">{{ $c->code }}</span> · {{ $c->name }}</span>
                        @if ($c->secciones === 0)
                            <x-ui.badge color="gris">{{ __('Sin secciones') }}</x-ui.badge>
                        @elseif ($c->authorized_at)
                            <x-ui.badge color="verde">{{ __('Autorizado por :quien', ['quien' => $c->autorizo]) }}</x-ui.badge>
                        @else
                            <x-ui.badge color="ambar">{{ __('Pendiente') }}</x-ui.badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    </div>

    {{-- El calendario de evaluaciones vive en su propia página (ADR-068): también lo gestionan el Jefe y el Coordinador. --}}
    <x-ui.card :titulo="__('Calendario de evaluaciones')"
        :descripcion="__('Las fechas de todo el período, de cada departamento y de cada carrera, y su sincronización con Google Calendar.')">
        <x-slot:acciones>
            <x-ui.button variante="secundario" icono="calendar" :href="route('periodos.calendario', ['periodo' => $periodo->id])">{{ __('Abrir el calendario') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.card>

    <x-ui.modal nombre="reabrir-periodo" :titulo="__('Reabrir período')">
        <form wire:submit="reabrir" id="form-reabrir" class="flex flex-col gap-4">
            <x-ui.field :etiqueta="__('Motivo de la reapertura')" para="motivoReapertura" error="motivo" requerido><x-ui.textarea id="motivoReapertura" wire:model="motivo" /></x-ui.field>
            <p class="text-xs text-texto-suave">{{ __('El período volverá a estar en curso y se avisará a los Coordinadores. Las autorizaciones de cierre se conservan; al cerrarlo de nuevo se recalculan las notas finales.') }}</p>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'reabrir-periodo')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-reabrir">{{ __('Reabrir período') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
