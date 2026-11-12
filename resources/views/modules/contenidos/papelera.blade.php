{{-- Papelera de la sección (RF11). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />
    @error('papelera')<x-ui.alert tipo="error">{{ $message }}</x-ui.alert>@enderror
    <x-ui.card ajustar :titulo="__('Papelera')" :descripcion="__('Lo que elimine se puede restaurar durante :dias días; después se borra definitivamente.', ['dias' => $dias])">
        <x-ui.table ajustar :vacio="__('La papelera está vacía.')">
            <x-slot:cabecera><th>{{ __('Elemento') }}</th><th>{{ __('Tipo') }}</th><th>{{ __('Unidad') }}</th><th>{{ __('Eliminado') }}</th><th class="text-right">{{ __('Quedan') }}</th><th></th></x-slot:cabecera>
            @foreach ($eliminados as $r)
                @php($eliminado = \Illuminate\Support\Carbon::parse($r->deleted_at))
                <tr wire:key="papelera-{{ $r->tipo }}-{{ $r->id }}">
                    <td class="font-medium">{{ $r->title }}</td>
                    <td>{{ $r->tipo === 'unidad' ? __('Unidad') : __($tipos[$r->resource_type] ?? $r->resource_type) }}</td>
                    <td>{{ $r->unidad ?? '—' }}</td>
                    <td>{{ $eliminado->format('d/m/Y') }}</td>
                    <td class="text-right">{{ trans_choice('{1} 1 día|[0,*] :count días', max(0, $dias - (int) $eliminado->diffInDays(now()))) }}</td>
                    <td class="text-right">
                        <x-ui.button tamano="sm" variante="fantasma" wire:click="{{ $r->tipo === 'unidad' ? 'restaurarUnidad' : 'restaurar' }}({{ $r->id }})" wire:confirm="{{ __('¿Restaurar «:t»? Volverá a verse en la sección.', ['t' => $r->title]) }}">{{ __('Restaurar') }}</x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>
</div>
