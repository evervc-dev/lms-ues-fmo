{{-- Sesión de asistencia (RF19, RF28, RF32, maqueta 21). --}}
@php
    $colores = ['present' => 'verde', 'late' => 'ambar', 'absent' => 'rojo', 'justified' => 'azul'];
    $motivos = \App\Modules\Asistencia\Services\DiagnosticoService::MOTIVOS;
    $abierta = $sesion->status === 'open';
@endphp
<div class="flex flex-col gap-5" @if ($abierta) wire:poll.10s @endif>
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    <x-ui.encabezado :titulo="__('Sesión del :f', ['f' => \Illuminate\Support\Carbon::parse($sesion->session_date)->format('d/m/Y')])"
        :subtitulo="($sesion->modality === 'virtual' ? __('Virtual') : __('Presencial')).' · '.__('vigencia :n min', ['n' => $sesion->qr_validity_minutes])">
        <x-slot:acciones>
            @if ($abierta)
                <x-ui.badge color="verde">{{ __('Abierta') }}</x-ui.badge>
                <span x-data="{ s: {{ $segundos }} }" x-init="setInterval(() => s > 0 && s--, 1000)" class="font-mono text-sm" x-text="`${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`"></span>
                <x-ui.button variante="secundario" icono="qr-code" :href="route('secciones.asistencia.proyectar', ['seccion' => $resumen['ulid'], 'sesion' => $sesion->ulid])" :navegar="false" target="_blank">{{ __('Proyectar QR') }}</x-ui.button>
                <x-ui.button variante="peligro" wire:click="cerrar" wire:confirm="{{ __('Quienes no marcaron quedarán ausentes. ¿Cerrar la sesión?') }}">{{ __('Cerrar sesión') }}</x-ui.button>
            @else
                <x-ui.badge>{{ __('Cerrada') }}</x-ui.badge>
            @endif
            <x-ui.button variante="fantasma" x-on:click="$dispatch('abrir-modal', 'cancelar-clase')">{{ __('Solicitar cancelación') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.encabezado>

    <div class="grid gap-3 sm:grid-cols-4">
        <x-ui.stat :etiqueta="__('Presentes')" :valor="$conteo['present'] ?? 0" icono="check-circle" tono="exito" />
        <x-ui.stat :etiqueta="__('Tardes')" :valor="$conteo['late'] ?? 0" icono="clock" tono="aviso" />
        <x-ui.stat :etiqueta="__('Ausentes')" :valor="$conteo['absent'] ?? 0" icono="x-circle" tono="error" />
        <x-ui.stat :etiqueta="__('Sin registro')" :valor="$conteo['sin_registro'] ?? 0" icono="users" />
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <x-ui.card :titulo="__('Estudiantes')">
            <x-ui.table>
                <x-slot:cabecera><th>{{ __('Estudiante') }}</th><th>{{ __('Estado') }}</th><th>{{ __('Método') }}</th><th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
                @foreach ($estudiantes as $e)
                    <tr wire:key="asis-{{ $e->matricula }}">
                        <td>{{ $e->last_name }}, {{ $e->first_name }}<p class="text-xs text-texto-suave">{{ $e->student_code }}</p></td>
                        <td>@if ($e->status)<x-ui.badge :color="$colores[$e->status]">{{ __($estados[$e->status]) }}</x-ui.badge>@else<span class="text-texto-suave">—</span>@endif</td>
                        <td class="text-texto-suave">{{ $e->method ? __($metodos[$e->method] ?? $e->method) : '' }}{{ $e->correction_reason ? ' · '.__('corregido') : '' }}</td>
                        <td class="text-right whitespace-nowrap">
                            @if (! $e->status)
                                <x-ui.button tamano="sm" variante="secundario" wire:click="marcarRapido({{ $e->matricula }}, 'present')">{{ __('Presente') }}</x-ui.button>
                                @if ($abierta)<x-ui.button tamano="sm" variante="fantasma" wire:click="generarCodigo({{ $e->matricula }})">{{ __('Código') }}</x-ui.button>@endif
                            @else
                                <x-ui.button tamano="sm" variante="fantasma" wire:click="abrirCorreccion({{ $e->matricula }}, '{{ $e->status }}')">{{ __('Corregir') }}</x-ui.button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>

        <div class="flex flex-col gap-5">
            {{-- CP-RF19-02: con la sesión cerrada, la asistencia se puede cargar desde un archivo con carné y estado. --}}
            @if (! $abierta)
                <x-ui.card :titulo="__('Importar desde Excel o CSV')" :descripcion="__('Una fila por estudiante, con las columnas «Carné» y «Estado» (presente, tarde, ausente o justificada). Solo se aplican los matriculados en este grupo.')">
                    @if ($previa === null)
                        <form wire:submit="previsualizar" class="flex flex-col gap-3">
                            <x-ui.field :etiqueta="__('Archivo')" para="archivo" error="archivo"><input id="archivo" type="file" wire:model="archivo" accept=".csv,.xlsx,.txt" class="text-sm" /></x-ui.field>
                            <x-ui.button type="submit" variante="secundario" icono="arrow-up-tray" wire:loading.attr="disabled">{{ __('Ver vista previa') }}</x-ui.button>
                        </form>
                    @else
                        @php($cambian = collect($previa['aplicables'])->filter(fn ($f) => $f['anterior'] !== $f['estado']))
                        <p class="text-sm">{{ trans_choice('{1} 1 fila se aplica|[0,*] :count filas se aplican', count($previa['aplicables'])) }} · {{ trans_choice('{1} 1 cambia de estado|[0,*] :count cambian de estado', $cambian->count()) }}</p>
                        @foreach ($cambian->take(15) as $f)
                            <p class="py-0.5 text-xs">{{ $f['nombre'] }} · {{ $f['anterior'] ? __($estados[$f['anterior']]).' → ' : '' }}<x-ui.badge :color="$colores[$f['estado']]">{{ __($estados[$f['estado']]) }}</x-ui.badge></p>
                        @endforeach
                        @if ($previa['rechazadas'] !== [])
                            <x-ui.alert class="mt-3" tipo="aviso" :titulo="trans_choice('{1} 1 fila rechazada|[2,*] :count filas rechazadas', count($previa['rechazadas']))">
                                @foreach (array_slice($previa['rechazadas'], 0, 15) as $r)
                                    <p class="text-xs">{{ __('Fila :n', ['n' => $r['fila']]) }} · {{ $r['carne'] }} · {{ $r['motivo'] }}</p>
                                @endforeach
                            </x-ui.alert>
                        @endif
                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-ui.button wire:click="importar" :disabled="$previa['aplicables'] === []">{{ __('Confirmar importación') }}</x-ui.button>
                            <x-ui.button variante="fantasma" wire:click="descartarImportacion">{{ __('Descartar') }}</x-ui.button>
                        </div>
                    @endif
                </x-ui.card>
            @endif
            <x-ui.card :titulo="__('Rechazos de la sesión')">
                @forelse ($rechazos as $r)
                    <p class="py-1 text-sm">{{ $r->first_name }} {{ $r->last_name }} · <x-ui.badge color="rojo">{{ __($motivos[$r->result] ?? $r->result) }}</x-ui.badge>@if ($r->distance_m) <span class="text-xs text-texto-suave">{{ $r->distance_m }} m</span>@endif</p>
                @empty
                    <p class="text-sm text-texto-suave">{{ __('Sin rechazos.') }}</p>
                @endforelse
            </x-ui.card>
            <x-ui.card :titulo="__('Justificaciones pendientes')">
                @forelse ($justificaciones as $j)
                    <div class="border-b border-borde py-2 text-sm last:border-0" wire:key="just-{{ $j->id }}">
                        <p class="font-medium">{{ $j->first_name }} {{ $j->last_name }} · {{ \Illuminate\Support\Carbon::parse($j->session_date)->format('d/m') }}</p>
                        <p class="text-texto-suave">{{ $j->reason }}</p>
                        <div class="mt-1 flex gap-2"><x-ui.button tamano="sm" wire:click="revisar({{ $j->id }}, true)">{{ __('Aceptar') }}</x-ui.button><x-ui.button tamano="sm" variante="peligro" wire:click="revisar({{ $j->id }}, false)">{{ __('Rechazar') }}</x-ui.button></div>
                    </div>
                @empty
                    <p class="text-sm text-texto-suave">{{ __('No hay justificaciones por revisar.') }}</p>
                @endforelse
            </x-ui.card>
        </div>
    </div>

    <x-ui.modal nombre="codigo-temporal" :titulo="__('Código temporal')">
        <div class="flex flex-col items-center gap-3 py-2 text-center" x-data="{ s: 30 }" x-init="setInterval(() => s > 0 && s--, 1000)">
            <p class="text-sm text-texto-suave">{{ __('Díctelo al estudiante: solo sirve para él, una vez.') }}</p>
            <p class="font-mono text-5xl font-semibold tracking-[0.3em] text-marca-700">{{ $codigo }}</p>
            <p class="text-sm" :class="s < 10 ? 'text-red-700' : 'text-texto-suave'" x-text="s > 0 ? `{{ __('Vence en') }} ${s} s` : '{{ __('Vencido: genere otro.') }}'"></p>
        </div>
        <x-slot:pie><x-ui.button x-on:click="$dispatch('cerrar-modal', 'codigo-temporal')">{{ __('Listo') }}</x-ui.button></x-slot:pie>
    </x-ui.modal>

    <x-ui.modal nombre="corregir-asistencia" :titulo="__('Corregir registro')">
        <form wire:submit="corregir" id="form-corregir-asis" class="flex flex-col gap-4">
            <x-ui.field :etiqueta="__('Estado')" para="estadoAsis"><x-ui.select id="estadoAsis" wire:model="estado">@foreach ($estados as $v => $e)<option value="{{ $v }}">{{ __($e) }}</option>@endforeach</x-ui.select></x-ui.field>
            <x-ui.field :etiqueta="__('Motivo')" para="motivoAsis" error="motivo" requerido><x-ui.textarea id="motivoAsis" wire:model="motivo" /></x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'corregir-asistencia')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-corregir-asis">{{ __('Corregir') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>

    <x-ui.modal nombre="cancelar-clase" :titulo="__('Solicitar cancelación o cambio de modalidad')">
        <form wire:submit="solicitarCancelacion" id="form-cancelar" class="flex flex-col gap-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field :etiqueta="__('Fecha de la clase')" para="fechaCancelacion" error="fechaCancelacion"><x-ui.input id="fechaCancelacion" type="date" wire:model="fechaCancelacion" /></x-ui.field>
                <x-ui.field :etiqueta="__('Tipo')" para="tipoCancelacion"><x-ui.select id="tipoCancelacion" wire:model="tipoCancelacion"><option value="cancellation">{{ __('Cancelar la clase') }}</option><option value="modality_change">{{ __('Pasar a virtual') }}</option></x-ui.select></x-ui.field>
            </div>
            <x-ui.field :etiqueta="__('Motivo')" para="motivoCancelacion" error="motivoCancelacion" requerido><x-ui.textarea id="motivoCancelacion" wire:model="motivoCancelacion" /></x-ui.field>
            <p class="text-xs text-texto-suave">{{ __('La aprueba el Coordinador; esa sesión no cuenta para la asistencia.') }}</p>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'cancelar-clase')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-cancelar">{{ __('Enviar solicitud') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
