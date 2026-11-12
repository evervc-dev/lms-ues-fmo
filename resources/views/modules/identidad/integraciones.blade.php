{{-- Integraciones (RNF09, maqueta 51). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Integraciones')" :subtitulo="__('La configuración que el LMS está usando. Los secretos nunca se muestran.')">
        <x-slot:acciones><x-ui.button variante="secundario" icono="queue-list" :href="route('horizon.index')" :navegar="false" target="_blank" rel="noopener">{{ __('Monitoreo técnico (Horizon)') }}</x-ui.button></x-slot:acciones>
    </x-ui.encabezado>
    {{-- QA sesión 4: la pantalla y el botón de Horizon resultaban ambiguos para el personal administrativo. --}}
    <x-ui.alert tipo="info" :titulo="__('Configuración técnica')">
        {{ __('Aquí puede revisar con qué credenciales se conecta el LMS a Google Workspace (inicio de sesión con la cuenta institucional) y al servidor de correo que envía los avisos. Se configuran en el servidor (archivo .env) con el equipo técnico; si algo aparece como pendiente, pídales que lo completen y lo verifiquen con «php artisan google:verificar».') }}
        {{ __('«Monitoreo técnico (Horizon)» abre en otra pestaña el panel de las tareas en segundo plano (correos, avisos, importaciones y reportes): sirve para revisar si alguna falló.') }}
    </x-ui.alert>
    <div class="grid gap-5 lg:grid-cols-3">
        @foreach ($integraciones as $clave => $i)
            <x-ui.card :titulo="__($i['titulo'])" wire:key="int-{{ $clave }}">
                <x-slot:acciones><x-ui.badge :color="$i['configurada'] ? 'verde' : 'ambar'">{{ $i['configurada'] ? __('Configurada') : __('Pendiente') }}</x-ui.badge></x-slot:acciones>
                <dl class="flex flex-col gap-3 text-sm">
                    @foreach ($i['campos'] as $c)
                        <div>
                            <dt class="font-medium text-texto">{{ $c['etiqueta'] }} <code class="ml-1 text-xs text-texto-suave">{{ $c['variable'] }}</code></dt>
                            <dd @class(['mt-0.5 break-all', 'text-texto-suave' => $c['ok'], 'text-amber-700' => ! $c['ok']])>{{ $c['valor'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-ui.card>
        @endforeach
    </div>
</div>
