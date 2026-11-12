{{-- Vista de sección › Asistencia (RF19, RF21, RF22). --}}
@php($colores = ['present' => 'verde', 'late' => 'ambar', 'absent' => 'rojo', 'justified' => 'azul'])
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    @if ($esEstudiante)
        <div class="grid gap-3 sm:grid-cols-4">
            <x-ui.stat :etiqueta="__('Asistencia')" :valor="$historial['porcentaje'] !== null ? $historial['porcentaje'].' %' : '—'" icono="clipboard-document-check"
                :tono="$historial['porcentaje'] === null ? 'neutro' : ($historial['porcentaje'] < 75 ? 'error' : ($historial['porcentaje'] < 85 ? 'aviso' : 'exito'))" :detalle="__('Mínimo 75 %; aviso bajo 85 %.')" />
            @foreach ($historial['porComputo'] as $c)<x-ui.stat :etiqueta="$c->name" :valor="$c->porcentaje.' %'" :detalle="trans_choice('{1} 1 clase|[0,*] :count clases', $c->clases)" />@endforeach
        </div>
        <x-ui.card :titulo="__('Mi historial')">
            <x-ui.table :vacio="__('Aún no hay clases registradas.')">
                <x-slot:cabecera><th>{{ __('Fecha') }}</th><th>{{ __('Modalidad') }}</th><th>{{ __('Estado') }}</th><th>{{ __('Método') }}</th><th></th></x-slot:cabecera>
                @foreach ($historial['registros'] as $r)
                    <tr wire:key="reg-{{ $r->id }}">
                        <td>{{ \Illuminate\Support\Carbon::parse($r->session_date)->format('d/m/Y') }}</td>
                        <td>{{ $r->modality === 'virtual' ? __('Virtual') : __('Presencial') }}</td>
                        <td><x-ui.badge :color="$colores[$r->status]">{{ __($estados[$r->status]) }}</x-ui.badge></td>
                        <td>{{ __($metodos[$r->method] ?? $r->method) }}</td>
                        <td class="text-right">
                            @if ($r->status === 'absent' && ! $r->justificacion)<x-ui.button tamano="sm" variante="fantasma" wire:click="pedirJustificacion({{ $r->id }})">{{ __('Justificar') }}</x-ui.button>
                            @elseif ($r->justificacion === 'pending')<x-ui.badge color="ambar">{{ __('Justificación en revisión') }}</x-ui.badge>
                            @elseif ($r->justificacion === 'rejected')<x-ui.badge color="rojo">{{ __('Justificación rechazada') }}</x-ui.badge>@endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>

        <x-ui.modal nombre="justificar" :titulo="__('Justificar inasistencia')">
            <form wire:submit="justificar" id="form-justificar" class="flex flex-col gap-4">
                <x-ui.field :etiqueta="__('Motivo')" para="motivoJustificacion" error="motivoJustificacion" requerido><x-ui.textarea id="motivoJustificacion" wire:model="motivoJustificacion" /></x-ui.field>
                <x-ui.field :etiqueta="__('Documento (opcional)')" para="documento" error="documento" :ayuda="__('Constancia médica u otra. PDF o imagen, máximo 5 MB.')"><input id="documento" type="file" wire:model="documento" accept=".pdf,.jpg,.jpeg,.png" class="text-sm"></x-ui.field>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'justificar')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-justificar">{{ __('Enviar') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @else
        {{-- RF32: un cambio de modalidad aprobado para hoy decide el método de asistencia. --}}
        @if ($abre && $modalidadHoy === 'virtual')
            <x-ui.alert tipo="info" :titulo="__('Hoy la clase es virtual')">
                {{ __('Hay un cambio de modalidad aprobado para hoy. La asistencia no se toma con QR: programe la clase virtual y suba el reporte de Meet en «Clases virtuales».') }}
            </x-ui.alert>
        @elseif ($abre)
            <x-ui.card :titulo="__('Abrir asistencia')" :descripcion="$modalidadHoy === 'in_person' ? __('Hoy la clase es presencial por un cambio de modalidad aprobado: la asistencia se toma con QR.') : null">
                <form wire:submit="abrir" class="flex flex-wrap items-end gap-3">
                    <x-ui.field :etiqueta="__('Vigencia (5 a 15 min)')" para="minutos" error="minutos"><x-ui.input id="minutos" type="number" min="5" max="15" wire:model="minutos" class="w-28" /></x-ui.field>
                    <x-ui.field :etiqueta="__('Modalidad')" para="modalidad" error="modalidad"><x-ui.select id="modalidad" wire:model="modalidad" :disabled="$modalidadHoy !== null"><option value="in_person">{{ __('Presencial') }}</option><option value="virtual">{{ __('Virtual') }}</option></x-ui.select></x-ui.field>
                    <x-ui.button type="submit" icono="qr-code">{{ __('Abrir asistencia') }}</x-ui.button>
                </form>
            </x-ui.card>
        @endif
        <x-ui.card :titulo="__('Sesiones')">
            <x-ui.table :vacio="__('Aún no hay sesiones de asistencia.')">
                <x-slot:cabecera><th>{{ __('Fecha') }}</th><th>{{ __('Modalidad') }}</th><th class="text-right">{{ __('Presentes') }}</th><th class="text-right">{{ __('Tardes') }}</th><th class="text-right">{{ __('Ausentes') }}</th><th class="text-right">{{ __('Justificadas') }}</th><th></th></x-slot:cabecera>
                @foreach ($sesiones as $s)
                    <tr wire:key="ses-{{ $s->id }}">
                        <td>{{ \Illuminate\Support\Carbon::parse($s->session_date)->format('d/m/Y') }} @if ($s->status === 'open')<x-ui.badge color="verde">{{ __('Abierta') }}</x-ui.badge>@endif</td>
                        <td>{{ $s->modality === 'virtual' ? __('Virtual') : __('Presencial') }}</td>
                        <td class="text-right">{{ $s->presentes }}</td><td class="text-right">{{ $s->tardes }}</td><td class="text-right">{{ $s->ausentes }}</td><td class="text-right">{{ $s->justificadas }}</td>
                        <td class="text-right">@if ($abre)<x-ui.button tamano="sm" variante="fantasma" :href="route('secciones.asistencia.sesion', ['seccion' => $seccion, 'sesion' => $s->ulid])">{{ __('Ver') }}</x-ui.button>@endif</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif

    <livewire:asistencia.clases-virtuales :seccion-id="$seccionId" :key="'clases-virtuales-'.$seccionId" />
</div>
