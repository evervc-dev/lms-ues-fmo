{{-- Sesión de asistencia (RF19, RF28, RF32, maqueta 21). --}}
@php
    $colores = ['present' => 'verde', 'late' => 'ambar', 'absent' => 'rojo', 'justified' => 'azul'];
    $motivos = \App\Modules\Asistencia\Services\DiagnosticoService::MOTIVOS;
    $abierta = $sesion->status === 'open';
    // ADR-079: la marca de salida se abre una vez, el mismo día y solo en presencial.
    $salida = $sesion->exit_opened_at !== null;
    $puedeAbrirSalida = ! $salida && $sesion->modality === 'in_person' && \Illuminate\Support\Carbon::parse($sesion->session_date)->isToday();
@endphp
<div class="flex flex-col gap-5" @if ($abierta) wire:poll.10s @endif>
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    @error('periodo')<x-ui.alert tipo="error">{{ $message }}</x-ui.alert>@enderror

    <x-ui.encabezado :titulo="__('Sesión del :f', ['f' => \Illuminate\Support\Carbon::parse($sesion->session_date)->format('d/m/Y')])"
        :subtitulo="($sesion->modality === 'virtual' ? __('Virtual') : __('Presencial')).' · '.__('vigencia :n min', ['n' => $sesion->qr_validity_minutes])">
        <x-slot:acciones>
            @if ($abierta)
                <x-ui.badge color="verde">{{ $salida ? __('Marca de salida abierta') : __('Abierta') }}</x-ui.badge>
                <span x-data="{ s: {{ $segundos }} }" x-init="setInterval(() => s > 0 && s--, 1000)" class="font-mono text-sm" x-text="`${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`"></span>
                <x-ui.button variante="secundario" icono="qr-code" :href="route('secciones.asistencia.proyectar', ['seccion' => $resumen['ulid'], 'sesion' => $sesion->ulid])" :navegar="false" target="_blank">{{ __('Proyectar QR') }}</x-ui.button>
                {{-- «Cerrar asistencia» y no «Cerrar sesión»: así se llama también salir de la cuenta (revisión UX del 22/11/2026). --}}
                <x-ui.button variante="peligro" wire:click="cerrar" wire:confirm="{{ $salida ? __('Quienes marcaron solo la entrada o solo la salida quedarán con tardanza. ¿Cerrar la marca de salida?') : __('Quienes no marcaron quedarán ausentes. ¿Cerrar la asistencia?') }}">{{ $salida ? __('Cerrar salida') : __('Cerrar asistencia') }}</x-ui.button>
            @else
                <x-ui.badge>{{ __('Cerrada') }}</x-ui.badge>
            @endif
            @if ($puedeAbrirSalida)
                <x-ui.button variante="secundario" icono="arrow-right-start-on-rectangle" x-on:click="$dispatch('abrir-modal', 'abrir-salida')">{{ __('Abrir marca de salida') }}</x-ui.button>
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
                <x-slot:cabecera><th>{{ __('Estudiante') }}</th><th>{{ __('Estado') }}</th><th>{{ __('Método') }}</th>@if ($salida)<th>{{ __('Salida') }}</th>@endif<th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
                @foreach ($estudiantes as $e)
                    <tr wire:key="asis-{{ $e->matricula }}">
                        <td>{{ $e->last_name }}, {{ $e->first_name }}<p class="text-xs text-texto-suave">{{ mb_strtoupper((string) $e->student_code) }}</p></td>
                        <td>@if ($e->status)<x-ui.badge :color="$colores[$e->status]">{{ __($estados[$e->status]) }}</x-ui.badge>@else<span class="text-texto-suave">—</span>@endif</td>
                        <td class="text-texto-suave">{{ $e->method ? __($metodos[$e->method] ?? $e->method) : '' }}{{ $e->correction_reason ? ' · '.__('corregido') : '' }}</td>
                        @if ($salida)
                            <td class="text-texto-suave">{{ $e->exit_recorded_at ? \Illuminate\Support\Carbon::parse($e->exit_recorded_at)->format('H:i') : '—' }}</td>
                        @endif
                        <td class="text-right whitespace-nowrap">
                            @if (! $e->status)
                                <x-ui.button tamano="sm" variante="secundario" wire:click="marcarRapido({{ $e->matricula }}, 'present')">{{ __('Presente') }}</x-ui.button>
                                @if ($abierta)<x-ui.button tamano="sm" variante="fantasma" wire:click="generarCodigo({{ $e->matricula }})">{{ __('Código') }}</x-ui.button>@endif
                            @else
                                @if ($abierta && $salida && ! $e->exit_recorded_at)<x-ui.button tamano="sm" variante="fantasma" wire:click="generarCodigo({{ $e->matricula }})">{{ __('Código') }}</x-ui.button>@endif
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
            <x-ui.card :titulo="__('Justificaciones de esta sesión')">
                @forelse ($justificaciones as $j)
                    <div class="border-b border-borde py-2 text-sm last:border-0" wire:key="just-{{ $j->id }}">
                        <p class="font-medium">{{ $j->first_name }} {{ $j->last_name }}</p>
                        <p class="text-texto-suave">{{ $j->reason }}</p>
                        <div class="mt-1 flex gap-2"><x-ui.button tamano="sm" wire:click="revisar({{ $j->id }}, true)">{{ __('Aceptar') }}</x-ui.button><x-ui.button tamano="sm" variante="peligro" wire:click="revisar({{ $j->id }}, false)">{{ __('Rechazar') }}</x-ui.button></div>
                    </div>
                @empty
                    <p class="text-sm text-texto-suave">{{ __('No hay justificaciones por revisar en esta sesión.') }}</p>
                @endforelse
            </x-ui.card>
        </div>
    </div>

    @if ($puedeAbrirSalida)
        <x-ui.modal nombre="abrir-salida" :titulo="__('Abrir marca de salida')">
            <form wire:submit="abrirSalida" id="form-abrir-salida" class="flex flex-col gap-4">
                <p class="text-sm text-texto-suave">{{ __('Los estudiantes vuelven a escanear el QR al final de la clase. Al cerrar: quien marcó entrada y salida conserva su estado; quien marcó solo una queda con tardanza; quien no marcó ninguna, ausente.') }}</p>
                @if ($abierta)<x-ui.alert tipo="aviso">{{ __('La marca de entrada sigue abierta: se cerrará primero y quienes no marcaron quedarán ausentes.') }}</x-ui.alert>@endif
                <x-ui.field :etiqueta="__('Vigencia (5 a 15 min)')" para="minutosSalida" error="minutosSalida"><x-ui.input id="minutosSalida" type="number" min="5" max="15" wire:model="minutosSalida" class="w-28" /></x-ui.field>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'abrir-salida')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-abrir-salida">{{ __('Abrir salida') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif

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

    <x-ui.modal nombre="cancelar-clase" :titulo="__('Solicitar cancelación o cambio de modalidad')" :subtitulo="__('La aprueba el Coordinador; al aprobarla se avisa al grupo teórico.')">
        <form wire:submit="solicitarCancelacion" id="form-cancelar" class="flex flex-col gap-4">
            <fieldset>
                <legend class="mb-2 text-sm font-medium text-zinc-800">{{ __('¿Qué necesita?') }}</legend>
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach (['cancellation' => [__('Cancelar la clase'), __('Ese día no hay clase y no cuenta para la asistencia.')], 'virtual' => [__('Pasar a virtual'), __('La asistencia se toma con el reporte de Meet, no con QR.')], 'in_person' => [__('Pasar a presencial'), __('La asistencia se toma con QR en el aula.')]] as $valor => [$titulo, $efecto])
                        <label wire:key="tipo-{{ $valor }}" @class(['flex cursor-pointer flex-col gap-0.5 rounded-lg p-3 ring-1 transition', 'bg-marca-50 ring-2 ring-marca-600' => $tipoCancelacion === $valor, 'ring-borde hover:bg-zinc-50' => $tipoCancelacion !== $valor])>
                            <input type="radio" wire:model.live="tipoCancelacion" value="{{ $valor }}" class="sr-only">
                            <span class="text-sm font-medium text-texto">{{ $titulo }}</span>
                            <span class="text-xs text-texto-suave">{{ $efecto }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <x-ui.field :etiqueta="__('Fecha de la clase')" para="fechaCancelacion" error="fechaCancelacion" :ayuda="__('De hoy en adelante, no solo la de esta sesión. Se propone mañana.')">
                <x-ui.input id="fechaCancelacion" type="date" wire:model="fechaCancelacion" min="{{ now()->toDateString() }}" class="sm:max-w-56" />
            </x-ui.field>
            <x-ui.field :etiqueta="__('Motivo')" para="motivoCancelacion" error="motivoCancelacion" requerido><x-ui.textarea id="motivoCancelacion" wire:model="motivoCancelacion" rows="3" :placeholder="__('Fumigación del edificio, asueto, actividad de la carrera…')" /></x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'cancelar-clase')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-cancelar">{{ __('Enviar solicitud') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
