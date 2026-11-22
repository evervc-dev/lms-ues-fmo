{{-- Cambios de teléfono (RF27, maqueta 30). En el teléfono se ve la lista o el detalle, no los dos apilados. --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Cambios de teléfono')" :subtitulo="__('Cambios de teléfono de asistencia. La identidad ya la garantiza la sesión de Google del estudiante.')" />

    <x-ui.tabs :pestanas="[
        ['etiqueta' => __('Pendientes'), 'url' => route('dispositivos'), 'activa' => $estado === 'pending'],
        ['etiqueta' => __('Resueltas'), 'url' => route('dispositivos', ['estado' => 'resueltas']), 'activa' => $estado !== 'pending'],
    ]" />

    @if ($this->solicitudes->isEmpty())
        <x-ui.card><x-ui.empty-state icono="device-phone-mobile" :titulo="$estado === 'pending' ? __('No hay solicitudes pendientes') : __('Aún no hay solicitudes resueltas')" /></x-ui.card>
    @else
        <div class="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <x-ui.card :titulo="trans_choice('{1} 1 solicitud|[0,*] :count solicitudes', $this->solicitudes->count())" @class(['hidden lg:block' => $verDetalle])>
                <ul class="-mx-2 flex flex-col gap-1">
                    @foreach ($this->solicitudes as $s)
                        <li wire:key="sol-{{ $s->id }}">
                            <button type="button" wire:click="seleccionar({{ $s->id }})" @class([
                                'w-full rounded-lg px-3 py-2.5 text-left transition',
                                'bg-marca-50 ring-1 ring-marca-200' => $this->actual?->id === $s->id,
                                'hover:bg-zinc-50' => $this->actual?->id !== $s->id,
                            ])>
                                <span class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-texto">{{ $s->first_name }} {{ $s->last_name }}</span>
                                    @if ($s->status === 'pending')
                                        <span class="text-xs text-texto-suave">{{ \Illuminate\Support\Carbon::parse($s->created_at)->diffForHumans() }}</span>
                                    @else
                                        <x-ui.badge :color="$s->status === 'approved' ? 'verde' : 'rojo'">{{ $s->status === 'approved' ? __('Aprobada') : __('Rechazada') }}</x-ui.badge>
                                    @endif
                                </span>
                                <span class="mt-0.5 block truncate text-sm text-texto-suave">{{ $s->reason }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            @if ($actual = $this->actual)
                <div @class(['flex flex-col gap-3', 'hidden lg:flex' => ! $verDetalle])>
                <div class="lg:hidden">
                    <x-ui.button variante="secundario" tamano="sm" icono="arrow-left" wire:click="volver">{{ __('Volver a la lista') }}</x-ui.button>
                </div>
                <x-ui.card :titulo="$actual->first_name.' '.$actual->last_name" :descripcion="$actual->institutional_email.($actual->student_code ? ' · '.mb_strtoupper($actual->student_code) : '')">
                    @can('asistencia.diagnostico.ver')
                        <x-slot:acciones>
                            <x-ui.button tamano="sm" variante="secundario" icono="signal" :href="route('asistencia.diagnostico', ['buscar' => $actual->student_code ?: $actual->institutional_email])">{{ __('Ver su diagnóstico') }}</x-ui.button>
                        </x-slot:acciones>
                    @endcan
                    <div class="flex flex-col gap-4 text-sm">
                        <div>
                            <p class="text-texto-suave">{{ __('Motivo') }}</p>
                            <p class="mt-1 text-texto">{{ $actual->reason }}</p>
                        </div>
                        <div>
                            <p class="mb-2 text-texto-suave">{{ __('Historial de teléfonos') }}</p>
                            <x-ui.table :vacio="__('Sin teléfonos registrados.')">
                                <x-slot:cabecera><th>{{ __('Registrado') }}</th><th>{{ __('Último uso') }}</th><th>{{ __('Estado') }}</th></x-slot:cabecera>
                                @foreach ($historial['dispositivos'] as $d)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Carbon::parse($d->created_at)->format('d/m/Y') }}</td>
                                        <td>{{ $d->last_used_at ? \Illuminate\Support\Carbon::parse($d->last_used_at)->format('d/m/Y') : '—' }}</td>
                                        <td><x-ui.badge :color="$d->status === 'active' ? 'verde' : 'gris'">{{ $d->status === 'active' ? __('Activo') : __('Revocado') }}</x-ui.badge></td>
                                    </tr>
                                @endforeach
                            </x-ui.table>
                        </div>

                        @if ($historial['cambioReciente'])
                            <x-ui.alert tipo="aviso">
                                {{ __('Ya había cambiado de teléfono en los 30 días anteriores a esta solicitud.') }}
                                {{ $actual->justification_path ? __('Adjuntó una justificación.') : __('No adjuntó justificación.') }}
                            </x-ui.alert>
                        @endif

                        @if ($actual->status === 'pending')
                            @if ($rechazando)
                                {{-- El motivo se valida al confirmar; no se pide un «¿Continuar?» extra porque ya se pulsó «Rechazar». --}}
                                <x-ui.field :etiqueta="__('Motivo del rechazo')" para="motivoRechazo" error="motivoRechazo" requerido
                                    :ayuda="__('El estudiante lo recibe en su aviso.')">
                                    <x-ui.textarea id="motivoRechazo" wire:model="motivoRechazo" rows="2" />
                                </x-ui.field>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <x-ui.button variante="secundario" wire:click="cancelarRechazo">{{ __('Cancelar') }}</x-ui.button>
                                    <x-ui.button variante="peligro" wire:click="rechazar">{{ __('Confirmar rechazo') }}</x-ui.button>
                                </div>
                            @else
                                <div class="flex flex-wrap justify-end gap-2">
                                    <x-ui.button variante="secundario" wire:click="iniciarRechazo">{{ __('Rechazar') }}</x-ui.button>
                                    <x-ui.button icono="check-circle" wire:click="aprobar" wire:confirm="{{ __('Se revocará el teléfono actual del estudiante. ¿Continuar?') }}">{{ __('Aprobar') }}</x-ui.button>
                                </div>
                            @endif
                        @else
                            @if ($actual->status === 'rejected' && $actual->resolution_note)
                                <div>
                                    <p class="text-texto-suave">{{ __('Motivo del rechazo') }}</p>
                                    <p class="mt-1 text-texto">{{ $actual->resolution_note }}</p>
                                </div>
                            @endif
                            <p class="text-texto-suave">{{ __('Resuelta por :quien el :fecha.', ['quien' => $actual->resuelta_por, 'fecha' => \Illuminate\Support\Carbon::parse($actual->resolved_at)->format('d/m/Y H:i')]) }}</p>
                        @endif
                    </div>
                </x-ui.card>
                </div>
            @endif
        </div>
    @endif
</div>
