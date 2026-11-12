{{-- Reportes (RF25, maqueta 47). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Reportes')" :subtitulo="__('Datos de su alcance por período y carrera. Exporte a Excel, PDF o CSV con los filtros que tenga elegidos.')">
        <x-slot:acciones>
            {{-- R-12: el período en curso o uno cerrado. --}}
            <x-ui.select class="sm:w-auto" wire:model.live="periodo" :aria-label="__('Período')">
                @foreach ($periodos as $p)
                    <option value="{{ $p->status === 'active' ? '' : $p->id }}">{{ $p->name }}{{ $p->status === 'active' ? ' · '.__('en curso') : '' }}</option>
                @endforeach
            </x-ui.select>
            @if ($carreras->isNotEmpty())
                <x-ui.select class="sm:w-auto" wire:model.live="carrera" :aria-label="__('Carrera')">
                    <option value="">{{ __('Todas las carreras de su alcance') }}</option>
                    @foreach ($carreras as $c)<option value="{{ $c->id }}">{{ $c->code }} · {{ $c->name }}</option>@endforeach
                </x-ui.select>
            @endif
            <x-ui.exportar />
        </x-slot:acciones>
    </x-ui.encabezado>

    <x-ui.tabs :pestanas="collect($tipos)->map(fn ($t, $k) => ['etiqueta' => __($t), 'url' => route('reportes', array_filter(['tipo' => $k, 'periodo' => $periodo, 'carrera' => $carrera])), 'activa' => $k === $tipo])->values()->all()" />

    @if ($periodos->isEmpty())
        <x-ui.alert tipo="info">{{ __('No hay un período en curso ni cerrados: los reportes aparecerán cuando empiece el primero.') }}</x-ui.alert>
    @endif

    {{-- R-11: el gráfico resume la medida principal; la tabla de abajo tiene todos los datos. --}}
    @if ($grafico['valores'] !== [])
        <x-ui.card :titulo="$grafico['titulo']" :descripcion="$grafico['recortado'] ? __('Se grafican las primeras :n filas; la tabla y la exportación traen todas.', ['n' => count($grafico['valores'])]) : null">
            <div wire:key="grafico-{{ $tipo }}-{{ $periodo }}-{{ $carrera }}" x-data="graficoReporte(@js($grafico))"
                style="height: {{ 48 + 28 * count($grafico['valores']) }}px" role="img"
                aria-label="{{ $grafico['titulo'] }}. {{ __('Los mismos datos están en la tabla de abajo.') }}">
                <canvas x-ref="lienzo"></canvas>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card :titulo="__($tipos[$tipo])" :descripcion="trans_choice('{1} 1 fila|[0,*] :count filas', $reporte['filas']->count()).' · '.__('Período :p', ['p' => $reporte['periodo']?->name ?? '—'])">
        <x-ui.table :vacio="__('No hay datos para este reporte.')">
            <x-slot:cabecera>@foreach ($reporte['columnas'] as $c)<th>{{ $c }}</th>@endforeach</x-slot:cabecera>
            @foreach ($reporte['filas']->take(200) as $fila)
                <tr>@foreach ($fila as $valor)<td>{{ $valor ?? '—' }}</td>@endforeach</tr>
            @endforeach
        </x-ui.table>
        @if ($reporte['filas']->count() > 200)<p class="mt-2 text-xs text-texto-suave">{{ __('Se muestran 200 filas; la exportación incluye todas.') }}</p>@endif
    </x-ui.card>

    @if ($programa)
        <x-ui.card :titulo="__('Envío programado por correo')" :descripcion="__('Cada envío trae el período en curso y todas las carreras de su alcance, sin los filtros de arriba.')">
            <form wire:submit="programar" class="mb-4 flex flex-wrap items-end gap-3">
                <x-ui.field :etiqueta="__('Frecuencia')" para="frecuencia"><x-ui.select id="frecuencia" wire:model="frecuencia"><option value="weekly">{{ __('Cada lunes') }}</option><option value="monthly">{{ __('Cada inicio de mes') }}</option></x-ui.select></x-ui.field>
                <x-ui.button type="submit" variante="secundario">{{ __('Programar «:r»', ['r' => __($tipos[$tipo])]) }}</x-ui.button>
            </form>
            <x-ui.table :vacio="__('No tiene reportes programados.')">
                <x-slot:cabecera><th>{{ __('Reporte') }}</th><th>{{ __('Frecuencia') }}</th><th>{{ __('Próximo envío') }}</th><th></th></x-slot:cabecera>
                @foreach ($programados as $p)
                    <tr wire:key="prog-{{ $p->id }}"><td>{{ __($tipos[$p->report_type] ?? $p->report_type) }}</td><td>{{ $p->frequency === 'weekly' ? __('Semanal') : __('Mensual') }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($p->next_run_at)->format('d/m/Y H:i') }}</td>
                        <td class="text-right"><x-ui.button tamano="sm" variante="peligro" wire:click="cancelar({{ $p->id }})" wire:confirm="{{ __('¿Cancelar este envío programado? Dejará de llegarle por correo.') }}">{{ __('Cancelar') }}</x-ui.button></td></tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
