<div class="flex flex-col gap-4">
    <x-ui.alert tipo="info" :titulo="__('Pantalla en construcción')">
        {{ __('Esta vista ya tiene su ruta, su permiso y su lugar en el menú. Se construye con su requisito siguiendo la maqueta.') }}
    </x-ui.alert>

    <x-ui.card :titulo="__('Ficha de la vista')">
        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-texto-suave">{{ __('Requisitos') }}</dt><dd class="font-medium">{{ $vista['rf'] ? implode(', ', $vista['rf']) : '—' }}</dd></div>
            <div><dt class="text-texto-suave">{{ __('Tarea en OpenProject') }}</dt><dd class="font-medium">{{ $vista['op'] ? 'OP#'.$vista['op'] : '—' }}</dd></div>
            <div><dt class="text-texto-suave">{{ __('Equipo · hito') }}</dt><dd class="font-medium">{{ $equipo ?? '—' }}{{ $hito ? ' · '.$hito : '' }}</dd></div>
            <div><dt class="text-texto-suave">{{ __('Permiso') }}</dt><dd class="font-mono text-xs">{{ $vista['permiso'] ?? __('sesión iniciada') }}</dd></div>
            <div class="sm:col-span-2 lg:col-span-4"><dt class="text-texto-suave">{{ __('Ruta') }}</dt><dd class="font-mono text-xs">{{ $nombre }} · {{ $vista['uri'] }} · app/Modules/{{ $vista['modulo'] }}</dd></div>
        </dl>
    </x-ui.card>

    @if ($maqueta)
        <x-ui.card :titulo="__('Maqueta de referencia')" :descripcion="$vista['maqueta']">
            <img src="{{ $maqueta }}" alt="{{ __('Maqueta: :nombre', ['nombre' => $vista['maqueta']]) }}" loading="lazy" class="w-full rounded-lg ring-1 ring-borde">
        </x-ui.card>
    @elseif (! isset($vista['maqueta']))
        <x-ui.empty-state icono="squares-2x2" :titulo="__('Sin maqueta todavía')">
            {{ __('Esta vista aún no tiene diseño en Stitch. Use la guía de estilo y el mapa de vistas como referencia.') }}
        </x-ui.empty-state>
    @endif
</div>
