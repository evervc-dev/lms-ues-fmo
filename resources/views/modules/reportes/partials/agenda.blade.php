{{--
    Agenda de los próximos 7 días (RF24, CA1): clases, entregas y videoconferencias en una sola línea de
    tiempo, agrupadas por día. Variables: $agenda (PanelService::agendaDelEstudiante).
--}}
@php($hoy = now()->timezone('America/El_Salvador')->toDateString())
@php($manana = now()->timezone('America/El_Salvador')->addDay()->toDateString())
@php($iconos = ['clase' => 'building-office', 'entrega' => 'clipboard-document-check', 'videoconferencia' => 'signal'])

@forelse ($agenda as $dia => $entradas)
    @php($fecha = \Illuminate\Support\Carbon::parse($dia))
    <section class="border-b border-borde py-3 first:pt-0 last:border-0 last:pb-0" wire:key="dia-{{ $dia }}">
        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-texto-suave">
            @if ($dia === $hoy)
                <span class="text-marca-700">{{ __('Hoy') }}</span> · {{ $fecha->translatedFormat('j \d\e F') }}
            @elseif ($dia === $manana)
                {{ __('Mañana') }} · {{ $fecha->translatedFormat('j \d\e F') }}
            @else
                {{ $fecha->translatedFormat('l j \d\e F') }}
            @endif
        </h3>

        <ul class="flex flex-col gap-1.5">
            @foreach ($entradas as $e)
                <li class="flex items-center gap-3 text-sm" wire:key="ag-{{ $dia }}-{{ $loop->index }}">
                    <span @class([
                        'grid size-7 shrink-0 place-items-center rounded-lg',
                        'bg-zinc-100 text-texto-suave' => $e->tipo === 'clase',
                        'bg-amber-50 text-amber-700' => $e->tipo === 'entrega',
                        'bg-marca-50 text-marca-700' => $e->tipo === 'videoconferencia',
                    ])>
                        <x-ui.icon :name="$iconos[$e->tipo]" class="size-4" />
                    </span>

                    <span class="w-12 shrink-0 tabular-nums text-xs text-texto-suave">{{ $e->cuando->format('H:i') }}</span>

                    <span class="min-w-0 flex-1">
                        @if ($e->url)
                            <a href="{{ $e->url }}" @if (! str_starts_with($e->url, 'http')) wire:navigate @endif
                                class="font-medium text-texto hover:text-marca-700 hover:underline">{{ $e->titulo }}</a>
                        @else
                            <span class="font-medium text-texto">{{ $e->titulo }}</span>
                        @endif
                        <span class="text-xs text-texto-suave">· {{ $e->code }}@if ($e->detalle) · {{ $e->detalle }}@endif</span>
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
@empty
    <x-ui.empty-state icono="calendar-days" :titulo="__('No tiene nada agendado esta semana')">
        {{ __('Aquí verá sus clases, las entregas que vencen y las videoconferencias de los próximos 7 días.') }}
    </x-ui.empty-state>
@endforelse
