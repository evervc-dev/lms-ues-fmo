{{-- Cuestionario (RF16, maqueta 33). --}}
@php
    $fecha = fn ($f) => \Illuminate\Support\Carbon::parse($f)->timezone('America/El_Salvador')->format('d/m/Y H:i');
    $estadosIntento = ['in_progress' => ['ambar', __('En curso')], 'submitted' => ['azul', __('Enviado')], 'pending_grading' => ['ambar', __('Ensayos por calificar')], 'graded' => ['verde', __('Calificado')]];
@endphp
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    <x-ui.card :titulo="$cuestionario->title">
        @if ($cuestionario->instructions)<p class="mb-4 whitespace-pre-line text-sm">{{ $cuestionario->instructions }}</p>@endif
        <dl class="grid gap-3 text-sm sm:grid-cols-4">
            <div><dt class="text-texto-suave">{{ __('Abre') }}</dt><dd>{{ $fecha($cuestionario->opens_at) }}</dd></div>
            <div><dt class="text-texto-suave">{{ __('Cierra') }}</dt><dd class="font-semibold">{{ $fecha($cuestionario->closes_at) }}</dd></div>
            <div><dt class="text-texto-suave">{{ __('Tiempo') }}</dt><dd>{{ __(':n minutos', ['n' => $cuestionario->time_limit_minutes]) }}</dd></div>
            <div><dt class="text-texto-suave">{{ __('Preguntas · intentos') }}</dt><dd>{{ $preguntas }} · {{ $cuestionario->max_attempts }}</dd></div>
        </dl>
        <p class="mt-3 text-xs text-texto-suave">{{ $cuestionario->navigation === 'linear' ? __('Una a una: no podrá volver a preguntas anteriores.') : __('Navegación libre entre preguntas.') }} {{ __('Sus respuestas se guardan solas.') }}</p>

        @if ($esEstudiante)
            <div class="mt-5 flex flex-col gap-3">
                @foreach ($misIntentos as $i)
                    <div class="flex items-center justify-between rounded-lg p-3 text-sm ring-1 ring-borde">
                        <span>{{ __('Intento :n', ['n' => $i->attempt_number]) }} · <x-ui.badge :color="$estadosIntento[$i->status][0]">{{ $estadosIntento[$i->status][1] }}</x-ui.badge></span>
                        @if ($i->status === 'in_progress')<x-ui.button tamano="sm" :href="route('intentos.show', $i->ulid)" :navegar="false">{{ __('Continuar') }}</x-ui.button>
                        @else
                            <span class="flex items-center gap-3">
                                @if ($revisionVisible)<a href="{{ route('intentos.show', $i->ulid) }}" class="text-sm font-medium text-marca-700 hover:underline">{{ __('Ver revisión') }}</a>@endif
                                @if ($cuestionario->is_published && $i->score !== null)<span class="font-semibold">{{ number_format((float) $i->score, 1) }}</span>@endif
                            </span>
                        @endif
                    </div>
                @endforeach
                @if ($estado === 'abierta' && $misIntentos->where('status', 'in_progress')->isEmpty() && $misIntentos->count() < $cuestionario->max_attempts)
                    <div><x-ui.button wire:click="comenzar" wire:confirm="{{ __('El tiempo empieza a correr al comenzar. ¿Continuar?') }}">{{ __('Comenzar intento') }}</x-ui.button></div>
                @elseif ($estado === 'proxima')
                    <p class="text-sm text-texto-suave">{{ __('El cuestionario aún no abre.') }}</p>
                @endif
            </div>
        @endif
    </x-ui.card>

    @if ($esEstudiante && $misIntentos->where('status', '!=', 'in_progress')->isNotEmpty())
        <livewire:calificaciones.solicitud-revision :seccion="$seccion" :actividad="$cuestionario->ulid" :key="'revision-'.$cuestionario->ulid" />
    @endif

    @if ($gestiona)
        <x-ui.card :titulo="__('Intentos')">
            <x-slot:acciones>
                @can('calificaciones.revision.resolver')
                    <livewire:evaluaciones.prorrogas :seccion="$seccion" :actividad="$cuestionario->ulid" :key="'prorrogas-'.$cuestionario->ulid" />
                @endcan
            </x-slot:acciones>
            <livewire:evaluaciones.monitor-cuestionario :seccion="$seccion" :cuestionario="$cuestionario->ulid" :key="'monitor-'.$cuestionario->ulid" />
        </x-ui.card>

        @if ($aciertos->isNotEmpty())
            <x-ui.card :titulo="__('Aciertos por pregunta')" :descripcion="__('Intentos enviados, de la pregunta más difícil a la más fácil. Un acierto es la respuesta con el valor completo.')">
                <ul class="flex flex-col gap-3">
                    @foreach ($aciertos as $p)
                        <li wire:key="acierto-{{ $p->id }}">
                            <p class="text-sm">{{ \Illuminate\Support\Str::limit($p->statement, 140) }}</p>
                            <x-ui.progress class="mt-1" :valor="$p->porcentaje" :tono="$p->porcentaje < 50 ? 'error' : ($p->porcentaje < 75 ? 'aviso' : 'exito')"
                                :etiqueta="__('Aciertos')" />
                            <p class="mt-0.5 text-xs text-texto-suave">{{ __(':a de :r respuestas', ['a' => $p->aciertos, 'r' => $p->respuestas]) }}</p>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        @if ($ensayosPendientes->isNotEmpty())
            <x-ui.card :titulo="__('Ensayos por calificar')" :descripcion="__('Califique cada ensayo de 0 a 1 (proporción del valor de la pregunta).')">
                <ul class="flex flex-col gap-4">
                    @foreach ($ensayosPendientes as $r)
                        <li class="rounded-lg p-3 ring-1 ring-borde" wire:key="ensayo-{{ $r->id }}">
                            <p class="text-xs text-texto-suave">{{ $r->statement }}</p>
                            <p class="mt-1 whitespace-pre-line text-sm">{{ $r->answer_text ?: __('(sin respuesta)') }}</p>
                            <div class="mt-2 flex items-center gap-2"><x-ui.input type="number" step="0.1" min="0" max="1" class="w-24" wire:model="ensayos.{{ $r->id }}" :aria-label="__('Puntos')" />
                                <x-ui.button tamano="sm" wire:click="calificarEnsayo({{ $r->id }})">{{ __('Guardar') }}</x-ui.button></div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
    @endif
</div>
