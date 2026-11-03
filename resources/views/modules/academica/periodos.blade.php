{{-- Períodos (RF06). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Períodos')" :subtitulo="__('Solo puede haber un período en curso. Los cerrados quedan como histórico.')">
        <x-slot:acciones>
            <x-ui.button icono="plus" x-on:click="$dispatch('abrir-modal', 'nuevo-periodo')">{{ __('Nuevo período') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.encabezado>

    <x-ui.table ajustar :vacio="__('Aún no hay períodos.')">
        <x-slot:cabecera><th>{{ __('Período') }}</th><th>{{ __('Fechas') }}</th><th>{{ __('Estado') }}</th><th>{{ __('Etapa') }}</th><th class="text-right">{{ __('Secciones') }}</th><th class="text-right">{{ __('Matrículas') }}</th><th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
        @foreach ($periodos as $p)
            <tr wire:key="periodo-{{ $p->id }}">
                <td><a href="{{ route('periodos.show', $p->id) }}" wire:navigate class="font-semibold text-texto hover:text-marca-700 hover:underline">{{ $p->name }}</a></td>
                <td>{{ \Illuminate\Support\Carbon::parse($p->start_date)->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($p->end_date)->format('d/m/Y') }}</td>
                <td><x-ui.badge :color="['active' => 'verde', 'planned' => 'azul', 'closed' => 'gris'][$p->status]">{{ __($estados[$p->status]) }}</x-ui.badge></td>
                <td>{{ $p->status === 'planned' ? __($etapas[$p->preparation_stage]) : '—' }}</td>
                <td class="text-right">{{ $p->secciones }}</td>
                <td class="text-right">{{ number_format($p->matriculas) }}</td>
                {{-- P-01 (QA 27/10): la acción visible; antes solo el nombre llevaba al detalle. --}}
                <td class="text-right"><x-ui.button tamano="sm" variante="secundario" :href="route('periodos.show', $p->id)">{{ $p->status === 'closed' ? __('Ver') : __('Gestionar') }}</x-ui.button></td>
            </tr>
        @endforeach
    </x-ui.table>

    <x-ui.modal nombre="nuevo-periodo" :titulo="__('Nuevo período')">
        <form wire:submit="crear" id="form-periodo" class="flex flex-col gap-4">
            <x-ui.field :etiqueta="__('Nombre')" para="nombre" error="nombre" :ayuda="__('Ej.: 2027-I')" requerido><x-ui.input id="nombre" wire:model="nombre" /></x-ui.field>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field :etiqueta="__('Inicio')" para="inicio" error="inicio" requerido><x-ui.input id="inicio" type="date" wire:model="inicio" /></x-ui.field>
                <x-ui.field :etiqueta="__('Fin')" para="fin" error="fin" requerido><x-ui.input id="fin" type="date" wire:model="fin" /></x-ui.field>
            </div>
            <p class="text-xs text-texto-suave">{{ __('Se crea en planificación con tres cómputos de 30 %, 30 % y 40 % (provisionales hasta el reglamento académico).') }}</p>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'nuevo-periodo')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-periodo">{{ __('Crear período') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
