{{-- Página de un escenario de prueba: reparto, enlaces por rol y checklist. Se comparte con el grupo. --}}
<div class="flex flex-col gap-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="font-mono text-sm font-semibold text-marca-700">{{ $escenario->code }}</p>
            <h1 class="text-xl font-semibold text-zinc-900">{{ $escenario->title }}</h1>
            @if ($escenario->notes)
                <p class="mt-1 text-sm text-zinc-600">{{ $escenario->notes }}</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($conteo->sum() > 0)
                <x-ui.badge color="verde">{{ __(':n pasan', ['n' => $conteo['pass'] ?? 0]) }}</x-ui.badge>
                <x-ui.badge color="rojo">{{ __(':n fallan', ['n' => $conteo['fail'] ?? 0]) }}</x-ui.badge>
                <x-ui.badge color="ambar">{{ __(':n bloqueados', ['n' => $conteo['blocked'] ?? 0]) }}</x-ui.badge>
                <x-ui.badge>{{ __(':n sin probar', ['n' => $conteo['pending'] ?? 0]) }}</x-ui.badge>
            @endif
            <x-ui.button variante="secundario" tamano="sm" :href="route('dev.escenarios')">{{ __('Escenarios') }}</x-ui.button>
        </div>
    </header>

    @error('checklist')
        <x-ui.alert tipo="error">{{ $message }}</x-ui.alert>
    @enderror

    @guest
        <x-ui.alert tipo="info">
            {{ __('Inicie sesión con su cuenta @ues.edu.sv para abrir los enlaces y marcar los casos.') }}
            <a href="{{ route('login') }}" class="font-medium underline">{{ __('Iniciar sesión') }}</a>
        </x-ui.alert>
    @endguest

    {{-- Qué quedó sembrado --}}
    <section class="flex flex-col gap-2 rounded-xl bg-zinc-50 p-4 ring-1 ring-zinc-200">
        <h2 class="text-sm font-semibold text-zinc-800">{{ __('Qué quedó preparado') }}</h2>
        <ul class="flex flex-col gap-1 text-sm text-zinc-700">
            @foreach ($resumen as $linea)
                <li class="flex gap-2"><span class="text-zinc-400">·</span><span>{{ $linea }}</span></li>
            @endforeach
        </ul>
        @if (($datos['csv_meet'] ?? null) !== null)
            <details class="mt-2">
                <summary class="cursor-pointer text-sm font-medium text-marca-700">{{ __('CSV de Meet para subir (cópielo a un archivo .csv)') }}</summary>
                <pre class="mt-2 overflow-x-auto rounded-lg bg-white p-3 text-xs ring-1 ring-zinc-200">{{ $datos['csv_meet'] }}</pre>
            </details>
        @endif
        @if (($datos['claves_inscripcion'] ?? null) !== null)
            <p class="mt-1 text-sm text-zinc-700">
                {{ __('Claves de inscripción:') }}
                @foreach ($datos['claves_inscripcion'] as $materia => $clave)
                    <code class="ml-1 rounded bg-white px-1.5 py-0.5 text-xs ring-1 ring-zinc-200">{{ $clave }}</code>
                @endforeach
            </p>
        @elseif (($datos['clave_inscripcion'] ?? null) !== null)
            <p class="mt-1 text-sm text-zinc-700">
                {{ __('Clave de inscripción:') }}
                <code class="rounded bg-white px-1.5 py-0.5 text-xs ring-1 ring-zinc-200">{{ $datos['clave_inscripcion'] }}</code>
            </p>
        @endif
    </section>

    {{-- Reparto y enlaces, por rol --}}
    <section class="flex flex-col gap-4">
        <h2 class="text-sm font-semibold text-zinc-800">{{ __('Quién prueba qué') }}</h2>

        @foreach ($actores->groupBy('role') as $rol => $personas)
            <div wire:key="rol-{{ $rol }}" class="flex flex-col gap-3 rounded-xl p-4 ring-1 ring-zinc-200">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="font-semibold text-zinc-900">{{ $roles[$rol] ?? $rol }}</h3>
                    <p class="text-xs text-zinc-500">{{ $personas->first()->label }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach ($personas as $persona)
                        <span @class([
                            'flex items-center gap-2 rounded-full bg-zinc-100 px-3 py-1 text-sm text-zinc-800',
                            'ring-2 ring-marca-500' => auth()->id() === $persona->id,
                        ])>
                            {{ $persona->first_name }} {{ $persona->last_name }}
                            <span class="text-xs text-zinc-500">{{ $persona->institutional_email }}</span>
                        </span>
                    @endforeach
                </div>

                @if (($enlaces[$rol] ?? []) !== [])
                    <ul class="flex flex-col gap-1">
                        @foreach ($enlaces[$rol] as $enlace)
                            <li>
                                <a href="{{ $enlace['url'] }}" class="group flex flex-wrap items-baseline gap-2 rounded-lg px-2 py-1.5 text-sm transition hover:bg-zinc-50">
                                    <x-ui.icon name="arrow-top-right-on-square" class="size-4 shrink-0 text-zinc-400 group-hover:text-marca-600" />
                                    <span class="font-medium text-marca-700 group-hover:underline">{{ $enlace['titulo'] }}</span>
                                    @if ($enlace['nota'])
                                        <span class="text-zinc-600">— {{ $enlace['nota'] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </section>

    {{-- Checklist --}}
    <section class="flex flex-col gap-2">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-semibold text-zinc-800">{{ __('Checklist') }}</h2>
            <p class="text-xs text-zinc-500">{{ __('Los casos de prueba del RF, de docs/10 Calidad. Marque lo que pruebe.') }}</p>
        </div>

        <ul class="flex flex-col divide-y divide-zinc-100 rounded-xl ring-1 ring-zinc-200">
            @foreach ($checklist as $caso)
                <li wire:key="caso-{{ $caso->id }}" class="flex flex-col gap-2 px-4 py-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-mono text-xs text-zinc-500">{{ $caso->case_id }}</p>
                            <p class="text-sm text-zinc-900">{{ $caso->statement }}</p>
                            @if ($caso->note)
                                <p class="mt-1 text-sm text-amber-800">{{ __('Nota:') }} {{ $caso->note }}</p>
                            @endif
                            @if ($caso->marcado_por)
                                <p class="mt-1 text-xs text-zinc-500">{{ __('marcado por :quien', ['quien' => $caso->marcado_por]) }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            @foreach (['pass' => ['verde', __('Pasa')], 'fail' => ['rojo', __('Falla')], 'blocked' => ['ambar', __('Bloqueado')]] as $estado => [$color, $texto])
                                <button type="button" wire:click="marcar({{ $caso->id }}, '{{ $estado }}')"
                                    @class([
                                        'rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition',
                                        match ($color) {
                                            'verde' => $caso->status === $estado ? 'bg-emerald-600 text-white ring-emerald-600' : 'text-emerald-700 ring-emerald-200 hover:bg-emerald-50',
                                            'rojo' => $caso->status === $estado ? 'bg-red-600 text-white ring-red-600' : 'text-red-700 ring-red-200 hover:bg-red-50',
                                            default => $caso->status === $estado ? 'bg-amber-500 text-white ring-amber-500' : 'text-amber-700 ring-amber-200 hover:bg-amber-50',
                                        },
                                    ])>{{ $texto }}</button>
                            @endforeach
                            @if ($caso->status !== 'pending')
                                <button type="button" wire:click="marcar({{ $caso->id }}, 'pending')" class="rounded-full px-2 py-1 text-xs text-zinc-500 hover:bg-zinc-100" title="{{ __('Quitar la marca') }}">
                                    <x-ui.icon name="x-mark" class="size-4" />
                                </button>
                            @endif
                            <button type="button" wire:click="abrirNota({{ $caso->id }})" class="rounded-full px-2 py-1 text-xs text-zinc-500 hover:bg-zinc-100" title="{{ __('Anotar') }}">
                                <x-ui.icon name="clipboard-document-list" class="size-4" />
                            </button>
                        </div>
                    </div>

                    @if ($editando === $caso->id)
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                            <x-ui.field etiqueta="{{ __('Qué pasó') }}" para="nota-{{ $caso->id }}" class="sm:flex-1">
                                <x-ui.textarea id="nota-{{ $caso->id }}" rows="2" wire:model="nota" placeholder="{{ __('ej. el QR no rota cuando la pestaña está en segundo plano') }}" />
                            </x-ui.field>
                            <div class="flex gap-2">
                                <x-ui.button tamano="sm" wire:click="guardarNota({{ $caso->id }})">{{ __('Guardar') }}</x-ui.button>
                                <x-ui.button tamano="sm" variante="fantasma" wire:click="cerrarNota">{{ __('Cancelar') }}</x-ui.button>
                            </div>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    @role('administrador')
        <section class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100 pt-5">
            <p class="text-sm text-zinc-600">{{ __('Limpiar borra solo lo que sembró este escenario; el resto del entorno queda igual.') }}</p>
            <x-ui.button variante="peligro" tamano="sm" wire:click="limpiar"
                wire:confirm="{{ __('¿Limpiar el escenario :codigo? Se borran sus materias, grupos teóricos, matrículas y actividades.', ['codigo' => $escenario->code]) }}">
                {{ __('Limpiar este escenario') }}
            </x-ui.button>
        </section>
    @endrole
</div>
