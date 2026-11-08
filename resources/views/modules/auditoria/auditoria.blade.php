{{-- Auditoría (RF05, maqueta 28). --}}
@php
    $acciones = ['insert' => __('Creación'), 'update' => __('Cambio'), 'delete' => __('Eliminación')];
@endphp
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Auditoría')" :subtitulo="$subtitulo.' '.__('Los registros no se pueden modificar.')">
        <x-slot:acciones><x-ui.exportar :pdf="false" /></x-slot:acciones>
    </x-ui.encabezado>

    <x-ui.card ajustar>
        <div class="mb-4 grid gap-3 md:grid-cols-5">
            <x-ui.search class="md:col-span-2" wire:model.live.debounce.300ms="buscar" :placeholder="__('Buscar por usuario o evento…')" />
            <x-ui.select wire:model.live="entidad" :aria-label="__('Entidad')">
                <option value="">{{ __('Todas las entidades') }}</option>
                @foreach ($entidades as $opcion)
                    <option value="{{ $opcion }}">{{ $opcion }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select wire:model.live="accion" :aria-label="__('Acción')">
                <option value="">{{ __('Todas las acciones') }}</option>
                @foreach ($acciones as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </x-ui.select>
            <div class="grid grid-cols-2 gap-2">
                <x-ui.input type="date" wire:model.live="desde" :aria-label="__('Desde')" />
                <x-ui.input type="date" wire:model.live="hasta" :aria-label="__('Hasta')" />
            </div>
        </div>

        <x-ui.table ajustar :vacio="__('No hay eventos con esos filtros.')">
            <x-slot:cabecera><th>{{ __('Fecha') }}</th><th>{{ __('Usuario') }}</th><th>{{ __('Qué pasó') }}</th><th>{{ __('Sobre') }}</th><th>{{ __('Acción') }}</th><th></th></x-slot:cabecera>
            @foreach ($this->eventos as $e)
                <tr wire:key="audit-{{ $e->id }}">
                    <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($e->occurred_at)->timezone('America/El_Salvador')->format('d/m/Y H:i') }}</td>
                    <td>{{ $e->actor ?? __('Sistema') }}</td>
                    <td title="{{ $e->event_type ?: __('Sin evento de dominio') }}">{{ \App\Modules\Auditoria\Services\AuditoriaService::evento($e->event_type) }}</td>
                    <td title="{{ $e->entity_type }}">{{ \App\Modules\Auditoria\Services\AuditoriaService::entidad($e->entity_type) }}{{ $e->entity_id ? ' #'.$e->entity_id : '' }}</td>
                    <td><x-ui.badge :color="['insert' => 'verde', 'update' => 'azul', 'delete' => 'rojo'][$e->action] ?? 'gris'">{{ $acciones[$e->action] ?? $e->action }}</x-ui.badge></td>
                    <td class="text-right"><x-ui.button tamano="sm" variante="fantasma" wire:click="ver({{ $e->id }})">{{ __('Ver') }}</x-ui.button></td>
                </tr>
            @endforeach
        </x-ui.table>
        {{ $this->eventos->links() }}
    </x-ui.card>

    <x-ui.modal nombre="detalle-auditoria" :titulo="__('Detalle del evento')" ancho="lg">
        @if ($evento)
            @php($servicio = \App\Modules\Auditoria\Services\AuditoriaService::class)
            @php($cambios = $servicio::cambios($evento->old_values, $evento->new_values))
            <p class="mb-3 text-base font-semibold">{{ $servicio::evento($evento->event_type) }}</p>
            <dl class="mb-4 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-texto-suave">{{ __('Fecha') }}</dt><dd>{{ \Illuminate\Support\Carbon::parse($evento->occurred_at)->timezone('America/El_Salvador')->format('d/m/Y H:i:s') }}</dd></div>
                <div><dt class="text-texto-suave">{{ __('Usuario') }}</dt><dd>{{ $evento->actor ?? __('Sistema') }}</dd></div>
                <div><dt class="text-texto-suave">{{ __('Sobre') }}</dt><dd>{{ $servicio::entidad($evento->entity_type) }}{{ $evento->entity_id ? ' #'.$evento->entity_id : '' }}</dd></div>
                <div><dt class="text-texto-suave">{{ __('Motivo') }}</dt><dd>{{ $evento->reason ?: '—' }}</dd></div>
                <div><dt class="text-texto-suave">{{ __('IP') }}</dt><dd class="font-mono text-xs">{{ $evento->ip_address ?: '—' }}</dd></div>
            </dl>

            <p class="mb-2 text-xs font-semibold uppercase text-texto-suave">{{ __('Qué cambió') }}</p>
            @if ($cambios)
                <x-ui.table ajustar>
                    <x-slot:cabecera><th>{{ __('Campo') }}</th><th>{{ __('Antes') }}</th><th>{{ __('Después') }}</th></x-slot:cabecera>
                    @foreach ($cambios as $c)
                        <tr><td class="font-medium">{{ $c['campo'] }}</td><td class="text-texto-suave">{{ $c['antes'] }}</td><td class="font-semibold">{{ $c['despues'] }}</td></tr>
                    @endforeach
                </x-ui.table>
            @else
                <p class="text-sm text-texto-suave">{{ __('El evento no cambió ningún campo que se audite.') }}</p>
            @endif

            {{-- La fila cruda sigue disponible para Soporte y para el Administrador, pero ya no es lo primero que se ve. --}}
            <details class="mt-4">
                <summary class="cursor-pointer text-xs font-semibold uppercase text-texto-suave">{{ __('Ver el registro técnico') }}</summary>
                <p class="mt-2 text-xs text-texto-suave">{{ __('Evento') }}: <span class="font-mono">{{ $evento->event_type ?: '—' }}</span> · {{ __('Tabla') }}: <span class="font-mono">{{ $evento->entity_type }}</span></p>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <div><p class="mb-1 text-xs font-semibold uppercase text-texto-suave">{{ __('Antes') }}</p><pre class="max-h-64 overflow-auto rounded-lg bg-zinc-50 p-3 text-xs">{{ $evento->old_values ? json_encode(json_decode($evento->old_values), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—' }}</pre></div>
                    <div><p class="mb-1 text-xs font-semibold uppercase text-texto-suave">{{ __('Después') }}</p><pre class="max-h-64 overflow-auto rounded-lg bg-zinc-50 p-3 text-xs">{{ $evento->new_values ? json_encode(json_decode($evento->new_values), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—' }}</pre></div>
                </div>
            </details>
        @endif
    </x-ui.modal>
</div>
