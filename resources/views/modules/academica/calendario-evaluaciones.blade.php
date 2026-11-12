{{-- Calendario de evaluaciones por ámbito (RF06, CP-RF06-04, ADR-068). --}}
@php($abierto = $periodo->status !== 'closed')
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

    <x-ui.card :titulo="__('Fechas del período')"
        :descripcion="__('El evento queda en el calendario de quien lo sincroniza e invita solo a los docentes y estudiantes de ese ámbito. Las fechas de todo el período las fija el Administrador; usted gestiona las suyas.')">
        @if ($abierto && $calendarHabilitado && ! $calendarConectado)
            <x-ui.alert tipo="info" class="mb-4">
                {{ __('Para sincronizar, conecte primero su Google Calendar.') }}
                <a href="{{ route('perfil.google-calendar') }}" class="font-medium underline">{{ __('Ir a Mi perfil › Google Calendar') }}</a>
            </x-ui.alert>
        @endif

        <x-ui.table :vacio="__('Aún no hay fechas en el calendario de evaluaciones.')">
            <x-slot:cabecera>
                <th>{{ __('Evento') }}</th><th>{{ __('Ámbito') }}</th><th>{{ __('Fecha') }}</th><th>{{ __('Google Calendar') }}</th>
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
                    <td>
                        @switch($f->sync_status)
                            @case('synced') <x-ui.badge color="verde">{{ __('Sincronizada') }}</x-ui.badge> @break
                            @case('pending') <x-ui.badge color="azul">{{ __('Enviando…') }}</x-ui.badge> @break
                            @case('failed') <x-ui.badge color="rojo" :title="$f->last_error">{{ __('Falló') }}</x-ui.badge>
                                @if ($f->last_error)<p class="mt-1 max-w-xs text-xs text-red-700">{{ \Illuminate\Support\Str::limit($f->last_error, 120) }}</p>@endif
                                @break
                            @default <x-ui.badge color="gris">{{ __('Sin sincronizar') }}</x-ui.badge>
                        @endswitch
                    </td>
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

    <x-ui.modal nombre="agregar-fecha" :titulo="__('Agregar fecha al calendario de evaluaciones')">
        <form wire:submit="agregarFecha" id="form-agregar-fecha" class="flex flex-col gap-4">
            <x-ui.field :etiqueta="__('Evento')" para="fecha-titulo" error="tituloFecha" requerido><x-ui.input id="fecha-titulo" wire:model="tituloFecha" maxlength="255" :placeholder="__('Primer parcial')" /></x-ui.field>
            <x-ui.field :etiqueta="__('Ámbito')" para="fecha-ambito" error="ambito" :ayuda="__('A quién le aplica la fecha y a quién se invita al sincronizar.')" requerido>
                <x-ui.select id="fecha-ambito" wire:model="ambito">
                    @foreach ($ambitos as $a)<option value="{{ $a['valor'] }}">{{ $a['etiqueta'] }}</option>@endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field :etiqueta="__('Inicio')" para="fecha-inicio" error="inicioFecha" requerido><x-ui.input id="fecha-inicio" type="datetime-local" wire:model="inicioFecha" /></x-ui.field>
            <x-ui.field :etiqueta="__('Fin')" para="fecha-fin" error="finFecha" :ayuda="__('Déjelo vacío para un evento de todo el día.')"><x-ui.input id="fecha-fin" type="datetime-local" wire:model="finFecha" /></x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'agregar-fecha')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-agregar-fecha">{{ __('Agregar') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
