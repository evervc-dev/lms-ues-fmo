{{--
    PDF de una tabla cualquiera (ADR-049): sirve para toda exportación que ya resolvió su Service en
    columnas y filas, sin una vista propia por pantalla.

    Variables: $titulo, $subtitulo, $columnas, $filas, $desdeColumna (índice desde el que las celdas se
    alinean a la derecha, por ser números) y $nota (aviso de filas recortadas, Exportacion::recortarParaPdf()).
--}}
@php($desde = $desdeColumna ?? count($columnas))
<x-exportacion.hoja :titulo="$titulo" :subtitulo="$subtitulo ?? null">
    @isset($nota)
        <p class="nota">{{ $nota }}</p>
    @endisset
    @if (count($filas) === 0)
        <p class="vacio">{{ __('No hay datos para exportar con los filtros elegidos.') }}</p>
    @else
        <table>
            <thead>
                <tr>@foreach ($columnas as $i => $columna)<th @class(['num' => $i >= $desde])>{{ $columna }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>@foreach (array_values((array) $fila) as $i => $celda)<td @class(['num' => $i >= $desde])>{{ $celda === null ? '—' : $celda }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-exportacion.hoja>
