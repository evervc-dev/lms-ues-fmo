{{-- Importar usuarios (RF04). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Importar estudiantes')" :subtitulo="__('Suba un archivo CSV o Excel (.xlsx) con las columnas: :c. Solo correos @ues.edu.sv.', ['c' => implode(', ', $columnas)])" />

    <x-ui.card :titulo="__('1. Plantilla')" :descripcion="__('Empiece por aquí si es su primera importación.')">
        <p class="text-sm text-texto-suave">{{ __('La plantilla trae los encabezados en el orden correcto y una fila de ejemplo con una carrera y un plan que ya existen, para que vea qué se espera en cada columna. Ábrala en Excel, reemplace la fila de ejemplo por sus estudiantes y súbala tal cual: se acepta tanto el CSV como el libro de Excel (.xlsx) que guarde desde ahí.') }}</p>
        <p class="mt-2 text-sm text-texto-suave">{{ __('El carné sale del correo (ll22017@ues.edu.sv → ll22017): la columna carnet solo hace falta cuando el correo no lo lleva.') }}</p>
        <x-ui.button class="mt-4" variante="secundario" icono="arrow-down-tray" wire:click="plantilla">{{ __('Descargar plantilla (CSV)') }}</x-ui.button>
    </x-ui.card>

    <x-ui.card :titulo="__('2. Archivo')">
        <x-ui.field :etiqueta="__('Archivo CSV o Excel (.xlsx)')" para="archivoImport" error="archivo"><input id="archivoImport" type="file" wire:model="archivo" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="text-sm"></x-ui.field>
        <div wire:loading wire:target="archivo" class="mt-2 text-xs text-texto-suave">{{ __('Leyendo el archivo…') }}</div>
    </x-ui.card>

    @if ($previa)
        <x-ui.card :titulo="__('3. Vista previa')">
            <div class="mb-4 grid gap-3 sm:grid-cols-3">
                <x-ui.stat :etiqueta="__('Se crearán')" :valor="$previa['validas']" icono="user-plus" tono="exito" />
                <x-ui.stat :etiqueta="__('Duplicados (se omiten)')" :valor="$previa['duplicados']" icono="users" tono="aviso" />
                <x-ui.stat :etiqueta="__('Filas con error')" :valor="count($previa['errores']) - $previa['duplicados']" icono="exclamation-triangle" tono="error" />
            </div>
            @if ($previa['errores'])
                <x-ui.table>
                    <x-slot:cabecera><th>{{ __('Fila') }}</th><th>{{ __('Campo') }}</th><th>{{ __('Motivo') }}</th></x-slot:cabecera>
                    @foreach ($previa['errores'] as $e)<tr><td>{{ $e['fila'] }}</td><td>{{ $e['campo'] }}</td><td>{{ $e['mensaje'] }}</td></tr>@endforeach
                </x-ui.table>
            @endif
            <div class="mt-4 flex justify-end"><x-ui.button :disabled="$previa['validas'] === 0" wire:click="importar" wire:confirm="{{ trans_choice('{1} ¿Crear 1 cuenta con este archivo?|[0,*] ¿Crear :count cuentas con este archivo?', $previa['validas']) }}">{{ trans_choice('{1} Crear 1 cuenta|[0,*] Crear :count cuentas', $previa['validas']) }}</x-ui.button></div>
        </x-ui.card>
    @endif

    <x-ui.card :titulo="__('Lotes anteriores')">
        <x-ui.table :vacio="__('Aún no hay importaciones.')">
            <x-slot:cabecera><th>{{ __('Archivo') }}</th><th>{{ __('Fecha') }}</th><th class="text-right">{{ __('Creados') }}</th><th class="text-right">{{ __('Con error') }}</th></x-slot:cabecera>
            @foreach ($lotes as $l)
                <tr><td><a href="{{ route('usuarios.lote', $l->id) }}" wire:navigate class="font-medium hover:text-marca-700 hover:underline">{{ $l->file_name }}</a></td>
                    <td>{{ \Illuminate\Support\Carbon::parse($l->created_at)->format('d/m/Y H:i') }}</td><td class="text-right">{{ $l->valid_rows }}</td><td class="text-right">{{ $l->error_rows }}</td></tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>
</div>
