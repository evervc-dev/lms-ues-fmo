{{-- Vista de sección › Progreso (RF18): avance por unidad de cada estudiante. --}}
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    <x-ui.card :titulo="__('Avance por unidad')"
        :descripcion="__('Porcentaje de materiales completados en cada unidad. Un material se completa a mano, con «Ya lo leí», o solo al cumplirse su regla.')">
        @if ($unidades->isEmpty())
            <x-ui.empty-state icono="chart-bar" :titulo="__('Aún no hay unidades')">
                {{ __('Cree la primera unidad con sus materiales para seguir el avance.') }}
            </x-ui.empty-state>
        @else
            <x-ui.table :vacio="__('Aún no hay estudiantes matriculados en la sección.')">
                <x-slot:cabecera>
                    <th scope="col">{{ __('Estudiante') }}</th>
                    @foreach ($unidades as $u)
                        <th scope="col"><span class="block max-w-40 truncate normal-case" title="{{ $u->title }}">{{ $u->title }}</span></th>
                    @endforeach
                    <th scope="col">{{ __('Total') }}</th>
                </x-slot:cabecera>

                @foreach ($estudiantes as $e)
                    <tr wire:key="progreso-{{ $e->id }}">
                        <td class="whitespace-nowrap">{{ $e->last_name }}, {{ $e->first_name }}</td>
                        @foreach ($unidades as $u)
                            <td class="min-w-36">
                                @if (($e->unidades[$u->id] ?? null) === null)
                                    <span class="text-xs text-texto-suave">{{ __('Sin materiales') }}</span>
                                @else
                                    <x-ui.progress :valor="$e->unidades[$u->id]" :etiqueta="__('Avance de :e en :u', ['e' => $e->first_name.' '.$e->last_name, 'u' => $u->title])" />
                                @endif
                            </td>
                        @endforeach
                        <td class="min-w-36">
                            @if ($e->total === null)
                                <span class="text-xs text-texto-suave">{{ __('Sin materiales') }}</span>
                            @else
                                <x-ui.progress :valor="$e->total" :tono="$e->total >= 70 ? 'exito' : ($e->total >= 30 ? 'aviso' : 'error')"
                                    :etiqueta="__('Avance total de :e', ['e' => $e->first_name.' '.$e->last_name])" />
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
