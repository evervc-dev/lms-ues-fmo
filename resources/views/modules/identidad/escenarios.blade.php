{{-- Wizard de escenarios de prueba (solo local y staging, LMS_DEV_LOGIN=true). --}}
<div class="flex flex-col gap-6">
    <x-ui.alert tipo="aviso" titulo="{{ __('Entorno de pruebas') }}">
        {{ __('Esta pantalla siembra datos y asigna roles. En producción no existe.') }}
        <a href="{{ route('login') }}" class="font-medium underline" wire:navigate>{{ __('Volver al inicio de sesión') }}</a>
    </x-ui.alert>

    @if (! $autorizado)
        @if ($pideClave)
            <section class="flex flex-col gap-3">
                <div class="flex items-center gap-2 text-sm font-semibold text-zinc-800">
                    <x-ui.icon name="lock-closed" class="size-4" />
                    {{ __('Clave del entorno de pruebas') }}
                </div>
                <p class="text-sm text-zinc-600">{{ __('Puede ver los escenarios que ya existen, pero para preparar uno nuevo hace falta la clave que les compartió quien montó el entorno.') }}</p>
                <form wire:submit="autorizar" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <x-ui.field etiqueta="{{ __('Clave') }}" para="clave" error="clave" class="sm:flex-1">
                        <x-ui.input id="clave" type="password" wire:model="clave" autocomplete="off" />
                    </x-ui.field>
                    <x-ui.button type="submit">{{ __('Entrar') }}</x-ui.button>
                </form>
            </section>
        @else
            <x-ui.alert tipo="info">
                {{ __('No hay clave configurada (LMS_ESCENARIOS_CLAVE), así que solo un Administrador con sesión iniciada puede preparar escenarios. Puede ver los que ya existen más abajo.') }}
            </x-ui.alert>
        @endif
    @endif

    @if ($autorizado)
        {{-- Pasos --}}
        <ol class="flex flex-wrap items-center gap-2 text-sm">
            @foreach ([1 => __('Flujo'), 2 => __('Reparto'), 3 => __('Confirmar')] as $numero => $nombre)
                <li class="flex items-center gap-2">
                    <button type="button" wire:click="irAlPaso({{ $numero }})" @disabled($numero > 1 && $flujo === '')
                        @class([
                            'flex items-center gap-2 rounded-full px-3 py-1 font-medium transition',
                            'bg-marca-600 text-white' => $paso === $numero,
                            'text-zinc-600 hover:bg-zinc-100' => $paso !== $numero,
                            'cursor-not-allowed opacity-50' => $numero > 1 && $flujo === '',
                        ])>
                        <span class="grid size-5 place-items-center rounded-full text-xs {{ $paso === $numero ? 'bg-white/20' : 'bg-zinc-200' }}">{{ $numero }}</span>
                        {{ $nombre }}
                    </button>
                    @if ($numero < 3)
                        <x-ui.icon name="chevron-right" class="size-4 text-zinc-400" />
                    @endif
                </li>
            @endforeach
        </ol>

        @error('reparto')
            <x-ui.alert tipo="error">{{ $message }}</x-ui.alert>
        @enderror

        {{-- Paso 1: el flujo --}}
        @if ($paso === 1)
            <section class="grid gap-3 sm:grid-cols-2">
                @foreach ($catalogo as $clave => $definicion)
                    <button type="button" wire:key="flujo-{{ $clave }}" wire:click="elegirFlujo('{{ $clave }}')"
                        class="flex flex-col gap-2 rounded-xl p-4 text-left ring-1 ring-zinc-200 transition hover:bg-zinc-50 hover:ring-marca-300">
                        <span class="flex items-start justify-between gap-2">
                            <span class="font-semibold text-zinc-900">{{ $definicion['titulo'] }}</span>
                            <x-ui.icon name="chevron-right" class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                        </span>
                        <span class="text-sm text-zinc-600">{{ $definicion['descripcion'] }}</span>
                        <span class="flex flex-wrap gap-1">
                            @foreach ($definicion['rfs'] as $rf)
                                <x-ui.badge color="azul">{{ $rf }}</x-ui.badge>
                            @endforeach
                        </span>
                    </button>
                @endforeach
            </section>
        @endif

        {{-- Paso 2: quién juega qué --}}
        @if ($paso === 2 && $definicion !== null)
            <section class="flex flex-col gap-5">
                <div>
                    <h2 class="font-semibold text-zinc-900">{{ $definicion['titulo'] }}</h2>
                    <p class="text-sm text-zinc-600">{{ $definicion['descripcion'] }}</p>
                </div>

                @foreach ($definicion['roles'] as $rol => $regla)
                    <div wire:key="rol-{{ $rol }}" class="flex flex-col gap-2">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h3 class="text-sm font-semibold text-zinc-800">
                                {{ $roles[$rol] ?? $rol }}
                                @if ($regla['minimo'] === 0)
                                    <span class="font-normal text-zinc-500">({{ __('opcional') }})</span>
                                @elseif ($regla['maximo'] > 1)
                                    <span class="font-normal text-zinc-500">({{ __('de :min a :max', ['min' => $regla['minimo'], 'max' => $regla['maximo']]) }})</span>
                                @endif
                            </h3>
                            <p class="text-xs text-zinc-500">{{ $regla['etiqueta'] }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($testers as $tester)
                                @php $elegido = in_array($tester->id, $reparto[$rol] ?? [], true); @endphp
                                <button type="button" wire:key="rol-{{ $rol }}-{{ $tester->id }}" wire:click="alternar('{{ $rol }}', {{ $tester->id }})"
                                    @class([
                                        'flex items-center gap-2 rounded-full px-3 py-1.5 text-sm transition ring-1 ring-inset',
                                        'bg-marca-600 text-white ring-marca-600' => $elegido,
                                        'bg-white text-zinc-700 ring-zinc-300 hover:bg-zinc-50' => ! $elegido,
                                    ])>
                                    @if ($elegido)
                                        <x-ui.icon name="check-circle" class="size-4" />
                                    @endif
                                    {{ $tester->nombre }}
                                    <span class="{{ $elegido ? 'text-white/70' : 'text-zinc-400' }}">{{ $tester->due }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.field etiqueta="{{ __('Estudiantes de relleno') }}" para="relleno"
                        ayuda="{{ __('Cuentas inventadas que dan volumen a las listas y los reportes. No pueden iniciar sesión.') }}">
                        <x-ui.input id="relleno" type="number" min="0" max="80" wire:model="relleno" />
                    </x-ui.field>
                    <x-ui.field etiqueta="{{ __('Nota del escenario') }}" para="notas" ayuda="{{ __('Opcional: para qué sesión de pruebas es.') }}">
                        <x-ui.input id="notas" wire:model="notas" placeholder="{{ __('ej. sesión de QA del viernes') }}" />
                    </x-ui.field>
                </div>

                <div class="flex justify-between gap-2">
                    <x-ui.button variante="secundario" icono="arrow-left" wire:click="irAlPaso(1)">{{ __('Cambiar de flujo') }}</x-ui.button>
                    <x-ui.button wire:click="irAlPaso(3)">{{ __('Revisar el reparto') }}</x-ui.button>
                </div>
            </section>
        @endif

        {{-- Paso 3: confirmar --}}
        @if ($paso === 3 && $definicion !== null)
            <section class="flex flex-col gap-4">
                <div>
                    <h2 class="font-semibold text-zinc-900">{{ $definicion['titulo'] }}</h2>
                    <p class="text-sm text-zinc-600">{{ __('Se va a sembrar esto. Nada de lo que ya existe se borra ni se modifica.') }}</p>
                </div>

                <dl class="divide-y divide-zinc-100 rounded-xl ring-1 ring-zinc-200">
                    @foreach ($definicion['roles'] as $rol => $regla)
                        <div wire:key="resumen-{{ $rol }}" class="flex flex-wrap gap-2 px-4 py-3">
                            <dt class="w-40 shrink-0 text-sm font-medium text-zinc-700">{{ $roles[$rol] ?? $rol }}</dt>
                            <dd class="min-w-0 flex-1 text-sm text-zinc-900">
                                @php $elegidos = $testers->whereIn('id', $reparto[$rol] ?? []); @endphp
                                @if ($elegidos->isEmpty())
                                    <span class="text-zinc-400">{{ $regla['minimo'] === 0 ? __('sin asignar (opcional)') : __('falta asignar') }}</span>
                                @else
                                    {{ $elegidos->pluck('nombre')->join(', ', ' y ') }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                    <div class="flex flex-wrap gap-2 px-4 py-3">
                        <dt class="w-40 shrink-0 text-sm font-medium text-zinc-700">{{ __('Relleno') }}</dt>
                        <dd class="text-sm text-zinc-900">{{ trans_choice(':count estudiante inventado|:count estudiantes inventados', $relleno, ['count' => $relleno]) }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-2 px-4 py-3">
                        <dt class="w-40 shrink-0 text-sm font-medium text-zinc-700">{{ __('Casos de prueba') }}</dt>
                        <dd class="text-sm text-zinc-900">{{ __('los de :rfs', ['rfs' => implode(', ', $definicion['rfs'])]) }}</dd>
                    </div>
                </dl>

                <div class="flex justify-between gap-2">
                    <x-ui.button variante="secundario" icono="arrow-left" wire:click="irAlPaso(2)">{{ __('Cambiar el reparto') }}</x-ui.button>
                    <x-ui.button wire:click="preparar" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="preparar">{{ __('Preparar el escenario') }}</span>
                        <span wire:loading wire:target="preparar">{{ __('Sembrando…') }}</span>
                    </x-ui.button>
                </div>
            </section>
        @endif
    @endif

    {{-- Escenarios vivos --}}
    <section class="flex flex-col gap-3 border-t border-zinc-100 pt-5">
        <h2 class="text-sm font-semibold text-zinc-800">{{ __('Escenarios preparados') }}</h2>
        @forelse ($activos as $escenario)
            <a href="{{ route('dev.escenarios.show', ['codigo' => $escenario->code]) }}" wire:navigate wire:key="activo-{{ $escenario->id }}"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg px-3 py-2 ring-1 ring-zinc-200 transition hover:bg-zinc-50">
                <span class="min-w-0">
                    <span class="font-mono text-sm font-semibold text-marca-700">{{ $escenario->code }}</span>
                    <span class="ml-2 text-sm text-zinc-900">{{ $escenario->title }}</span>
                    @if ($escenario->notes)
                        <span class="ml-1 text-xs text-zinc-500">· {{ $escenario->notes }}</span>
                    @endif
                </span>
                <span class="text-xs text-zinc-500">{{ \Illuminate\Support\Carbon::parse($escenario->created_at)->diffForHumans() }}</span>
            </a>
        @empty
            <x-ui.empty-state icono="puzzle-piece" titulo="{{ __('Todavía no hay escenarios') }}">{{ __('Prepare el primero con el wizard de arriba.') }}</x-ui.empty-state>
        @endforelse
    </section>
</div>
