{{-- Notificaciones (RF23). --}}
@php
    $iconos = [
        'grade.published' => 'chart-bar', 'deadline.upcoming' => 'clock', 'resource.published' => 'book-open',
        'academic_alert.opened' => 'exclamation-triangle', 'grade_review.requested' => 'clipboard-document-check',
        'grade_review.resolved' => 'check-circle', 'feedback.available' => 'clipboard-document-check',
        'extension.granted' => 'clock', 'assessment.created' => 'clipboard-document-list',
        'video_session.upcoming' => 'calendar', 'schedule.changed' => 'calendar-days',
        'enrollment.changed' => 'user-plus', 'grade_sheet.published' => 'clipboard-document-list',
        'grade_sheet.returned' => 'arrow-left', 'class.cancelled' => 'x-circle',
        'forum.reply' => 'rectangle-stack', 'mass_message.sent' => 'inbox-stack',
        'device_request.pending' => 'device-phone-mobile', 'device_request.resolved' => 'device-phone-mobile',
        'period.reopened' => 'lock-closed',
    ];
@endphp
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Notificaciones')" :subtitulo="trans_choice('{0} No tiene avisos sin leer.|{1} Tiene 1 aviso sin leer.|[2,*] Tiene :count avisos sin leer.', $pendientes)">
        <x-slot:acciones>
            <label class="flex items-center gap-2 text-sm text-texto"><input type="checkbox" wire:model.live="sinLeer" class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600"> {{ __('Solo sin leer') }}</label>
            @if ($pendientes > 0)
                <x-ui.button variante="secundario" tamano="sm" wire:click="marcarTodas">{{ __('Marcar todas como leídas') }}</x-ui.button>
            @endif
        </x-slot:acciones>
    </x-ui.encabezado>

    <x-ui.card>
        @if ($avisos->isEmpty())
            <x-ui.empty-state icono="bell" :titulo="__('No hay avisos')">{{ __('Aquí aparecerán las notas publicadas, los vencimientos y los materiales nuevos. Al abrir uno, la plataforma lo lleva a la pantalla del evento.') }}</x-ui.empty-state>
        @else
            <ul class="divide-y divide-borde">
                @foreach ($avisos as $aviso)
                    @php $destino = ! empty($aviso->data['url']); @endphp
                    <li wire:key="aviso-{{ $aviso->id }}" @class(['flex flex-wrap items-start gap-3 py-3 sm:flex-nowrap', 'opacity-70' => $aviso->read_at])>
                        <span @class(['mt-0.5 rounded-lg p-2', 'bg-marca-50 text-marca-600' => ! $aviso->read_at, 'bg-zinc-100 text-texto-suave' => $aviso->read_at])>
                            <x-ui.icon :name="$iconos[$aviso->event_code] ?? 'bell'" />
                        </span>
                        {{-- Abrir el aviso lo marca leído y lleva al evento; si no tiene pantalla, solo lo marca. --}}
                        <button type="button" wire:click="abrir('{{ $aviso->id }}')" class="-m-2 min-w-0 flex-1 rounded-lg p-2 text-left hover:bg-zinc-50"
                            aria-label="{{ $destino ? __('Abrir: :aviso', ['aviso' => $aviso->data['title'] ?? __('Aviso')]) : __('Marcar leído: :aviso', ['aviso' => $aviso->data['title'] ?? __('Aviso')]) }}">
                            <span @class(['block text-sm text-texto', 'font-semibold' => ! $aviso->read_at])>{{ $aviso->data['title'] ?? __('Aviso') }}</span>
                            @if (! empty($aviso->data['body']))
                                <span class="block text-sm text-texto-suave">{{ $aviso->data['body'] }}</span>
                            @endif
                            <span class="mt-1 block text-xs text-texto-suave">{{ $aviso->created_at->diffForHumans() }}</span>
                        </button>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($destino)
                                <x-ui.button tamano="sm" variante="secundario" icono="chevron-right" wire:click="abrir('{{ $aviso->id }}')">{{ __('Ir al evento') }}</x-ui.button>
                            @endif
                            @unless ($aviso->read_at)
                                <x-ui.button tamano="sm" variante="fantasma" wire:click="marcarLeida('{{ $aviso->id }}')">{{ __('Marcar leída') }}</x-ui.button>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ul>
            {{ $avisos->links() }}
        @endif
    </x-ui.card>
</div>
