{{-- Vista de sección › Notas (RF17, RF18, maqueta 20). --}}
@php($fmt = fn ($n) => $n === null ? '—' : number_format((float) $n, 1))
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    @if ($esEstudiante)
        <div class="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <x-ui.card :titulo="__('Mi boleta')" :descripcion="__('Solo cuentan las notas publicadas. Las actividades sin nota cuentan como 0.')">
                @foreach ($boleta['computos'] as $c)
                    {{-- Un cómputo sin notas en este grupo conserva el del grupo anterior (ADR-062). --}}
                    @php($heredado = $boleta['heredadas'][(int) $c->number] ?? null)
                    <div class="mb-4">
                        <div class="mb-2 flex items-center justify-between gap-2 text-sm font-semibold">
                            <span>{{ $c->name }} · {{ rtrim(rtrim(number_format((float) $c->weight_percent, 2, '.', ''), '0'), '.') }} %
                                @if ($heredado)<x-ui.badge color="azul">{{ __('del grupo :g', ['g' => $heredado->grupo]) }}</x-ui.badge>@endif</span>
                            <span>{{ $fmt($servicio->notaComputo($c, $boleta['notas'], true, $boleta['heredadas'])) }}</span>
                        </div>
                        @if ($heredado)
                            <p class="text-xs text-texto-suave">{{ __('Esta nota la trae del grupo :g, donde cursó este cómputo antes del cambio de grupo.', ['g' => $heredado->grupo]) }}</p>
                        @endif
                        <ul class="divide-y divide-borde text-sm">
                            @foreach ($c->items as $i)
                                <li class="flex justify-between py-1.5"><span>{{ $i->title }} <span class="text-xs text-texto-suave">({{ rtrim(rtrim(number_format((float) $i->weight_percent, 2, '.', ''), '0'), '.') }} %)</span></span>
                                    <span>{{ $i->is_published ? $fmt($boleta['notas'][$i->id]->score ?? 0) : __('Pendiente') }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </x-ui.card>
            <div class="flex flex-col gap-5">
                <x-ui.card>
                    <p class="text-xs uppercase text-texto-suave">{{ __('Nota acumulada') }}</p>
                    <p @class(['text-4xl font-semibold', 'text-emerald-700' => $boleta['final'] >= $minima, 'text-red-700' => $boleta['final'] < $minima])>{{ $fmt($boleta['final']) }}</p>
                    <p class="text-xs text-texto-suave">{{ __('Se aprueba con :m.', ['m' => number_format($minima, 1)]) }}</p>
                </x-ui.card>
                <x-ui.card :titulo="__('Por unidad')">
                    @forelse ($boleta['porUnidad'] as $unidad => $nota)
                        <div class="mb-2"><p class="text-sm">{{ $unidad }}</p><x-ui.progress :valor="$nota * 10" :mostrar="false" :tono="$nota >= $minima ? 'exito' : 'error'" /><p class="text-xs text-texto-suave">{{ $fmt($nota) }}</p></div>
                    @empty
                        <p class="text-sm text-texto-suave">{{ __('Aún no hay notas por unidad.') }}</p>
                    @endforelse
                </x-ui.card>
            </div>
        </div>
    @else
        @if ($problemasPesos)
            <x-ui.alert tipo="aviso" :titulo="__('Revise los pesos: no podrá publicar el acta hasta que sumen 100 %')">
                <ul class="list-disc pl-4">
                    @foreach ($problemasPesos as $problema)
                        <li>{{ $problema }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('secciones.pesos', ['seccion' => $seccion]) }}" wire:navigate class="mt-2 inline-block font-semibold underline hover:no-underline">{{ __('Ajustar los pesos') }}</a>
            </x-ui.alert>
        @endif
        <x-ui.card :titulo="__('Libro de calificaciones')" :descripcion="$libro['cerrado'] ? __('Período cerrado: solo el Coordinador corrige, con motivo.') : __('Nota final = suma de los cómputos según su peso. Sin nota cuenta 0.')">
            <x-slot:acciones>
                @if ($puedeMensajear)
                    <x-ui.button tamano="sm" variante="secundario" :href="route('secciones.mensajes', ['seccion' => $seccion])">{{ __('Mensajes enviados') }}</x-ui.button>
                    <x-ui.button tamano="sm" variante="secundario" icono="inbox-stack" :href="route('secciones.mensajes.crear', ['seccion' => $seccion])">{{ __('Mensajear a estudiantes que…') }}</x-ui.button>
                @endif
                @if ($gestiona)<x-ui.button tamano="sm" variante="secundario" :href="route('secciones.pesos', ['seccion' => $seccion])">{{ __('Pesos') }}</x-ui.button>@endif
                @if ($gestiona)<x-ui.button tamano="sm" variante="secundario" :href="route('secciones.acta', ['seccion' => $seccion])">{{ __('Acta') }}</x-ui.button>@endif
                <x-ui.exportar tamano="sm" />
            </x-slot:acciones>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 text-xs text-texto-suave">
                        <tr><th rowspan="2" class="sticky left-0 bg-zinc-50 p-2 text-left">{{ __('Estudiante') }}</th>
                            @foreach ($libro['computos'] as $c)<th colspan="{{ max(1, $c->items->count()) }}" class="border-l border-borde p-2">{{ $c->name }} ({{ rtrim(rtrim(number_format((float) $c->weight_percent, 2, '.', ''), '0'), '.') }} %)</th>@endforeach
                            <th rowspan="2" class="border-l border-borde p-2">{{ __('Final') }}</th></tr>
                        <tr>@foreach ($libro['computos'] as $c)
                                @forelse ($c->items as $i)
                                    <th class="border-l border-borde p-2 font-normal"><span class="block max-w-28 truncate" title="{{ $i->title }}">{{ $i->title }}</span>
                                        @if ($gestiona && ! $i->is_published)<button type="button" wire:click="publicar({{ $i->id }})" wire:confirm="{{ __('¿Publicar las notas de :t? Los estudiantes las verán de inmediato.', ['t' => $i->title]) }}" class="text-marca-700 hover:underline">{{ __('Publicar') }}</button>@elseif ($i->is_published)<span class="text-emerald-700">✓</span>@endif</th>
                                @empty<th class="border-l border-borde p-2 font-normal">—</th>@endforelse
                            @endforeach</tr>
                    </thead>
                    <tbody class="divide-y divide-borde">
                        @forelse ($libro['filas'] as $f)
                            <tr wire:key="fila-{{ $f->id }}">
                                <td class="sticky left-0 whitespace-nowrap bg-white p-2">{{ $f->last_name }}, {{ $f->first_name }}</td>
                                @php($conHerencia = false)
                                @foreach ($libro['computos'] as $c)
                                    {{-- Cómputo heredado del grupo anterior (ADR-062): histórico de solo lectura, no se califica aquí. --}}
                                    @php($heredado = $f->heredadas[(int) $c->number] ?? null)
                                    @if ($heredado)
                                        @php($conHerencia = true)
                                        <td colspan="{{ max(1, $c->items->count()) }}" class="border-l border-borde bg-sky-50/60 p-2 text-center" title="{{ __('Nota del grupo :g, traída por el cambio de grupo. No se edita en esta sección.', ['g' => $heredado->grupo]) }}">
                                            <span class="font-semibold">{{ $fmt($heredado->nota) }}</span>
                                            <x-ui.badge color="azul" class="ml-1">{{ __('del grupo :g', ['g' => $heredado->grupo]) }}</x-ui.badge>
                                        </td>
                                    @else
                                        @forelse ($c->items as $i)
                                            @php($nota = $f->notas[$i->id] ?? null)
                                            <td class="border-l border-borde p-2 text-center">
                                                @if ($nota && $corrige && $libro['cerrado'])<button type="button" wire:click="abrirCorreccion({{ $nota->id }})" class="underline decoration-dotted hover:text-marca-700">{{ $fmt($nota->score) }}</button>
                                                @else<span @class(['text-texto-suave' => $nota && $nota->status === 'draft'])>{{ $fmt($nota?->score) }}</span>@endif
                                            </td>
                                        @empty<td class="border-l border-borde p-2"></td>@endforelse
                                    @endif
                                @endforeach
                                <td @class(['border-l border-borde p-2 text-center font-semibold', 'text-red-700' => $f->final < $minima])>{{ $fmt($f->final) }}
                                    @if ($conHerencia)<x-ui.badge color="azul" class="ml-1 font-normal" :title="__('Incluye cómputos traídos del grupo anterior.')">{{ __('con herencia') }}</x-ui.badge>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="100" class="p-4 text-center text-texto-suave">{{ __('No hay estudiantes activos.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-texto-suave">{{ __('En gris: notas en borrador (aún no publicadas). Califique desde cada actividad.') }}
                {{ __('En azul: cómputo traído del grupo anterior por un cambio de grupo; se muestra como histórico y no se edita en esta sección.') }}</p>
        </x-ui.card>
    @endif

    @if ($corrige)
        <x-ui.modal nombre="corregir-nota" :titulo="__('Corregir nota (período cerrado)')">
            <form wire:submit="corregir" id="form-corregir" class="flex flex-col gap-4">
                <x-ui.field :etiqueta="__('Nota nueva')" para="nuevaNota" error="nuevaNota" requerido><x-ui.input id="nuevaNota" type="number" step="0.1" min="0" max="10" wire:model="nuevaNota" /></x-ui.field>
                <x-ui.field :etiqueta="__('Motivo')" para="motivoNota" error="motivo" requerido><x-ui.textarea id="motivoNota" wire:model="motivo" /></x-ui.field>
                <p class="text-xs text-texto-suave">{{ __('La corrección queda en la auditoría con el valor anterior y el nuevo.') }}</p>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'corregir-nota')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-corregir">{{ __('Corregir') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif
</div>
