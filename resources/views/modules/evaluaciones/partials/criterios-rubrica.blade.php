{{-- Editor de los criterios de la rúbrica de una tarea (RF15, ADR-065). $modelo es la propiedad Livewire con los criterios. --}}
<div class="flex flex-col gap-2">
    <p class="text-sm font-medium">{{ __('Criterios y puntos máximos (deben sumar 10)') }}</p>
    @foreach ($criterios as $i => $c)
        <div class="grid grid-cols-[1fr_6rem_auto] items-center gap-2" wire:key="{{ $modelo }}-{{ $i }}">
            <x-ui.input wire:model="{{ $modelo }}.{{ $i }}.nombre" :aria-label="__('Criterio :n', ['n' => $i + 1])" :placeholder="__('Ej.: Contenido y exactitud')" />
            <x-ui.input type="number" step="0.5" min="0.5" max="10" wire:model.live.debounce.500ms="{{ $modelo }}.{{ $i }}.maximo" :aria-label="__('Puntos del criterio :n', ['n' => $i + 1])" />
            <x-ui.button tamano="sm" variante="fantasma" icono="x-mark" wire:click="quitarCriterio({{ $i }})" :disabled="count($criterios) === 1" :aria-label="__('Quitar el criterio :n', ['n' => $i + 1])" />
        </div>
    @endforeach
    @php($suma = array_sum(array_map(fn ($c) => (float) $c['maximo'], $criterios)))
    <p @class(['text-xs', 'text-texto-suave' => abs($suma - 10) < 0.001, 'font-medium text-red-700' => abs($suma - 10) >= 0.001])>{{ __('Suman :s de 10.', ['s' => rtrim(rtrim(number_format($suma, 2, '.', ''), '0'), '.')]) }}</p>
    @error('criterios')<p class="text-xs font-medium text-red-700">{{ $message }}</p>@enderror
    @error($modelo.'.*')<p class="text-xs font-medium text-red-700">{{ $message }}</p>@enderror
    <div><x-ui.button variante="fantasma" tamano="sm" icono="plus" wire:click="agregarCriterio">{{ __('Agregar criterio') }}</x-ui.button></div>
    <p class="text-xs text-texto-suave">{{ __('Cada criterio tiene tres niveles: Excelente (100 %), Bueno (70 %) y Regular (40 %) de su máximo. La nota es la suma de los niveles elegidos.') }}</p>
</div>
