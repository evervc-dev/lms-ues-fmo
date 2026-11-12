{{--
    Campana de la cabecera (RF23): contador y panel con los últimos 10 avisos.
    Llega por Reverb; el respaldo de 15 s (ADR-010, ADR-043) solo corre mientras el WebSocket no esté conectado,
    para no cargar al servidor con consultas que el push ya hace innecesarias (RNF03).
--}}
<div
    x-data="{
        conectado: false,
        sonar: false,
        init() {
            const conexion = window.Echo?.connector?.pusher?.connection;
            if (! conexion) return;
            this.conectado = conexion.state === 'connected';
            conexion.bind('state_change', (cambio) => this.conectado = cambio.current === 'connected');
        },
    }"
    x-on:campana-nueva.window="sonar = false; $nextTick(() => sonar = true)"
>
    <template x-if="! conectado"><span wire:poll.15s="refrescar" class="hidden" aria-hidden="true"></span></template>

    <x-ui.dropdown alinear="derecha" ancho="w-96 max-w-[calc(100vw-1.5rem)]">
        <x-slot:disparador>
            <span
                class="relative block rounded-lg p-2 text-texto-suave hover:bg-zinc-100"
                aria-label="{{ trans_choice('{0} Notificaciones|{1} Notificaciones: 1 sin leer|[2,*] Notificaciones: :count sin leer', $sinLeer) }}"
            >
                <span class="block origin-top" :class="sonar && 'motion-safe:animate-campana'" x-on:animationend="sonar = false">
                    <x-ui.icon name="bell" class="size-6" />
                </span>
                @if ($sinLeer > 0)
                    <span wire:key="contador-{{ $sinLeer }}" class="motion-safe:animate-numero absolute right-1 top-1 grid min-w-4 place-items-center rounded-full bg-marca-600 px-1 text-[10px] font-bold leading-4 text-white">{{ $sinLeer > 99 ? '99+' : $sinLeer }}</span>
                @endif
            </span>
        </x-slot:disparador>

        <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-3 pb-2 pt-1">
            <p class="text-sm font-semibold text-texto">{{ __('Notificaciones') }}</p>
            @if ($sinLeer > 0)
                <button type="button" wire:click="marcarTodas" class="text-xs font-medium text-marca-700 hover:underline">{{ __('Marcar todas como leídas') }}</button>
            @endif
        </div>

        <ul class="max-h-96 overflow-y-auto py-1">
            @forelse ($recientes as $aviso)
                <li wire:key="aviso-{{ $aviso->id }}">
                    <button
                        type="button"
                        role="menuitem"
                        wire:click="abrir('{{ $aviso->id }}')"
                        @class(['flex w-full flex-col gap-0.5 rounded-lg px-3 py-2 text-left hover:bg-zinc-50', 'bg-marca-50/50' => $aviso->read_at === null])
                    >
                        <span class="flex items-start justify-between gap-2">
                            <span @class(['text-sm text-texto', 'font-semibold' => $aviso->read_at === null])>{{ $aviso->data['title'] ?? '' }}</span>
                            @if ($aviso->read_at === null)
                                <span class="mt-1.5 size-2 shrink-0 rounded-full bg-marca-600" aria-hidden="true"></span>
                                <span class="sr-only">{{ __('Sin leer') }}</span>
                            @endif
                        </span>
                        @if (! empty($aviso->data['body']))
                            <span class="line-clamp-2 text-xs text-texto-suave">{{ $aviso->data['body'] }}</span>
                        @endif
                        <span class="flex items-center gap-1 text-xs text-texto-desactivado">
                            {{ $aviso->updated_at->diffForHumans() }}
                            @if (! empty($aviso->data['url']))
                                <span class="font-medium text-marca-700">· {{ __('Ir al evento') }}</span>
                                <x-ui.icon name="chevron-right" class="size-3 text-marca-700" />
                            @endif
                        </span>
                    </button>
                </li>
            @empty
                <li class="px-3 py-6 text-center text-sm text-texto-suave">{{ __('No tiene avisos.') }}</li>
            @endforelse
        </ul>

        <div class="border-t border-zinc-100 px-3 pt-2">
            <a href="{{ route('notificaciones') }}" wire:navigate class="text-sm font-medium text-marca-700 hover:underline">{{ __('Ver todas las notificaciones') }}</a>
        </div>
    </x-ui.dropdown>
</div>
