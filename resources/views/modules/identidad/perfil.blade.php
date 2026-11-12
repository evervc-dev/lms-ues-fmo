{{-- Mi perfil (maqueta 16). --}}
<div class="flex flex-col gap-5">
    @include('modules.identidad.partials.perfil-encabezado')

    @if ($pestana === 'datos')
        <x-ui.card :titulo="__('Datos personales')" :descripcion="$datos['estudiante']
            ? __('Son de solo lectura: el nombre viene de Google y los datos académicos, del registro de la UES. Si algo está mal, comuníquese con la coordinación de su carrera.')
            : __('Son de solo lectura: el nombre y la foto vienen de su cuenta institucional de Google. Si algo está mal, comuníquese con el Administrador de la plataforma.')">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-texto-suave">{{ __('Nombre') }}</dt><dd class="font-medium">{{ $usuario->fullName() }}</dd></div>
                <div><dt class="text-texto-suave">{{ __('Correo institucional') }}</dt><dd class="font-medium">{{ $usuario->institutional_email }}</dd></div>
                @if ($datos['estudiante'])
                    <div><dt class="text-texto-suave">{{ __('Carné') }}</dt><dd class="font-medium">{{ $datos['estudiante']->carne }}</dd></div>
                    <div><dt class="text-texto-suave">{{ __('Carrera') }}</dt><dd class="font-medium">{{ $datos['estudiante']->carrera }} · {{ $datos['estudiante']->plan }}</dd></div>
                    <div><dt class="text-texto-suave">{{ __('Ciclo') }}</dt><dd class="font-medium">{{ $datos['estudiante']->ciclo }}</dd></div>
                @endif
                @if ($datos['departamento'])
                    <div><dt class="text-texto-suave">{{ __('Departamento') }}</dt><dd class="font-medium">{{ $datos['departamento'] }}</dd></div>
                @endif
                @if ($datos['coordina'])
                    <div><dt class="text-texto-suave">{{ __('Coordina') }}</dt><dd class="font-medium">{{ implode(', ', $datos['coordina']) }}</dd></div>
                @endif
                @if ($datos['dirige'])
                    <div><dt class="text-texto-suave">{{ __('Dirige') }}</dt><dd class="font-medium">{{ implode(', ', $datos['dirige']) }}</dd></div>
                @endif
            </dl>
        </x-ui.card>
    @elseif ($pestana === 'dispositivo')
        <x-ui.card :titulo="__('Dispositivo de asistencia')" :descripcion="__('Puede tener un solo teléfono registrado para marcar asistencia con QR.')">
            <div class="flex flex-col gap-4 text-sm" x-data x-on:dispositivo-registrado.window="localStorage.setItem('device_token', $event.detail.token)">
                @if ($dispositivo['activo'])
                    <x-ui.alert tipo="exito" :titulo="__('Teléfono registrado')">
                        {{ __('Registrado el :fecha · último uso :uso', ['fecha' => \Illuminate\Support\Carbon::parse($dispositivo['activo']->created_at)->format('d/m/Y'), 'uso' => $dispositivo['activo']->last_used_at ? \Illuminate\Support\Carbon::parse($dispositivo['activo']->last_used_at)->format('d/m/Y') : '—']) }}
                    </x-ui.alert>
                @else
                    <x-ui.alert tipo="aviso" :titulo="__('No tiene teléfono registrado')">
                        {{ __('Desde el teléfono con el que marcará asistencia, entre a esta pantalla y pulse «Registrar este teléfono». Use siempre el mismo navegador y no borre sus datos de navegación.') }}
                    </x-ui.alert>
                    @error('dispositivo')
                        <x-ui.alert tipo="error">{{ $message }}</x-ui.alert>
                    @enderror
                    <div>
                        <x-ui.button icono="device-phone-mobile" wire:click="registrarDispositivo">{{ __('Registrar este teléfono') }}</x-ui.button>
                        <p class="mt-1 text-xs text-texto-suave">{{ __('Solo puede tener un teléfono registrado. Para cambiarlo, solicítelo a Soporte.') }}</p>
                    </div>
                @endif

                @if ($dispositivo['solicitud'])
                    <div>
                        <p class="text-texto-suave">{{ __('Última solicitud de cambio') }}</p>
                        <p class="mt-1 flex items-center gap-2">
                            <x-ui.badge :color="['pending' => 'ambar', 'approved' => 'verde', 'rejected' => 'rojo'][$dispositivo['solicitud']->status]">{{ ['pending' => __('Pendiente'), 'approved' => __('Aprobada'), 'rejected' => __('Rechazada')][$dispositivo['solicitud']->status] }}</x-ui.badge>
                            <span>{{ $dispositivo['solicitud']->reason }}</span>
                        </p>
                    </div>
                @endif

                @can('dispositivo.solicitar-cambio')
                    <div><x-ui.button variante="secundario" x-on:click="$dispatch('abrir-modal', 'solicitar-cambio')">{{ __('Solicitar cambio de teléfono') }}</x-ui.button></div>
                @endcan
            </div>
        </x-ui.card>

        <x-ui.modal nombre="solicitar-cambio" :titulo="__('Solicitar cambio de teléfono')">
            <form wire:submit="solicitarCambio" id="form-cambio" class="flex flex-col gap-4">
                <x-ui.field :etiqueta="__('Motivo')" para="motivo" error="motivo" requerido>
                    <x-ui.textarea id="motivo" wire:model="motivo" :placeholder="__('Ej.: perdí mi teléfono')" />
                </x-ui.field>
                <x-ui.field :etiqueta="__('Justificación (si cambió hace menos de 30 días)')" para="justificacion" error="justificacion" :ayuda="__('PDF o imagen, máximo 5 MB.')">
                    <input id="justificacion" type="file" wire:model="justificacion" accept=".pdf,.jpg,.jpeg,.png" class="text-sm">
                </x-ui.field>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'solicitar-cambio')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-cambio">{{ __('Enviar solicitud') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @elseif ($pestana === 'notificaciones')
        <x-ui.card :titulo="__('Avisos por correo')" :descripcion="__('En la plataforma siempre verá todos los avisos. Aquí elige cuáles le llegan también a su correo institucional; solo aparecen los que su rol puede recibir.')">
            @if ($preferencias->isEmpty())
                <x-ui.empty-state icono="bell" :titulo="__('No hay avisos por correo que configurar')">
                    {{ __('Su rol no recibe avisos de los que se puedan apagar. Los avisos de la plataforma los seguirá viendo en la campana.') }}
                </x-ui.empty-state>
            @else
                <ul class="divide-y divide-borde">
                    @foreach ($preferencias as $preferencia)
                        <li wire:key="pref-{{ $preferencia->codigo }}" class="flex items-center justify-between gap-4 py-3">
                            <span class="text-sm text-texto">{{ $preferencia->descripcion }}</span>
                            @if ($preferencia->obligatorio)
                                <x-ui.badge color="gris">{{ __('Siempre por correo') }}</x-ui.badge>
                            @else
                                <button type="button" role="switch" aria-checked="{{ $preferencia->correo ? 'true' : 'false' }}" wire:click="alternarCorreo('{{ $preferencia->codigo }}')"
                                    aria-label="{{ __('Correo para: :aviso', ['aviso' => $preferencia->descripcion]) }}"
                                    @class(['relative inline-flex h-6 w-11 shrink-0 rounded-full transition', 'bg-marca-600' => $preferencia->correo, 'bg-zinc-300' => ! $preferencia->correo])>
                                    <span @class(['absolute top-0.5 size-5 rounded-full bg-white shadow transition', 'left-5' => $preferencia->correo, 'left-0.5' => ! $preferencia->correo])></span>
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    @elseif ($pestana === 'sesiones')
        <x-ui.card :titulo="__('Sesiones abiertas')" :descripcion="__('Su sesión se cierra sola tras 30 minutos sin actividad.')">
            <x-slot:acciones>
                @if ($sesiones['disponible'] && $sesiones['sesiones']->count() > 1)
                    <x-ui.button tamano="sm" variante="peligro" wire:click="cerrarOtras">{{ __('Cerrar las demás') }}</x-ui.button>
                @endif
            </x-slot:acciones>
            @if ($sesiones['disponible'])
                <x-ui.table :vacio="__('No hay sesiones abiertas.')">
                    <x-slot:cabecera><th>{{ __('Equipo') }}</th><th>{{ __('IP') }}</th><th>{{ __('Última actividad') }}</th></x-slot:cabecera>
                    @foreach ($sesiones['sesiones'] as $s)
                        <tr>
                            <td class="max-w-xs truncate">{{ \Illuminate\Support\Str::limit($s->user_agent, 60) }} @if ($s->id === session()->getId()) <x-ui.badge color="verde">{{ __('Esta sesión') }}</x-ui.badge> @endif</td>
                            <td class="font-mono text-xs">{{ $s->ip_address }}</td>
                            <td>{{ \Illuminate\Support\Carbon::createFromTimestamp($s->last_activity)->timezone('America/El_Salvador')->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @else
                <p class="text-sm text-texto-suave">{{ __('La lista de sesiones está disponible cuando las sesiones se guardan en la base de datos.') }}</p>
            @endif
        </x-ui.card>

        <x-ui.card :titulo="__('Últimos accesos')">
            <x-ui.table :vacio="__('Sin accesos registrados.')">
                <x-slot:cabecera><th>{{ __('Fecha') }}</th><th>{{ __('IP') }}</th><th>{{ __('Resultado') }}</th></x-slot:cabecera>
                @foreach ($sesiones['accesos'] as $a)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($a->occurred_at)->timezone('America/El_Salvador')->format('d/m/Y H:i') }}</td>
                        <td class="font-mono text-xs">{{ $a->ip_address }}</td>
                        <td><x-ui.badge :color="$a->result === 'success' ? 'verde' : 'rojo'">{{ $a->result === 'success' ? __('Correcto') : __('Rechazado') }}</x-ui.badge></td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
