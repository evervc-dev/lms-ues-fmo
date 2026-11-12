{{-- Informe de un lote de importación (RF04). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Lote: :a', ['a' => $lote->file_name])" :subtitulo="__('Subido por :q el :f', ['q' => $lote->subio, 'f' => \Illuminate\Support\Carbon::parse($lote->created_at)->format('d/m/Y H:i')])" />
    <div class="grid gap-3 sm:grid-cols-3">
        <x-ui.stat :etiqueta="__('Filas')" :valor="$lote->total_rows" icono="table-cells" />
        <x-ui.stat :etiqueta="__('Cuentas creadas')" :valor="$lote->valid_rows" icono="check-circle" tono="exito" />
        <x-ui.stat :etiqueta="__('Filas con error')" :valor="$lote->error_rows" icono="exclamation-triangle" :tono="$lote->error_rows ? 'error' : 'exito'" />
    </div>
    <x-ui.card ajustar :titulo="__('Filas rechazadas')">
        <x-ui.table ajustar :vacio="__('Ninguna fila tuvo errores.')">
            <x-slot:cabecera><th>{{ __('Fila') }}</th><th>{{ __('Campo') }}</th><th>{{ __('Motivo') }}</th><th>{{ __('Dato') }}</th></x-slot:cabecera>
            @foreach ($errores as $e)
                @php($dato = json_decode((string) $e->raw_data, true) ?: [])
                <tr><td>{{ $e->row_number }}</td><td>{{ $e->field }}</td><td>{{ $e->message }}</td><td class="font-mono text-xs">{{ $dato[$e->field] ?? '' }}</td></tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>
</div>
