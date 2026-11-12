{{-- Solicitud de revisión de una calificación (RF35, ADR-040). Por pasos al resolver; entera al consultar. --}}
@php($num = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 2), '0'), '.'))
@php($s = $detalle['solicitud'])
@php($resuelve = $detalle['puedeResolver'])
@php($ajustados = collect($detalle['items'])->filter(fn ($i) => $decisiones[$i->id]['cambiar'] ?? false))

<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    {{-- Sin pasos (paso 0) cuando solo se consulta: se dibuja todo de una vez. --}}
    <x-ui.formulario
        accion="resolver"
        :volver="route('secciones.revisiones', ['seccion' => $seccion])"
        :enviar="$resuelve ? __('Resolver') : false"
        :pasos="$this->pasos()"
        :paso="$resuelve ? $paso : 0"
    >
        <x-ui.formulario.paso :numero="1">
            <x-ui.card
                :titulo="__('Solicitud de :nombre', ['nombre' => $s->first_name.' '.$s->last_name])"
                :descripcion="__(':t · nota actual: :n', ['t' => $s->title, 'n' => $num($s->score_before)])"
            >
                <x-slot:acciones><x-ui.badge>{{ __($estados[$s->status]) }}</x-ui.badge></x-slot:acciones>

                <div class="flex flex-col gap-4 text-sm">
                    @error('solicitud')<x-ui.alert tipo="error">{{ $message }}</x-ui.alert>@enderror
                    <p class="whitespace-pre-line rounded-md bg-zinc-50 p-3">{{ $s->reason }}</p>
                </div>
            </x-ui.card>
        </x-ui.formulario.paso>

        <x-ui.formulario.paso :numero="2">
            @foreach ($detalle['items'] as $item)
                <x-ui.card wire:key="res-{{ $item->id }}" :titulo="$item->etiqueta" :descripcion="__('Puntos actuales: :p', ['p' => $num($item->points_before)])">
                    <div class="flex flex-col gap-3 text-sm">
                        @if ($item->tipo === 'pregunta')
                            <p class="text-texto">{{ $item->statement }}</p>
                            <p><span class="text-texto-suave">{{ __('Respondió:') }}</span> {{ $item->respuestaEstudiante ?? '—' }}</p>
                            @if ($item->correcta)<p><span class="text-texto-suave">{{ __('Correcta:') }}</span> {{ $item->correcta }}</p>@endif
                        @endif
                        <p class="rounded-md bg-amber-50 p-2"><span class="text-texto-suave">{{ __('Estudiante:') }}</span> {{ $item->student_comment }}</p>

                        @if ($resuelve)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" id="ajustar-{{ $item->id }}" wire:model.live="decisiones.{{ $item->id }}.cambiar" class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600">
                                {{ __('Ajustar') }}
                            </label>
                            @if ($decisiones[$item->id]['cambiar'] ?? false)
                                <x-ui.field :etiqueta="match ($item->tipo) { 'pregunta' => __('Puntos de la pregunta (0 a 1)'), 'criterio' => __('Nivel'), default => __('Nota (0 a 10)') }" :para="'valor-'.$item->id" :error="'decisiones.'.$item->id.'.valor'" requerido>
                                    @if ($item->tipo === 'criterio')
                                        <x-ui.select :id="'valor-'.$item->id" wire:model="decisiones.{{ $item->id }}.valor"><option value="">{{ __('Elija nivel…') }}</option>@foreach ($item->niveles as $n)<option value="{{ $n->id }}">{{ $n->name }} · {{ $num($n->points) }}</option>@endforeach</x-ui.select>
                                    @else
                                        <x-ui.input :id="'valor-'.$item->id" type="number" step="0.01" min="0" :max="$item->tipo === 'pregunta' ? 1 : 10" wire:model="decisiones.{{ $item->id }}.valor" />
                                    @endif
                                </x-ui.field>
                            @endif
                            <x-ui.field :etiqueta="__('Respuesta al estudiante')" :para="'resp-'.$item->id" :error="'decisiones.'.$item->id.'.respuesta'" requerido>
                                <x-ui.textarea :id="'resp-'.$item->id" wire:model="decisiones.{{ $item->id }}.respuesta" rows="2" />
                            </x-ui.field>
                        @elseif ($item->decision)
                            <p>
                                <x-ui.badge :color="$item->decision === 'changed' ? 'verde' : 'gris'">{{ $item->decision === 'changed' ? __('Ajustado: :a → :b', ['a' => $num($item->points_before), 'b' => $num($item->points_after)]) : __('Se mantiene') }}</x-ui.badge>
                                {{ $item->teacher_response }}
                            </p>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach

            @unless ($resuelve || $s->status !== 'pending')
                <x-ui.alert tipo="info">{{ __('Solo el docente de la sección resuelve, y antes de publicar el acta.') }}</x-ui.alert>
            @endunless
        </x-ui.formulario.paso>

        @if ($resuelve)
            <x-ui.formulario.paso :numero="3">
                <x-ui.card :titulo="__('Revisar y resolver')" :descripcion="__('Si algo no está bien, vuelva al paso correspondiente desde el indicador de arriba.')">
                    <dl class="flex flex-col gap-4 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-texto-suave">{{ __('Ítems que cambian') }}</dt>
                            <dd class="mt-1">
                                @forelse ($ajustados as $item)
                                    <p wire:key="resumen-{{ $item->id }}" class="text-texto">
                                        {{ $item->etiqueta }}: {{ $num($item->points_before) }} → {{ $num($decisiones[$item->id]['valor'] ?? null) }}
                                    </p>
                                @empty
                                    <p class="text-texto">{{ __('Ninguno: la nota se mantiene y solo se le responde al estudiante.') }}</p>
                                @endforelse
                            </dd>
                        </div>

                        <x-ui.field :etiqueta="__('Comentario general (opcional)')" para="respuesta" error="respuesta">
                            <x-ui.textarea id="respuesta" wire:model="respuesta" rows="2" />
                        </x-ui.field>
                    </dl>

                    <x-ui.alert class="mt-4" tipo="info">{{ __('Al resolver se recalcula la nota y se avisa al estudiante.') }}</x-ui.alert>
                </x-ui.card>
            </x-ui.formulario.paso>
        @endif
    </x-ui.formulario>
</div>
