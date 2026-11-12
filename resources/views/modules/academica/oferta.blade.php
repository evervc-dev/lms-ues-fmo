{{-- Estadísticas de oferta académica (RF10, maqueta 50). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Estadísticas de oferta')" :subtitulo="__('Inscritos del período en curso y resultados del último período cerrado (:p).', ['p' => $cerrado ?? '—'])" />

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat :etiqueta="__('Inscritos (período en curso)')" :valor="number_format($totales['inscritos'])" icono="users" />
        <x-ui.stat :etiqueta="__('Aprobados')" :valor="number_format($totales['aprobados'])" icono="check-circle" tono="exito" />
        <x-ui.stat :etiqueta="__('Reprobados')" :valor="number_format($totales['reprobados'])" icono="x-circle" tono="error" />
        <x-ui.stat :etiqueta="__('Grupos propuestos')" :valor="$totales['grupos']" icono="squares-2x2" />
    </div>

    <x-ui.alert tipo="aviso" :titulo="__('Fórmula provisional')">
        {{ __('Demanda = reprobados de la materia + aprobados de su prerrequisito en el último período cerrado; grupos = demanda ÷ :c, redondeado hacia arriba. RF10 tiene pendiente la fórmula oficial.', ['c' => $cupo]) }}
    </x-ui.alert>

    <div class="grid gap-3 md:grid-cols-[minmax(0,20rem)]">
        <x-ui.select wire:model.live="filtroCarrera" :aria-label="__('Carrera')">
            <option value="">{{ __('Todas las carreras de su alcance') }}</option>
            @foreach ($carreras as $c)<option value="{{ $c->id }}">{{ $c->code }} · {{ $c->name }}</option>@endforeach
        </x-ui.select>
    </div>

    <x-ui.table ajustar :vacio="__('No hay asignaturas en su alcance.')">
        <x-slot:cabecera><th>{{ __('Asignatura') }}</th><th>{{ __('Carrera y ciclo del plan') }}</th><th class="text-right">{{ __('Inscritos') }}</th><th class="text-right">{{ __('Aprobados') }}</th><th class="text-right">{{ __('Reprobados') }}</th><th class="text-right">{{ __('% aprobación') }}</th><th class="text-right">{{ __('Demanda') }}</th><th class="text-right">{{ __('Grupos') }}</th></x-slot:cabecera>
        @foreach ($filas as $f)
            @php($cursaron = $f->aprobados + $f->reprobados)
            <tr wire:key="oferta-{{ $f->id }}">
                <td><span class="font-semibold">{{ $f->code }}</span> · {{ $f->name }}</td>
                <td>
                    @forelse ($f->planes as $p)
                        <x-ui.badge color="marca" class="mb-1 mr-1" :title="$p->carrera.' · '.$p->plan">{{ $p->carrera_codigo }} · {{ __('Ciclo :n', ['n' => $p->cycle]) }}</x-ui.badge>
                    @empty
                        <span class="text-xs text-texto-suave">{{ __('Sin plan de estudios asignado') }}</span>
                    @endforelse
                </td>
                <td class="text-right">{{ $f->inscritos }}</td>
                <td class="text-right">{{ $f->aprobados }}</td>
                <td class="text-right">{{ $f->reprobados }}</td>
                <td class="text-right">{{ $cursaron ? round(100 * $f->aprobados / $cursaron).' %' : '—' }}</td>
                <td class="text-right">{{ $f->demanda }}</td>
                <td class="text-right font-semibold">{{ $f->grupos }}</td>
            </tr>
        @endforeach
    </x-ui.table>
</div>
