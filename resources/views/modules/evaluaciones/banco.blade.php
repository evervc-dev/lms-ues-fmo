{{-- Banco de preguntas (RF16, maqueta 32). --}}
@php($dificultades = ['easy' => __('Fácil'), 'medium' => __('Media'), 'hard' => __('Difícil')])
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="$banco->name" :subtitulo="trans_choice('{1} 1 pregunta|[0,*] :count preguntas', $preguntas->count()).($banco->code ? ' · '.$banco->code : '')">
        <x-slot:acciones><x-ui.button icono="plus" :href="route('bancos-de-preguntas.pregunta', ['banco' => $banco->id])">{{ __('Nueva pregunta') }}</x-ui.button></x-slot:acciones>
    </x-ui.encabezado>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,1fr)]">
        <div class="flex flex-col gap-3">
            @forelse ($preguntas as $p)
                <x-ui.card wire:key="preg-{{ $p->id }}">
                    <div class="mb-2 flex flex-wrap gap-1"><x-ui.badge color="marca">{{ __($tipos[$p->question_type]) }}</x-ui.badge>@if ($p->topic)<x-ui.badge>{{ $p->topic }}</x-ui.badge>@endif @if ($p->difficulty)<x-ui.badge>{{ $dificultades[$p->difficulty] ?? $p->difficulty }}</x-ui.badge>@endif</div>
                    <p class="text-sm font-medium text-texto">{{ $loop->iteration }}. {{ $p->statement }}</p>
                    @if ($p->opciones->isNotEmpty())
                        <ul class="mt-2 flex flex-col gap-1 text-sm">@foreach ($p->opciones as $o)<li @class(['font-semibold text-emerald-700' => $o->is_correct, 'text-texto-suave' => ! $o->is_correct])>{{ $o->is_correct ? '✓' : '·' }} {{ $o->text }}</li>@endforeach</ul>
                    @elseif ($p->accepted_answers)
                        <p class="mt-2 text-sm text-emerald-700">{{ __('Respuestas aceptadas: :r', ['r' => implode(', ', json_decode($p->accepted_answers, true) ?: [])]) }}</p>
                    @endif
                    @if ($p->justification)<p class="mt-2 text-xs text-texto-suave">{{ $p->justification }}</p>@endif
                </x-ui.card>
            @empty
                <x-ui.card><x-ui.empty-state icono="rectangle-stack" :titulo="__('El banco está vacío')" /></x-ui.card>
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

</div>
