{{-- Detalle de una materia para inscribirse (RF29, ADR-050): descripción, grupos y «Inscribirme con clave». --}}
@php($dias = [1 => __('Lun'), 2 => __('Mar'), 3 => __('Mié'), 4 => __('Jue'), 5 => __('Vie'), 6 => __('Sáb'), 7 => __('Dom')])
@php($horario = fn ($s) => $s->horario->map(fn ($h) => ($dias[$h->weekday] ?? '').' '.substr($h->start_time, 0, 5).'–'.substr($h->end_time, 0, 5).($h->aula ? ' · '.$h->aula : ''))->implode(' · '))
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="$asignatura->code.' · '.$asignatura->name">
        <div class="mt-2 flex flex-wrap gap-1">
            <x-ui.badge color="marca">{{ __('Ciclo :n del plan', ['n' => $asignatura->cycle]) }}</x-ui.badge>
            <x-ui.badge>{{ trans_choice('{1} 1 crédito|[2,*] :count créditos', $asignatura->credits) }}</x-ui.badge>
            @if ($inscrito)<x-ui.badge color="verde">{{ __('Inscrito') }}</x-ui.badge>@endif
        </div>
        <x-slot:acciones>
            <x-ui.button icono="user-plus" x-on:click="$dispatch('abrir-modal', 'inscribirme')" :disabled="$inscrito || ! $tiene_clave || $secciones->isEmpty() || $plazo['vencido']">{{ __('Inscribirme con clave') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.encabezado>

    @if ($plazo['vencido'])
        <x-ui.alert tipo="aviso">{{ __('El plazo de inscripción terminó el :fecha. Si necesita inscribirse, pida una prórroga a su coordinador.', ['fecha' => $plazo['fecha']->format('d/m/Y')]) }}</x-ui.alert>
    @else
        <x-ui.alert tipo="info">{{ __('Inscripción abierta hasta el :fecha.', ['fecha' => $plazo['fecha']->format('d/m/Y')]) }}</x-ui.alert>
    @endif

    @if ($inscrito)
        <x-ui.alert tipo="info">{{ __('Ya está inscrito en esta materia en el período en curso. La encuentra en «Mis materias».') }}</x-ui.alert>
    @elseif (! $tiene_clave)
        <x-ui.alert tipo="aviso">{{ __('La inscripción con clave aún no está habilitada para esta materia. Su coordinador o su jefe de departamento la habilitan.') }}</x-ui.alert>
    @elseif ($secciones->isEmpty())
        <x-ui.alert tipo="aviso">{{ __('Esta materia no tiene grupos abiertos en el período en curso.') }}</x-ui.alert>
    @endif

    <x-ui.card :titulo="__('Descripción de la materia')">
        @if (filled($asignatura->description))
            <p class="text-sm text-texto">{!! nl2br(e($asignatura->description)) !!}</p>
        @else
            <p class="text-sm text-texto-suave">{{ __('El docente aún no agregó una descripción.') }}</p>
        @endif
    </x-ui.card>

    <x-ui.card :titulo="__('Grupos')" :descripcion="__('Grupos abiertos del período en curso.')">
        <x-ui.table :vacio="__('No hay grupos abiertos de esta materia.')">
            <x-slot:cabecera>
                <th>{{ __('Grupo') }}</th>
                <th>{{ __('Docente') }}</th>
                <th>{{ __('Horario') }}</th>
                <th class="text-right">{{ __('Cupo') }}</th>
                <th>{{ __('Estado') }}</th>
            </x-slot:cabecera>
            @foreach ($secciones as $s)
                <tr wire:key="grupo-{{ $s->id }}">
                    <td class="font-semibold">{{ $s->code }}</td>
                    <td>{{ $s->docente ?? __('Docente por asignar') }}</td>
                    <td class="text-texto-suave">{{ $horario($s) ?: __('sin horario') }}</td>
                    <td class="text-right">{{ $s->inscritos }} / {{ $s->max_capacity }}</td>
                    <td>
                        @if ($s->llena)
                            <x-ui.badge color="rojo">{{ __('Sin cupo') }}</x-ui.badge>
                        @elseif ($s->choque)
                            <x-ui.badge color="ambar">{{ __('Choca con :s', ['s' => $s->choque]) }}</x-ui.badge>
                        @else
                            <x-ui.badge color="verde">{{ trans_choice('{1} 1 cupo libre|[2,*] :count cupos libres', $s->max_capacity - $s->inscritos) }}</x-ui.badge>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    @if (! $inscrito && $tiene_clave && $secciones->isNotEmpty() && ! $plazo['vencido'])
        <x-ui.modal nombre="inscribirme" :titulo="__('Inscribirme con clave')">
            <form wire:submit="inscribirme" id="form-inscribirme" class="flex flex-col gap-4">
                <div>
                    <p class="mb-2 text-sm font-medium text-texto">{{ __('Elija su grupo:') }}</p>
                    <div class="flex flex-col gap-2">
                        @foreach ($secciones as $s)
                            @php($bloqueada = $s->llena || $s->choque)
                            <label wire:key="opcion-{{ $s->id }}" @class(['flex items-start gap-3 rounded-lg p-3 ring-1', 'ring-borde hover:bg-zinc-50' => ! $bloqueada, 'cursor-not-allowed bg-zinc-50 text-texto-desactivado ring-borde' => $bloqueada])>
                                <input type="radio" wire:model="seccionElegida" value="{{ $s->id }}" @disabled($bloqueada) class="mt-1 text-marca-600 focus:ring-marca-600">
                                <span class="text-sm"><span class="font-semibold">{{ __('Grupo :g', ['g' => $s->code]) }}</span> · {{ $horario($s) ?: __('sin horario') }}
                                    <span class="block text-xs">{{ $s->llena ? __('Sin cupo') : ($s->choque ? __('Choca con :s', ['s' => $s->choque]) : trans_choice('{1} 1 cupo libre|[2,*] :count cupos libres', $s->max_capacity - $s->inscritos)) }}</span></span>
                            </label>
                        @endforeach
                    </div>
                    @error('seccionElegida')<p class="mt-2 text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                </div>
                <x-ui.field :etiqueta="__('Clave de la asignatura')" para="clave" error="clave" :ayuda="__('Se la da su docente, su coordinador o su jefe de departamento.')" requerido>
                    <x-ui.input id="clave" wire:model="clave" :placeholder="$asignatura->code.'-XXXXX'" autocomplete="off" />
                </x-ui.field>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'inscribirme')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-inscribirme">{{ __('Inscribirme') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif
</div>
