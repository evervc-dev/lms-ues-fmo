{{-- Prórrogas de una actividad (RF15, RF35). Asistente de tres pasos: 1) estudiantes, 2) tipo, fecha y motivo,
     3) vista previa para confirmar. Las prórrogas ya dadas se ven en el primer paso. --}}
@php
    $fecha = fn ($f) => \Illuminate\Support\Carbon::parse($f)->timezone('America/El_Salvador')->format('d/m/Y H:i');
    $elegidos = $estudiantes->whereIn('id', array_map('intval', $matriculas))->values();
    // Quienes ya tienen una prórroga de este tipo: al confirmar se les reemplaza.
    $conProrroga = $prorrogas->where('kind', $tipo)->pluck('matricula')->map(fn ($m) => (int) $m)->all();
    $form = 'form-prorroga-'.$datosActividad->ulid;
@endphp
<div>
    <x-ui.button tamano="sm" variante="secundario" icono="clock" wire:click="abrir">
        {{ __('Prórrogas') }}@if ($prorrogas->isNotEmpty()) ({{ $prorrogas->count() }})@endif
    </x-ui.button>

    {{-- Ancho xl y lista alta: con lg el buscador, la lista y la tabla quedaban apretados (pedido del usuario). --}}
    <x-ui.modal :nombre="'prorrogas-'.$datosActividad->ulid" :titulo="__('Prórrogas · :t', ['t' => $datosActividad->title])" ancho="xl">
        {{-- Intro avanza al paso siguiente y solo en el último confirma: así no se guarda nada a medio llenar. --}}
        <form wire:submit="{{ $paso === 3 ? 'conceder' : 'siguiente' }}" id="{{ $form }}" class="flex flex-col gap-4 text-sm">
            <x-ui.formulario.indicador :pasos="$this->pasos()" :paso="$paso" />

            @if ($paso === 1)
                {{-- Lista con buscador por nombre o carné (sin distinguir tildes) y casillas: un estudiante sin recorrer los
                     40, varios o todos los que se ven. --}}
                <div x-data="{
                        buscar: '',
                        normal(t) { return (t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); },
                        coincide(texto) { return this.normal(texto).includes(this.normal(this.buscar)); },
                        visibles() { return [...this.$refs.lista.querySelectorAll('label[data-texto]')].filter(l => this.coincide(l.dataset.texto)).map(l => Number(l.dataset.id)); },
                        todos() { const ids = this.visibles(); $wire.set('matriculas', [...new Set([...$wire.matriculas.map(Number), ...ids])]); },
                        ninguno() { $wire.set('matriculas', []); },
                    }" class="flex flex-col gap-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium text-texto">{{ __('Estudiantes') }} <span class="text-red-600">*</span></span>
                        <span class="text-xs text-texto-suave" x-text="$wire.matriculas.length === 1 ? @js(__('1 elegido')) : $wire.matriculas.length + ' ' + @js(__('elegidos'))"></span>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <x-ui.search class="flex-1" x-model="buscar" :placeholder="__('Buscar por nombre o carné…')" />
                        <div class="flex gap-2">
                            <x-ui.button type="button" tamano="sm" variante="secundario" x-on:click="todos()">{{ __('Elegir los que se ven') }}</x-ui.button>
                            <x-ui.button type="button" tamano="sm" variante="fantasma" x-on:click="ninguno()">{{ __('Quitar todos') }}</x-ui.button>
                        </div>
                    </div>
                    <div x-ref="lista" class="max-h-[min(22rem,40vh)] overflow-y-auto rounded-lg ring-1 ring-borde">
                        @foreach ($estudiantes as $est)
                            <label wire:key="prorroga-est-{{ $est->id }}" data-id="{{ $est->id }}" data-texto="{{ $est->nombre }} {{ $est->carne }}"
                                x-show="coincide($el.dataset.texto)"
                                class="flex cursor-pointer items-center gap-3 border-b border-borde px-4 py-2.5 last:border-0 hover:bg-zinc-50">
                                <input type="checkbox" value="{{ $est->id }}" wire:model="matriculas" class="size-4 rounded border-borde text-marca-600 focus:ring-marca-600">
                                <span class="min-w-0 flex-1 truncate text-texto">{{ $est->nombre }}</span>
                                @if ($est->carne)<span class="shrink-0 font-mono text-xs text-texto-suave">{{ $est->carne }}</span>@endif
                            </label>
                        @endforeach
                        <p x-show="visibles().length === 0" x-cloak class="px-3 py-4 text-center text-texto-suave">{{ __('Ningún estudiante coincide con la búsqueda.') }}</p>
                    </div>
                    @error('matriculas')<p class="text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                </div>

                @if ($prorrogas->isNotEmpty())
                    <details class="rounded-lg ring-1 ring-borde">
                        <summary class="cursor-pointer px-4 py-2.5 font-medium text-texto">{{ trans_choice('{1} 1 prórroga ya dada|[2,*] :count prórrogas ya dadas', $prorrogas->count()) }}</summary>
                        <div class="border-t border-borde p-3">
                            <x-ui.table>
                                <x-slot:cabecera><th>{{ __('Estudiante') }}</th><th>{{ __('Tipo') }}</th><th>{{ __('Hasta') }}</th><th>{{ __('Motivo') }}</th></x-slot:cabecera>
                                @foreach ($prorrogas as $p)
                                    <tr wire:key="prorroga-{{ $p->id }}"><td>{{ $p->nombre }}</td><td>{{ \App\Modules\Evaluaciones\Services\ProrrogaService::nombreTipo($p->kind, $datosActividad->assessment_type) }}</td><td>{{ $fecha($p->until) }}</td><td>{{ $p->reason }}</td></tr>
                                @endforeach
                            </x-ui.table>
                        </div>
                    </details>
                @endif
            @elseif ($paso === 2)
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field :etiqueta="__('Tipo')" para="prorroga-tipo" error="tipo" requerido>
                        <x-ui.select id="prorroga-tipo" wire:model="tipo">
                            @foreach ($tipos as $valor => $etiqueta)<option value="{{ $valor }}">{{ __($etiqueta) }}</option>@endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field :etiqueta="__('Hasta')" para="prorroga-hasta" error="hasta" requerido>
                        <x-ui.input id="prorroga-hasta" type="datetime-local" wire:model="hasta" />
                    </x-ui.field>
                    <x-ui.field class="sm:col-span-2" :etiqueta="__('Motivo')" para="prorroga-motivo" error="motivo" requerido :ayuda="__('Lo recibe cada estudiante en su aviso.')">
                        <x-ui.textarea id="prorroga-motivo" wire:model="motivo" rows="3" :placeholder="__('Ej.: constancia médica')" />
                    </x-ui.field>
                </div>
                <p class="text-xs text-texto-suave">{{ trans_choice('{1} Se aplicará al estudiante elegido.|[2,*] Se aplicará lo mismo a los :count estudiantes elegidos.', count($matriculas)) }} {{ __('La de entrega cuenta a tiempo, sin penalización.') }}</p>
            @else
                {{-- Vista previa: a quiénes, qué y hasta cuándo, y a quiénes se les reemplaza la que ya tenían. --}}
                <dl class="grid gap-3 rounded-lg bg-zinc-50 p-4 sm:grid-cols-3">
                    <div><dt class="text-xs text-texto-suave">{{ __('Tipo') }}</dt><dd class="font-medium text-texto">{{ $tipos[$tipo] ?? $tipo }}</dd></div>
                    <div><dt class="text-xs text-texto-suave">{{ __('Hasta') }}</dt><dd class="font-medium text-texto">{{ $hasta !== '' ? \Illuminate\Support\Carbon::parse($hasta, 'America/El_Salvador')->format('d/m/Y H:i') : '—' }}</dd></div>
                    <div><dt class="text-xs text-texto-suave">{{ __('Estudiantes') }}</dt><dd class="font-medium text-texto">{{ $elegidos->count() }}</dd></div>
                    <div class="sm:col-span-3"><dt class="text-xs text-texto-suave">{{ __('Motivo') }}</dt><dd class="text-texto">{{ $motivo }}</dd></div>
                </dl>
                @php($reemplazos = $elegidos->filter(fn ($e) => in_array((int) $e->id, $conProrroga, true))->count())
                @if ($reemplazos > 0)
                    <x-ui.alert tipo="aviso">{{ trans_choice('{1} 1 estudiante ya tenía una prórroga de este tipo: se reemplazará.|[2,*] :count estudiantes ya tenían una prórroga de este tipo: se reemplazará.', $reemplazos) }}</x-ui.alert>
                @endif
                <ul class="max-h-[min(18rem,35vh)] divide-y divide-borde overflow-y-auto rounded-lg ring-1 ring-borde">
                    @foreach ($elegidos as $est)
                        <li wire:key="prorroga-previa-{{ $est->id }}" class="flex items-center gap-3 px-4 py-2">
                            <span class="min-w-0 flex-1 truncate text-texto">{{ $est->nombre }}</span>
                            @if ($est->carne)<span class="shrink-0 font-mono text-xs text-texto-suave">{{ $est->carne }}</span>@endif
                            @if (in_array((int) $est->id, $conProrroga, true))<x-ui.badge color="ambar">{{ __('Reemplaza') }}</x-ui.badge>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </form>
        <x-slot:pie>
            @if ($paso > 1)
                <x-ui.button type="button" variante="secundario" icono="arrow-left" wire:click="anterior" class="mr-auto">{{ __('Atrás') }}</x-ui.button>
            @endif
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'prorrogas-{{ $datosActividad->ulid }}')">{{ __('Cerrar') }}</x-ui.button>
            @if ($paso < 3)
                <x-ui.button type="submit" :form="$form">{{ __('Siguiente') }}</x-ui.button>
            @else
                <x-ui.button type="submit" :form="$form" icono="check-circle">{{ trans_choice('{1} Confirmar prórroga|[2,*] Confirmar :count prórrogas', $elegidos->count()) }}</x-ui.button>
            @endif
        </x-slot:pie>
    </x-ui.modal>
</div>
