{{-- Calendario de evaluaciones por ámbito (RF06, CP-RF06-04, ADR-068). El Docente y el Estudiante lo consultan sin
     poder cambiarlo (ADR-094): sin «gestionar» no hay agregar, eliminar, sincronizar ni la columna de Google Calendar. --}}
@php($abierto = $periodo->status !== 'closed' && $puedeGestionar)
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Calendario de evaluaciones')"
        :subtitulo="$periodo->name.' · '.\Illuminate\Support\Carbon::parse($periodo->start_date)->format('d/m/Y').' – '.\Illuminate\Support\Carbon::parse($periodo->end_date)->format('d/m/Y')">
        @if ($abierto)
            <x-slot:acciones>
                <x-ui.button variante="secundario" icono="plus" x-on:click="$dispatch('abrir-modal', 'agregar-fecha')">{{ __('Agregar fecha') }}</x-ui.button>
                @if ($calendarHabilitado && $calendarConectado)
                    <x-ui.button icono="calendar" wire:click="sincronizarCalendario"
                        wire:confirm="{{ __('Se enviarán las fechas de su ámbito a su Google Calendar y Google avisará por correo a los docentes y estudiantes involucrados. ¿Continuar?') }}">{{ __('Sincronizar con mi Google Calendar') }}</x-ui.button>
                @endif
            </x-slot:acciones>
        @endif
    </x-ui.encabezado>

    {{-- Vista de mes (RF06): de lunes a domingo, dentro de las fechas del período. Pulsar un día propone esa fecha al agregar. --}}
    @php($colorAmbito = fn ($f) => $f->department_id !== null ? 'bg-sky-50 text-sky-800 ring-sky-200' : ($f->career_id !== null ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-marca-50 text-marca-800 ring-marca-200'))
    @php($puntoAmbito = fn ($f) => $f->department_id !== null ? 'bg-sky-500' : ($f->career_id !== null ? 'bg-emerald-500' : 'bg-marca-600'))
    @php($agrega = $abierto && $ambitos !== [])
    <x-ui.card>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button type="button" wire:click="mesAnterior" @disabled(! $hayAnterior) class="inline-flex size-9 items-center justify-center rounded-lg ring-1 ring-borde hover:bg-zinc-50 disabled:opacity-40" aria-label="{{ __('Mes anterior') }}"><x-ui.icon name="arrow-left" class="size-4" /></button>
                <h2 class="min-w-40 text-center text-lg font-semibold capitalize text-texto">{{ $mesVisible->translatedFormat('F Y') }}</h2>
                <button type="button" wire:click="mesSiguiente" @disabled(! $haySiguiente) class="inline-flex size-9 items-center justify-center rounded-lg ring-1 ring-borde hover:bg-zinc-50 disabled:opacity-40" aria-label="{{ __('Mes siguiente') }}"><x-ui.icon name="chevron-right" class="size-4" /></button>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-xs text-texto-suave">
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-marca-600"></span>{{ __('Todo el período') }}</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-sky-500"></span>{{ __('Departamento') }}</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-emerald-500"></span>{{ __('Carrera') }}</span>
            </div>
        </div>

        <div class="grid grid-cols-7 overflow-hidden rounded-lg ring-1 ring-borde" role="grid" aria-label="{{ __('Calendario de :m', ['m' => $mesVisible->translatedFormat('F Y')]) }}">
            @foreach ([__('Lun'), __('Mar'), __('Mié'), __('Jue'), __('Vie'), __('Sáb'), __('Dom')] as $nombreDia)
                <div class="border-b border-borde bg-zinc-50 py-2 text-center text-xs font-semibold uppercase tracking-wide text-texto-suave" role="columnheader">{{ $nombreDia }}</div>
            @endforeach
            @foreach ($semanas as $semana)
                @foreach ($semana as $d)
                    @php($pulsable = $agrega && $d['enPeriodo'])
                    <div wire:key="dia-{{ $d['fecha'] }}" role="gridcell"
                        @class(['flex min-h-14 flex-col gap-1 border-b border-r border-borde p-1 text-left sm:min-h-24 sm:p-1.5 [&:nth-child(7n)]:border-r-0',
                            'bg-white' => $d['enPeriodo'] && $d['delMes'], 'bg-zinc-50/70' => ! $d['delMes'] || ! $d['enPeriodo']])>
                        @if ($pulsable)
                            <button type="button" wire:click="elegirDia('{{ $d['fecha'] }}')" title="{{ __('Agregar una fecha el :f', ['f' => \Illuminate\Support\Carbon::parse($d['fecha'])->format('d/m/Y')]) }}"
                                @class(['self-start rounded-full px-1.5 text-xs font-medium leading-6 hover:bg-marca-50 hover:text-marca-700', 'bg-marca-600 text-white hover:bg-marca-700 hover:text-white' => $d['hoy'], 'text-texto' => ! $d['hoy'] && $d['delMes'], 'text-texto-desactivado' => ! $d['delMes']])>{{ $d['dia'] }}</button>
                        @else
                            <span @class(['self-start rounded-full px-1.5 text-xs leading-6', 'bg-marca-600 font-medium text-white' => $d['hoy'], 'text-texto-desactivado' => ! $d['hoy']])>{{ $d['dia'] }}</span>
                        @endif
                        {{-- En el teléfono, un punto por fecha; desde sm, la fecha con su título. --}}
                        @if ($d['eventos']->isNotEmpty())
                            <div class="flex flex-wrap gap-1 sm:hidden">
                                @foreach ($d['eventos'] as $f)<span class="size-2 rounded-full {{ $puntoAmbito($f) }}" title="{{ $f->title }}"></span>@endforeach
                            </div>
                            <div class="hidden flex-col gap-1 sm:flex">
                                @foreach ($d['eventos']->take(3) as $f)
                                    <span class="truncate rounded px-1.5 py-0.5 text-xs font-medium ring-1 {{ $colorAmbito($f) }}" title="{{ $f->title }} · {{ $f->ambito }}">{{ $f->title }}</span>
                                @endforeach
                                @if ($d['eventos']->count() > 3)<span class="px-1 text-xs text-texto-suave">{{ __('+:n más', ['n' => $d['eventos']->count() - 3]) }}</span>@endif
                            </div>
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>

        {{-- En el teléfono los títulos no caben en la cuadrícula: las fechas del mes van en lista. --}}
        @php($delMes = $fechas->filter(fn ($f) => \Illuminate\Support\Carbon::parse($f->starts_at)->timezone(config('app.timezone'))->format('Y-m') === $mesVisible->format('Y-m')))
        <div class="mt-4 flex flex-col gap-2 sm:hidden">
            @forelse ($delMes as $f)
                <div wire:key="agenda-{{ $f->id }}" class="flex items-start gap-3 text-sm">
                    <span class="mt-1.5 size-2.5 shrink-0 rounded-full {{ $puntoAmbito($f) }}"></span>
                    <div class="min-w-0"><p class="font-medium text-texto">{{ $f->title }}</p><p class="text-xs text-texto-suave">{{ \Illuminate\Support\Carbon::parse($f->starts_at)->timezone(config('app.timezone'))->translatedFormat($f->ends_at ? 'D j, H:i' : 'D j') }} · {{ $f->ambito }}</p></div>
                </div>
            @empty
                <p class="text-sm text-texto-suave">{{ __('No hay fechas este mes.') }}</p>
            @endforelse
        </div>
        @if ($agrega)
            <p class="mt-3 text-xs text-texto-suave">{{ __('Pulse un día del período para agregar una fecha que empiece ese día.') }}</p>
        @endif
    </x-ui.card>

    <x-ui.card :titulo="$puedeGestionar ? __('Lista de fechas y sincronización') : __('Lista de fechas')"
        :descripcion="$puedeGestionar ? __('El evento queda en el calendario de quien lo sincroniza e invita solo a los docentes y estudiantes de ese ámbito. Las fechas de todo el período las fija el Administrador; usted gestiona las suyas.') : __('Las fechas de evaluación que le aplican: las de todo el período y las del departamento y la carrera de sus materias.')">
        @if ($abierto && $calendarHabilitado && ! $calendarConectado)
            <x-ui.alert tipo="info" class="mb-4">
                {{ __('Para sincronizar, conecte primero su Google Calendar.') }}
                <a href="{{ route('perfil.google-calendar') }}" class="font-medium underline">{{ __('Ir a Mi perfil › Google Calendar') }}</a>
            </x-ui.alert>
        @endif

        <x-ui.table :vacio="__('Aún no hay fechas en el calendario de evaluaciones.')">
            <x-slot:cabecera>
                <th>{{ __('Evento') }}</th><th>{{ __('Ámbito') }}</th><th>{{ __('Fecha') }}</th>@if ($puedeGestionar)<th>{{ __('Google Calendar') }}</th>@endif
                @if ($abierto)<th class="text-right">{{ __('Acciones') }}</th>@endif
            </x-slot:cabecera>
            @foreach ($fechas as $f)
                <tr wire:key="fecha-{{ $f->id }}">
                    <td>{{ $f->title }}</td>
                    <td><x-ui.badge :color="$f->editable ? 'azul' : 'gris'">{{ $f->ambito }}</x-ui.badge></td>
                    <td class="whitespace-nowrap">
                        {{ \Illuminate\Support\Carbon::parse($f->starts_at)->timezone(config('app.timezone'))->format($f->ends_at ? 'd/m/Y H:i' : 'd/m/Y') }}
                        @if ($f->ends_at) – {{ \Illuminate\Support\Carbon::parse($f->ends_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') }} @else <span class="text-texto-suave">· {{ __('todo el día') }}</span> @endif
                    </td>
                    @if ($puedeGestionar)
                    <td>
                        {{-- Sin la integración habilitada no hay nada sincronizado, aunque quede un registro anterior (QA 03/11, H-07). --}}
                        @if (! $calendarHabilitado)
                            <x-ui.badge color="gris">{{ __('No disponible') }}</x-ui.badge>
                        @else
                        @switch($f->sync_status)
                            @case('synced') <x-ui.badge color="verde">{{ __('Sincronizada') }}</x-ui.badge> @break
                            @case('pending') <x-ui.badge color="azul">{{ __('Enviando…') }}</x-ui.badge> @break
                            @case('failed') <x-ui.badge color="rojo" :title="$f->last_error">{{ __('Falló') }}</x-ui.badge>
                                @if ($f->last_error)<p class="mt-1 max-w-xs text-xs text-red-700">{{ \Illuminate\Support\Str::limit($f->last_error, 120) }}</p>@endif
                                @break
                            @default <x-ui.badge color="gris">{{ __('Sin sincronizar') }}</x-ui.badge>
                        @endswitch
                        @endif
                    </td>
                    @endif
                    @if ($abierto)
                        <td class="text-right">
                            @if ($f->editable)
                                <x-ui.button tamano="sm" variante="peligro" wire:click="eliminarFecha({{ $f->id }})"
                                    wire:confirm="{{ __('¿Eliminar esta fecha? Si estaba en Google Calendar, se borrará el evento y se avisará a los invitados.') }}">{{ __('Eliminar') }}</x-ui.button>
                            @else
                                <span class="text-xs text-texto-suave">{{ __('De otro ámbito') }}</span>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    @if ($abierto)
    <x-ui.modal nombre="agregar-fecha" :titulo="__('Agregar fecha al calendario de evaluaciones')">
        {{-- «Todo el día» deja el fin vacío, que es como el Service guarda un evento de día completo. --}}
        <form wire:submit="agregarFecha" id="form-agregar-fecha" class="flex flex-col gap-4" x-data="{ todoElDia: ! $wire.finFecha, soloFecha: '' }" x-effect="soloFecha = ($wire.inicioFecha || '').slice(0, 10)">
            <x-ui.field :etiqueta="__('Evento')" para="fecha-titulo" error="tituloFecha" requerido><x-ui.input id="fecha-titulo" wire:model="tituloFecha" maxlength="255" :placeholder="__('Primer parcial')" /></x-ui.field>
            <x-ui.field :etiqueta="__('Ámbito')" para="fecha-ambito" error="ambito" :ayuda="__('A quién le aplica la fecha y a quién se invita al sincronizar.')" requerido>
                <x-ui.select id="fecha-ambito" wire:model="ambito">
                    @foreach ($ambitos as $a)<option value="{{ $a['valor'] }}">{{ $a['etiqueta'] }}</option>@endforeach
                </x-ui.select>
            </x-ui.field>
            <label class="flex items-center gap-2 text-sm text-texto">
                <input type="checkbox" x-model="todoElDia" x-on:change="if (todoElDia) $wire.set('finFecha', '')" class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600">
                {{ __('Todo el día') }}
            </label>
            <div class="grid gap-4" :class="todoElDia ? '' : 'sm:grid-cols-2'">
                {{-- QA admin 21/11: con «Todo el día» se pide solo la fecha; la hora (08:00) la pone el formulario. --}}
                <div x-show="todoElDia">
                    <x-ui.field :etiqueta="__('Fecha')" para="fecha-dia" error="inicioFecha" requerido>
                        <x-ui.input id="fecha-dia" type="date" x-model="soloFecha" x-on:change="$wire.set('inicioFecha', soloFecha ? soloFecha + 'T08:00' : '')" />
                    </x-ui.field>
                </div>
                <div x-show="! todoElDia" x-cloak>
                    <x-ui.field :etiqueta="__('Inicio')" para="fecha-inicio" error="inicioFecha" requerido><x-ui.input id="fecha-inicio" type="datetime-local" wire:model="inicioFecha" /></x-ui.field>
                </div>
                <div x-show="! todoElDia" x-cloak>
                    <x-ui.field :etiqueta="__('Fin')" para="fecha-fin" error="finFecha"><x-ui.input id="fecha-fin" type="datetime-local" wire:model="finFecha" /></x-ui.field>
                </div>
            </div>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'agregar-fecha')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-agregar-fecha">{{ __('Agregar') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
    @endif
</div>
