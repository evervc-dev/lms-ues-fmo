{{-- Un mensaje del foro y sus respuestas (recursivo). --}}
<li wire:key="msg-{{ $m->id }}" @class(['flex flex-col gap-2', 'opacity-60' => $m->is_hidden])>
    <div @class(['rounded-lg p-3 ring-1', 'bg-marca-50 ring-marca-200' => $m->es_docente, 'bg-white ring-borde' => ! $m->es_docente])>
        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-texto-suave">
            <span><span class="font-semibold text-texto">{{ $m->first_name }} {{ $m->last_name }}</span>
                @if ($m->es_docente)<x-ui.badge color="marca">{{ __('Docente') }}</x-ui.badge>@endif
                @if ($m->is_hidden)<x-ui.badge>{{ __('Oculto') }}</x-ui.badge>@endif
                · {{ \Illuminate\Support\Carbon::parse($m->created_at)->diffForHumans() }}</span>
            <span class="flex gap-2">
                @unless ($hilo->is_closed)<button type="button" wire:click="responderA({{ $m->id }})" class="hover:text-marca-700">{{ __('Responder') }}</button>@endunless
                @if ($modera)<button type="button" wire:click="moderar('ocultar', {{ $m->id }})" class="hover:text-red-700">{{ $m->is_hidden ? __('Mostrar') : __('Ocultar') }}</button>@endif
            </span>
        </div>
        <p class="mt-1 whitespace-pre-line text-sm text-texto">{{ strip_tags($m->body_html) }}</p>
    </div>
    @if (isset($hijos[$m->id]))
        <ul class="ml-4 flex flex-col gap-2 border-l-2 border-borde pl-4 sm:ml-6">
            @foreach ($hijos[$m->id] as $hijo)
                @include('modules.comunicacion.partials.mensaje', ['m' => $hijo])
            @endforeach
        </ul>
    @endif
</li>
