{{-- Banco de preguntas (RF16, maqueta 32). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="$banco->name" :subtitulo="trans_choice('{1} 1 pregunta|[0,*] :count preguntas', $preguntas->count()).($banco->code ? ' · '.$banco->code : '')">
        <x-slot:acciones>
            <x-ui.button variante="secundario" icono="arrow-up-tray" wire:click="abrirImportacion">{{ __('Importar desde Excel') }}</x-ui.button>
            <x-ui.button icono="plus" :href="route('bancos-de-preguntas.pregunta', ['banco' => $banco->id])">{{ __('Nueva pregunta') }}</x-ui.button>
        </x-slot:acciones>
    </x-ui.encabezado>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,1fr)]">
        <div class="flex flex-col gap-3">
            @forelse ($preguntas as $p)
                <x-ui.card wire:key="preg-{{ $p->id }}">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-1">
                        <div class="flex flex-wrap items-center gap-1">
                            <x-ui.badge color="marca">{{ __($tipos[$p->question_type]) }}</x-ui.badge>
                            @if ($p->topic)<x-ui.badge>{{ $p->topic }}</x-ui.badge>@endif
                            <x-ui.badge color="verde">{{ __(':p pts', ['p' => rtrim(rtrim(number_format((float) ($p->points ?? 1.0), 2), '0'), '.')]) }}</x-ui.badge>
                        </div>
                        @if ($esDueno)
                            <button type="button" wire:click="editarPuntos({{ $p->id }}, {{ (float) ($p->points ?? 1.0) }})" class="text-xs font-medium text-marca-600 hover:underline">
                                {{ __('Editar puntos') }}
                            </button>
                        @endif
                    </div>
                    <p class="text-sm font-medium text-texto">{{ $loop->iteration }}. {{ $p->statement }}</p>
                    @if ($p->opciones->isNotEmpty())
                        <ul class="mt-2 flex flex-col gap-1 text-sm">@foreach ($p->opciones as $o)<li @class(['font-semibold text-emerald-700' => $o->is_correct, 'text-texto-suave' => ! $o->is_correct])>{{ $o->is_correct ? '✓' : '·' }} {{ $o->text }}</li>@endforeach</ul>
                    @elseif ($p->accepted_answers)
                        <p class="mt-2 text-sm text-emerald-700">{{ __('Respuestas aceptadas: :r', ['r' => implode(', ', json_decode($p->accepted_answers, true) ?: [])]) }}</p>
                    @endif
                    @if ($p->justification)<p class="mt-2 text-xs text-texto-suave">{{ $p->justification }}</p>@endif
                </x-ui.card>
            @empty
                <x-ui.card><x-ui.empty-state icono="rectangle-stack" :titulo="__('El banco está vacío')">{{ __('Agregue preguntas una a una o cárguelas todas de una vez con «Importar desde Excel».') }}</x-ui.empty-state></x-ui.card>
            @endforelse
        </div>

        @if ($esDueno)
            <x-ui.card :titulo="__('Compartir')">
                <form wire:submit="compartir" class="flex flex-col gap-3">
                    <x-ui.select wire:model="colega" :aria-label="__('Docente')"><option value="">{{ __('Elija un docente…') }}</option>@foreach ($colegas as $c)<option value="{{ $c->id }}">{{ $c->last_name }}, {{ $c->first_name }}</option>@endforeach</x-ui.select>
                    @error('colega')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                    <x-ui.button type="submit" variante="secundario" tamano="sm">{{ __('Compartir') }}</x-ui.button>
                </form>
                @if ($compartidoCon->isNotEmpty())<p class="mt-3 text-xs text-texto-suave">{{ __('Compartido con: :n', ['n' => $compartidoCon->map(fn ($u) => $u->first_name.' '.$u->last_name)->join(', ')]) }}</p>@endif
            </x-ui.card>
        @endif
    </div>

    <x-ui.modal nombre="importar-preguntas" :titulo="__('Importar preguntas desde Excel')">
        <div class="flex flex-col gap-4 text-sm">
            <ol class="flex list-decimal flex-col gap-1 pl-5 text-texto-suave">
                <li>{{ __('Descargue el formato. La hoja «Instrucciones» explica cada columna y trae un ejemplo de cada tipo.') }}</li>
                <li>{{ __('Escriba una pregunta por fila en la hoja «Preguntas», sin cambiar los encabezados.') }}</li>
                <li>{{ __('Suba el archivo: verá cuántas preguntas se importarán y qué filas hay que corregir antes.') }}</li>
            </ol>
            <div><x-ui.button tamano="sm" variante="secundario" icono="arrow-down-tray" :href="route('bancos-de-preguntas.formato')" :navegar="false">{{ __('Descargar formato (.xlsx)') }}</x-ui.button></div>

            <x-ui.field :etiqueta="__('Archivo (.xlsx o .csv, hasta 5 MB y :n preguntas)', ['n' => \App\Modules\Evaluaciones\Services\ImportacionPreguntasService::MAXIMO])" para="archivo-preguntas" error="archivo">
                <input id="archivo-preguntas" type="file" wire:model="archivo" accept=".xlsx,.csv" class="block w-full text-sm text-texto file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200">
            </x-ui.field>
            <p wire:loading wire:target="archivo" class="text-texto-suave">{{ __('Revisando el archivo…') }}</p>

            @if ($previa)
                <div wire:loading.remove wire:target="archivo" class="flex flex-col gap-3">
                    @if ($previa['total'] > 0)
                        <div class="rounded-lg bg-emerald-50 p-3 ring-1 ring-emerald-200">
                            <p class="font-medium text-emerald-900">{{ trans_choice('{1} 1 pregunta lista para importar.|[2,*] :count preguntas listas para importar.', $previa['total']) }}</p>
                            <p class="mt-1 text-xs text-emerald-800">{{ collect($previa['porTipo'])->map(fn ($n, $t) => __($tipos[$t]).': '.$n)->implode(' · ') }}</p>
                            <ul class="mt-2 flex list-disc flex-col gap-0.5 pl-5 text-xs text-emerald-900">
                                @foreach ($previa['muestra'] as $enunciado)<li class="truncate">{{ $enunciado }}</li>@endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($previa['errores'] !== [])
                        <div class="rounded-lg bg-red-50 p-3 ring-1 ring-red-200">
                            <p class="font-medium text-red-900">{{ trans_choice('{1} 1 fila con errores: corríjala y vuelva a subir el archivo.|[2,*] :count filas con errores: corríjalas y vuelva a subir el archivo.', count($previa['errores'])) }}</p>
                            <ul class="mt-2 flex max-h-40 list-disc flex-col gap-0.5 overflow-y-auto pl-5 text-xs text-red-900">
                                @foreach ($previa['errores'] as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </div>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'importar-preguntas')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button wire:click="importar" wire:loading.attr="disabled" :disabled="! $previa || $previa['total'] === 0 || $previa['errores'] !== []">
                {{ $previa && $previa['total'] > 0 && $previa['errores'] === [] ? trans_choice('{1} Importar 1 pregunta|[2,*] Importar :count preguntas', $previa['total']) : __('Importar') }}
            </x-ui.button>
        </x-slot:pie>
    </x-ui.modal>

    <x-ui.modal nombre="editar-puntos-modal" :titulo="__('Editar puntaje de la pregunta')" ancho="sm">
        <form wire:submit="guardarPuntos" id="form-editar-puntos" class="flex flex-col gap-4">
            <x-ui.field :etiqueta="__('Puntos (de 0.01 a 10)')" para="editandoPuntos" error="editandoPuntos" requerido>
                <x-ui.input id="editandoPuntos" type="number" step="0.01" min="0.01" max="10" wire:model="editandoPuntos" />
            </x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'editar-puntos-modal')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-editar-puntos">{{ __('Guardar') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
