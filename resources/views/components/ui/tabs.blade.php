{{--
    Pestañas con URL propia (navegan con wire:navigate). En el teléfono se desplazan en horizontal.
    <x-ui.tabs :pestanas="[['etiqueta' => 'Contenidos', 'url' => …, 'activa' => true], …]" />
--}}
@props(['pestanas' => [], 'etiqueta' => null])

<nav aria-label="{{ $etiqueta ?? __('Pestañas') }}" {{ $attributes->class(['-mb-px overflow-x-auto']) }}>
    <ul class="flex min-w-max gap-1 border-b border-borde">
        @foreach ($pestanas as $pestana)
            <li>
                <a
                    href="{{ $pestana['url'] }}"
                    wire:navigate
                    @if ($pestana['activa'] ?? false) aria-current="page" @endif
                    @class([
                        'inline-flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition',
                        'border-marca-600 text-marca-700' => $pestana['activa'] ?? false,
                        'border-transparent text-texto-suave hover:border-borde hover:text-texto' => ! ($pestana['activa'] ?? false),
                    ])
                >
                    {{ $pestana['etiqueta'] }}
                    @if (! empty($pestana['contador']))
                        <x-ui.badge color="marca">{{ $pestana['contador'] }}</x-ui.badge>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</nav>
