{{--
    Indicador de los pasos de <x-ui.formulario>: el recorrido, el paso actual y los que faltan.
    Los pasos ya recorridos se pueden pulsar para volver; para avanzar hay que pasar por «Siguiente», que valida.
--}}
@props(['pasos', 'paso'])

<nav aria-label="{{ __('Pasos del formulario') }}" {{ $attributes->class(['rounded-xl bg-white p-4 ring-1 ring-borde']) }}>
    <ol class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-2">
        @foreach ($pasos as $indice => $definicion)
            @php($numero = $indice + 1)
            @php($hecho = $numero < $paso)
            @php($actual = $numero === $paso)

            <li class="flex flex-1 items-center gap-3" @if ($actual) aria-current="step" @endif>
                <button
                    type="button"
                    @disabled(! $hecho)
                    @if ($hecho) wire:click="irAlPaso({{ $numero }})" @endif
                    class="group flex flex-1 items-center gap-3 text-left disabled:cursor-default"
                >
                    <span @class([
                        'grid size-8 shrink-0 place-items-center rounded-full text-sm font-semibold ring-1',
                        'bg-marca-600 text-white ring-marca-600' => $actual,
                        'bg-marca-50 text-marca-700 ring-marca-200 group-hover:bg-marca-100' => $hecho,
                        'bg-white text-texto-desactivado ring-borde' => ! $actual && ! $hecho,
                    ])>
                        @if ($hecho)
                            <x-ui.icon name="check-circle" class="size-5" />
                            <span class="sr-only">{{ __('Paso completado; vuelva para corregirlo') }}</span>
                        @else
                            {{ $numero }}
                        @endif
                    </span>

                    <span class="min-w-0">
                        <span @class(['block text-sm font-medium', 'text-texto' => $actual || $hecho, 'text-texto-suave' => ! $actual && ! $hecho])>
                            {{ $definicion['titulo'] }}
                        </span>
                        @if ($actual && ($definicion['descripcion'] ?? null))
                            <span class="block text-xs text-texto-suave">{{ $definicion['descripcion'] }}</span>
                        @endif
                    </span>
                </button>

                @unless ($loop->last)
                    <span class="hidden h-px flex-1 bg-borde sm:block" aria-hidden="true"></span>
                @endunless
            </li>
        @endforeach
    </ol>
</nav>
