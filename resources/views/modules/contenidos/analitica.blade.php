{{-- Analítica de la sección (RF13, maqueta 42). --}}
@php
    $campo = $medidaActiva === 'tiempo' ? 'segundos' : 'aperturas';
    $maximo = max(1, (int) (collect($celdas)->max($campo) ?? 1));
    $sinAbrir = $estudiantes->filter(fn ($e) => ! collect($celdas)->contains(fn ($c) => (int) $c->enrollment_id === (int) $e->id))->count();
@endphp
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat :etiqueta="__('Recursos publicados')" :valor="$recursos->count()" icono="book-open" />
        <x-ui.stat :etiqueta="__('Aperturas')" :valor="number_format(collect($celdas)->sum('aperturas'))" icono="eye" />
        <x-ui.stat :etiqueta="__('Tiempo con el recurso a la vista')" :valor="\App\Modules\Contenidos\Livewire\Analitica::duracion((int) collect($celdas)->sum('segundos'))" icono="clock" />
        <x-ui.stat :etiqueta="__('Estudiantes sin abrir nada')" :valor="$sinAbrir" icono="exclamation-triangle" :tono="$sinAbrir ? 'aviso' : 'exito'" />
    </div>

    <x-ui.card :titulo="__('Mapa de calor')" :descripcion="$medidaActiva === 'tiempo'
        ? __('Tiempo con la pestaña visible, por estudiante y recurso. Más oscuro = más tiempo. Pase el cursor para ver también las aperturas.')
        : __('Aperturas por estudiante y recurso. Más oscuro = más aperturas. Pase el cursor para ver también el tiempo.')">
        <x-slot:acciones>
            <div class="inline-flex rounded-lg p-0.5 ring-1 ring-borde" role="group" aria-label="{{ __('Medida del mapa de calor') }}">
                @foreach (['aperturas' => __('Aperturas'), 'tiempo' => __('Tiempo')] as $valor => $etiqueta)
                    <button type="button" wire:click="$set('medida', '{{ $valor }}')" aria-pressed="{{ $medidaActiva === $valor ? 'true' : 'false' }}"
                        @class(['rounded-md px-3 py-1 text-sm font-medium transition', 'bg-marca-600 text-white' => $medidaActiva === $valor, 'text-texto hover:bg-zinc-100' => $medidaActiva !== $valor])>{{ $etiqueta }}</button>
                @endforeach
            </div>
        </x-slot:acciones>
        @if ($recursos->isEmpty() || $estudiantes->isEmpty())
            <x-ui.empty-state icono="chart-bar" :titulo="__('Aún no hay datos')" />
        @else
            <div class="overflow-x-auto">
                <table class="text-xs">
                    <thead><tr><th class="sticky left-0 bg-white p-2 text-left">{{ __('Estudiante') }}</th>
                        @foreach ($recursos as $r)<th class="p-1"><span class="block w-8 truncate" title="{{ $r->unidad }} · {{ $r->title }}">{{ $loop->iteration }}</span></th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($estudiantes as $e)
                            <tr wire:key="fila-{{ $e->id }}">
                                <td class="sticky left-0 whitespace-nowrap bg-white p-2">{{ $e->last_name }}, {{ $e->first_name }}</td>
                                @foreach ($recursos as $r)
                                    @php($c = $celdas[$e->id.'-'.$r->id] ?? null)
                                    <td class="p-0.5"><span class="block size-7 rounded" title="{{ $r->title }}: {{ $c ? __(':a aperturas · :t', ['a' => $c->aperturas, 't' => \App\Modules\Contenidos\Livewire\Analitica::duracion((int) $c->segundos)]) : __('sin abrir') }}"
                                        style="background-color: {{ $c ? 'color-mix(in srgb, #ae152d '.(15 + 85 * (int) $c->{$campo} / $maximo).'%, white)' : '#f4f4f5' }}"></span></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <ol class="mt-4 grid gap-1 text-xs text-texto-suave sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($recursos as $r)<li>{{ $loop->iteration }}. {{ $r->title }}</li>@endforeach
            </ol>
        @endif
    </x-ui.card>
</div>
