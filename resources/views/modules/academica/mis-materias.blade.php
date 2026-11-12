{{-- Mis materias (RF29, RF18, maqueta 05). --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Mis materias')" :subtitulo="__('Materias del período en curso con su avance.')">
        <x-slot:acciones><x-ui.button icono="plus" :href="route('inscripcion')">{{ __('Inscribir materias') }}</x-ui.button></x-slot:acciones>
    </x-ui.encabezado>

    @if ($materias->isEmpty())
        <x-ui.card><x-ui.empty-state icono="book-open" :titulo="__('Aún no tiene materias')">{{ __('Elija sus materias en «Inscripción de materias» e inscríbase con la clave que le dio su docente o su coordinador.') }}
            <x-slot:acciones><x-ui.button icono="plus" :href="route('inscripcion')">{{ __('Inscribir materias') }}</x-ui.button></x-slot:acciones>
        </x-ui.empty-state></x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($materias as $m)
                <div wire:key="materia-{{ $m->id }}" class="flex flex-col rounded-xl bg-white ring-1 ring-borde transition hover:ring-marca-300">
                    <a href="{{ route('secciones.contenidos', ['seccion' => $m->ulid]) }}" wire:navigate class="flex flex-1 flex-col gap-3 p-4">
                        <div class="flex gap-1"><x-ui.badge color="marca">{{ $m->code }}</x-ui.badge><x-ui.badge>{{ __('Grupo :g', ['g' => $m->grupo]) }}</x-ui.badge></div>
                        <h2 class="font-semibold text-texto">{{ $m->name }}</h2>
                        <p class="text-sm text-texto-suave">{{ $m->docente ?? __('Docente por asignar') }}</p>
                        <x-ui.progress :valor="$m->recursos ? 100 * $m->completados / $m->recursos : 0" :etiqueta="__('Avance de :m', ['m' => $m->code])" />
                    </a>
                    <div class="border-t border-borde px-2 py-1">
                        <x-ui.button tamano="sm" variante="fantasma" icono="arrows-right-left" wire:click="abrirCambio({{ $m->id }})">{{ __('Solicitar cambio de grupo') }}</x-ui.button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <x-ui.modal nombre="cambio-grupo" :titulo="__('Solicitar cambio de grupo')">
        @if ($cambio)
            <div class="flex flex-col gap-4">
                <p class="text-sm text-texto">{{ $cambio['materia'] }}</p>
                @if (! $cambio['abierto'])
                    <x-ui.alert tipo="aviso">{{ __('El plazo para pedir cambios de grupo terminó el :fecha.', ['fecha' => $cambio['plazo']]) }}</x-ui.alert>
                @elseif ($cambio['pendiente'])
                    <x-ui.alert tipo="info">{{ __('Ya tiene una solicitud pendiente en esta materia; su coordinador la revisará.') }}</x-ui.alert>
                @elseif (empty($cambio['secciones']))
                    <x-ui.alert tipo="info">{{ __('No hay otros grupos abiertos de esta materia.') }}</x-ui.alert>
                @else
                    <p class="text-xs text-texto-suave">{{ __('Puede pedirlo hasta el :fecha. Su coordinador decide; mientras tanto sigue en su grupo actual.', ['fecha' => $cambio['plazo']]) }}</p>
                    <div class="flex flex-col gap-2">
                        @foreach ($cambio['secciones'] as $s)
                            @php($bloqueada = $s['llena'] || $s['choque'])
                            <label @class(['flex items-start gap-3 rounded-lg p-3 ring-1', 'ring-borde hover:bg-zinc-50' => ! $bloqueada, 'cursor-not-allowed bg-zinc-50 text-texto-desactivado ring-borde' => $bloqueada])>
                                <input type="radio" wire:model="destino" value="{{ $s['id'] }}" @disabled($bloqueada) class="mt-1 text-marca-600 focus:ring-marca-600">
                                <span class="text-sm"><span class="font-semibold">{{ __('Grupo :g', ['g' => $s['grupo']]) }}</span>
                                    <span class="block text-xs">{{ $s['llena'] ? __('Sin cupo') : ($s['choque'] ? __('Choca con :s', ['s' => $s['choque']]) : __(':n cupos libres', ['n' => $s['cupo']])) }}</span></span>
                            </label>
                        @endforeach
                    </div>
                    @error('destino')<p class="text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                    <x-ui.field :etiqueta="__('Justificación')" para="justificacion" error="justificacion" requerido>
                        <x-ui.textarea id="justificacion" rows="3" wire:model="justificacion" />
                    </x-ui.field>
                @endif
            </div>
        @endif
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'cambio-grupo')">{{ __('Cancelar') }}</x-ui.button>
            @if ($cambio && $cambio['abierto'] && ! $cambio['pendiente'] && ! empty($cambio['secciones']))<x-ui.button wire:click="solicitarCambio">{{ __('Enviar solicitud') }}</x-ui.button>@endif
        </x-slot:pie>
    </x-ui.modal>
</div>
