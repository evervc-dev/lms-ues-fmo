{{-- Integraciones (RNF09, maqueta 51). La ayuda técnica va aparte, para el equipo técnico (QA admin 21/11). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Integraciones')" :subtitulo="__('Servicios externos que usa el LMS. Los secretos nunca se muestran.')">
        <x-slot:acciones><x-ui.button variante="secundario" icono="queue-list" :href="route('horizon.index')" :navegar="false" target="_blank" rel="noopener">{{ __('Monitoreo técnico') }}</x-ui.button></x-slot:acciones>
    </x-ui.encabezado>

    <x-ui.alert tipo="info">
        {{ __('Aquí ve si el inicio de sesión con la cuenta institucional (Google) y el envío de avisos por correo están listos. Si algo aparece como «Pendiente» o «Revisar», contacte al equipo técnico: se configura en el servidor, no desde esta pantalla.') }}
    </x-ui.alert>

    <div class="grid gap-5 lg:grid-cols-3">
        @foreach ($integraciones as $clave => $i)
            <x-ui.card :titulo="__($i['titulo'])" wire:key="int-{{ $clave }}">
                <x-slot:acciones>
                    <x-ui.badge :color="['ok' => 'verde', 'revisar' => 'ambar', 'pendiente' => 'ambar'][$i['estado']]">{{ ['ok' => __('Configurada'), 'revisar' => __('Revisar'), 'pendiente' => __('Pendiente')][$i['estado']] }}</x-ui.badge>
                </x-slot:acciones>
                <div class="flex flex-col gap-3">
                    @if ($i['aviso'])
                        <x-ui.alert tipo="aviso">{{ $i['aviso'] }}</x-ui.alert>
                    @endif
                    <dl class="flex flex-col gap-3 text-sm">
                        @foreach ($i['campos'] as $c)
                            <div>
                                <dt class="font-medium text-texto">{{ $c['etiqueta'] }}</dt>
                                <dd @class(['mt-0.5 break-all', 'text-texto-suave' => $c['ok'], 'text-amber-700' => ! $c['ok']])>{{ $c['valor'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    @if ($clave === 'correo')
                        <x-ui.button variante="secundario" tamano="sm" wire:click="enviarPrueba" wire:loading.attr="disabled">{{ __('Enviar correo de prueba') }}</x-ui.button>
                        <p class="text-xs text-texto-suave">{{ __('Se envía a su correo institucional en el momento.') }}</p>
                    @endif
                </div>
            </x-ui.card>
        @endforeach
    </div>

    <details class="rounded-xl bg-white p-4 text-sm ring-1 ring-borde">
        <summary class="cursor-pointer font-medium text-texto">{{ __('Detalles técnicos (para el equipo técnico)') }}</summary>
        <div class="mt-3 flex flex-col gap-2 text-texto-suave">
            <p>{{ __('Los valores salen del archivo .env del servidor (config/services.php y config/mail.php). Después de cambiarlos, verifique Google con «php artisan google:verificar».') }}</p>
            <p>{{ __('«Monitoreo técnico» abre Horizon, el panel de las tareas en segundo plano (correos, avisos, importaciones y reportes), para revisar si alguna falló.') }}</p>
            <ul class="mt-1 flex flex-col gap-1">
                @foreach ($integraciones as $i)
                    @foreach ($i['campos'] as $c)
                        <li><code class="text-xs">{{ $c['variable'] }}</code> · {{ $c['etiqueta'] }}</li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    </details>
</div>
