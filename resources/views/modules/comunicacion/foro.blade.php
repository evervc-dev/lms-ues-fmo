{{-- Vista de sección › Foro (RF30, maqueta 40). --}}
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />
    <div class="flex justify-end"><x-ui.button icono="plus" x-on:click="$dispatch('abrir-modal', 'nuevo-hilo')">{{ __('Nuevo hilo') }}</x-ui.button></div>

    <x-ui.card :titulo="__('Foro de la sección')">
        @if ($hilos->isEmpty())
            <x-ui.empty-state icono="rectangle-stack" :titulo="__('Aún no hay hilos')">{{ __('Abra el primero con su duda o comentario.') }}</x-ui.empty-state>
        @else
            <ul class="divide-y divide-borde">
                @foreach ($hilos as $h)
                    <li wire:key="hilo-{{ $h->id }}">
                        <a href="{{ route('secciones.foro.hilo', ['seccion' => $seccion, 'hilo' => $h->id]) }}" wire:navigate class="flex items-start justify-between gap-3 py-3 hover:bg-zinc-50">
                            <span class="min-w-0">
                                <span class="flex flex-wrap items-center gap-2">
                                    @if ($h->is_pinned)<x-ui.badge color="marca">{{ __('Fijado') }}</x-ui.badge>@endif
                                    @if ($h->is_closed)<x-ui.badge>{{ __('Cerrado') }}</x-ui.badge>@endif
                                    <span class="font-medium text-texto">{{ $h->title }}</span>
                                </span>
                                <span class="mt-0.5 block text-xs text-texto-suave">{{ __(':autor · :fecha', ['autor' => $h->autor, 'fecha' => \Illuminate\Support\Carbon::parse($h->created_at)->format('d/m/Y')]) }}</span>
                            </span>
                            <span class="shrink-0 text-right text-xs text-texto-suave">{{ trans_choice('{1} 1 mensaje|[0,*] :count mensajes', $h->respuestas) }}@if ($h->ultima)<span class="block">{{ \Illuminate\Support\Carbon::parse($h->ultima)->diffForHumans() }}</span>@endif</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.modal nombre="nuevo-hilo" :titulo="__('Nuevo hilo')" ancho="lg">
        <form wire:submit="crear" id="form-hilo" class="flex flex-col gap-4">
            <x-ui.field :etiqueta="__('Título')" para="tituloHilo" error="titulo" requerido><x-ui.input id="tituloHilo" wire:model="titulo" /></x-ui.field>
            <x-ui.field :etiqueta="__('Mensaje')" para="mensajeHilo" error="mensaje" requerido><x-ui.textarea id="mensajeHilo" wire:model="mensaje" rows="5" /></x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'nuevo-hilo')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-hilo">{{ __('Publicar') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
