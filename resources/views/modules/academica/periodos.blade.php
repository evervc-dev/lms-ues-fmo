{{-- Períodos (RF06). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Períodos')" :subtitulo="__('Solo puede haber un período en curso. Los cerrados quedan como histórico.')">
        <x-slot:acciones>
            <x-ui.button icono="plus" x-on:click="$dispatch('abrir-modal', 'nuevo-periodo')">{{ __('Nuevo período') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.encabezado>

    <x-ui.table ajustar :vacio="__('Aún no hay períodos.')">
        <x-slot:cabecera><th>{{ __('Período') }}</th><th>{{ __('Fechas') }}</th><th>{{ __('Estado') }}</th><th>{{ __('Etapa') }}</th><th class="text-right">{{ __('Grupos') }}</th><th class="text-right">{{ __('Matrículas') }}</th><th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
        @foreach ($periodos as $p)
            <tr wire:key="periodo-{{ $p->id }}">
                <td><a href="{{ route('periodos.show', $p->id) }}" wire:navigate class="font-semibold text-texto hover:text-marca-700 hover:underline">{{ $p->name }}</a></td>
                <td>{{ \Illuminate\Support\Carbon::parse($p->start_date)->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($p->end_date)->format('d/m/Y') }}</td>
                <td><x-ui.badge :color="['active' => 'verde', 'planned' => 'azul', 'closed' => 'gris'][$p->status]">{{ __($estados[$p->status]) }}</x-ui.badge></td>
                {{-- QA admin 21/11: el período en curso mostraba «—»; ahora su etapa real y, el cerrado, «Concluido». --}}
                <td>{{ $p->status === 'closed' ? __('Concluido') : __($etapas[$p->preparation_stage] ?? '—') }}</td>
                @if ($p->status === 'closed' && (int) $p->secciones === 0)
                    {{-- Un período cerrado sin grupos no tiene historial cargado (p. ej., la siembra del equipo): no son ceros reales. --}}
                    <td colspan="2" class="text-right text-xs text-texto-suave" title="{{ __('El período se cerró sin grupos teóricos registrados en el sistema.') }}">{{ __('Sin historial en el sistema') }}</td>
                @else
                    <td class="text-right">{{ $p->secciones }}</td>
                    <td class="text-right">{{ number_format($p->matriculas) }}</td>
                @endif
                {{-- P-01 (QA 27/10): la acción visible; antes solo el nombre llevaba al detalle. --}}
                <td class="text-right"><x-ui.button tamano="sm" variante="secundario" :href="route('periodos.show', $p->id)">{{ $p->status === 'closed' ? __('Ver') : __('Gestionar') }}</x-ui.button></td>
            </tr>
        @endforeach
    </x-ui.table>

    <x-ui.modal nombre="nuevo-periodo" :titulo="__('Nuevo período')">
        {{-- La duración se calcula mientras se eligen las fechas; los cómputos se muestran como quedarán. --}}
        <form wire:submit="crear" id="form-periodo" class="flex flex-col gap-4"
            x-data="{ ini: $wire.inicio, fin: $wire.fin, get semanas() {
                const a = new Date(this.ini), b = new Date(this.fin);
                if (isNaN(a) || isNaN(b)) return null;
                const dias = Math.round((b - a) / 86400000);
                return dias > 0 ? Math.round(dias / 7) : 0;
            } }">
            <x-ui.field :etiqueta="__('Nombre')" para="nombre" error="nombre" :ayuda="__('Año y ciclo, como 2027-I o 2027-II.')" requerido><x-ui.input id="nombre" wire:model="nombre" placeholder="2027-I" /></x-ui.field>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field :etiqueta="__('Inicio')" para="inicio" error="inicio" requerido><x-ui.input id="inicio" type="date" wire:model="inicio" x-on:input="ini = $event.target.value" /></x-ui.field>
                <x-ui.field :etiqueta="__('Fin')" para="fin" error="fin" requerido><x-ui.input id="fin" type="date" wire:model="fin" x-on:input="fin = $event.target.value" /></x-ui.field>
            </div>
            <p class="-mt-2 text-xs" x-show="semanas !== null" :class="semanas > 0 ? 'text-texto-suave' : 'text-red-700'"
                x-text="semanas > 0 ? @js(__('Dura unas ')) + semanas + @js(__(' semanas.')) : @js(__('El fin debe ser después del inicio.'))"></p>
            <div class="rounded-lg bg-zinc-50 p-3 ring-1 ring-borde">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Cómputos que se crean') }}</p>
                {{-- El ancho de cada tramo es lo que dura dentro del período; todos pesan 25 % (ADR-085). --}}
                <div class="flex overflow-hidden rounded-md text-center text-xs font-medium text-white">
                    @foreach (\App\Modules\Academica\Services\PeriodoService::COMPUTOS as $numero => [$peso, $parte])
                        <span @class(['py-1.5', 'bg-marca-600' => $numero % 2 === 1, 'bg-marca-800' => $numero % 2 === 0]) style="width: {{ $parte }}%">{{ __('C:n · :p %', ['n' => $numero, 'p' => $peso]) }}</span>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-texto-suave">{{ __('Los cuatro pesan igual en la nota. El primero dura más (30 % del período), el segundo y el tercero lo mismo y el cuarto es el más corto.') }}</p>
                <p class="mt-2 text-xs text-texto-suave">{{ __('Se crea en planificación. Los porcentajes son provisionales hasta el reglamento académico.') }}</p>
            </div>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'nuevo-periodo')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-periodo">{{ __('Crear período') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
