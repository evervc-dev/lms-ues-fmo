{{-- Desempeño docente (RF33, maqueta 27). --}}
@php($pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').' %')
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Desempeño docente')" :subtitulo="__('Docentes de su departamento en el período elegido.')">
        <x-slot:acciones>
            <x-ui.select wire:model.live="periodo" :aria-label="__('Período')">
                @foreach ($periodos as $p)
                    <option value="{{ $p->status === 'active' ? '' : $p->id }}">{{ $p->name }}{{ $p->status === 'active' ? ' · '.__('en curso') : '' }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select wire:model.live="orden" :aria-label="__('Ordenar por')">
                <option value="last_name">{{ __('Ordenar por nombre') }}</option><option value="retraso">{{ __('Retraso') }}</option><option value="aTiempo">{{ __('A tiempo') }}</option><option value="recursos">{{ __('Recursos') }}</option>
                <option value="revisionHoras">{{ __('Tiempo de revisión') }}</option><option value="publicadas">{{ __('Notas publicadas') }}</option>
            </x-ui.select>
            <x-ui.exportar />
        </x-slot:acciones>
    </x-ui.encabezado>

    <div class="grid gap-3 sm:grid-cols-3">
        <x-ui.stat :etiqueta="__('Retraso promedio al abrir la asistencia')" :valor="$promedios['retraso'] === null ? '—' : __(':m min', ['m' => rtrim(rtrim(number_format($promedios['retraso'], 1), '0'), '.')])" icono="clock" />
        <x-ui.stat :etiqueta="__('Recursos por docente')" :valor="$promedios['recursos'] === null ? '—' : round($promedios['recursos'])" icono="book-open" />
        <x-ui.stat :etiqueta="__('Revisión promedio')" :valor="$promedios['revision'] === null ? '—' : round($promedios['revision']).' h'" icono="inbox-stack" />
    </div>

    <x-ui.alert tipo="info" :titulo="__('Cómo se calculan')">{{ __('Retraso: minutos promedio entre el inicio de la clase según el horario y la apertura de la sesión de asistencia (abrir antes cuenta 0; no cuentan las sesiones de días sin clase en el horario). A tiempo: sesiones abiertas hasta 15 min después del inicio. Revisión: horas promedio desde la entrega hasta la publicación de la nota; «Sin publicar» son las entregas que aún no tienen nota publicada. Los indicadores son informativos y no califican al docente.') }}</x-ui.alert>

    <x-ui.table ajustar :vacio="__('No hay docentes con secciones en el período.')">
        <x-slot:cabecera><th>{{ __('Docente') }}</th><th>{{ __('Depto.') }}</th><th class="text-right">{{ __('Secciones') }}</th><th class="text-right">{{ __('Retraso') }}</th><th class="text-right">{{ __('A tiempo') }}</th><th class="text-right">{{ __('Recursos') }}</th><th class="text-right">{{ __('Revisión') }}</th><th class="text-right">{{ __('Sin publicar') }}</th><th class="text-right">{{ __('Notas publicadas') }}</th></x-slot:cabecera>
        @foreach ($filas as $f)
            <tr wire:key="doc-{{ $f->id }}">
                <td>{{ $f->last_name }}, {{ $f->first_name }}</td><td>{{ $f->departamento }}</td><td class="text-right">{{ $f->secciones }}</td>
                <td class="text-right">{{ $f->retraso === null ? '—' : __(':m min', ['m' => rtrim(rtrim(number_format($f->retraso, 1), '0'), '.')]) }}</td>
                <td @class(['text-right', 'text-red-700' => $f->aTiempo !== null && $f->aTiempo < 70])>{{ $pct($f->aTiempo) }}</td>
                <td class="text-right">{{ $f->recursos }}</td>
                <td class="text-right">{{ $f->revisionHoras === null ? '—' : $f->revisionHoras.' h' }}</td>
                <td class="text-right">{{ $f->sinPublicar }}</td>
                <td class="text-right">{{ $pct($f->publicadas) }}</td>
            </tr>
        @endforeach
    </x-ui.table>
</div>
