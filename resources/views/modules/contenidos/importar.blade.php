{{-- Importar de un período anterior (RF14, maqueta 39). --}}
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />
    <x-ui.card :titulo="__('Importar de un período anterior')" :descripcion="__('Se copian las unidades con sus recursos, al final del contenido actual, y las tareas y cuestionarios sin fechas ni cómputo, para que los programe en Actividades. No se copian entregas, notas ni asistencia.')">
        @if ($origenes->isEmpty())
            <x-ui.empty-state icono="arrow-up-tray" :titulo="__('No hay contenido para importar')">{{ __('No encontramos secciones de períodos anteriores de esta asignatura, ni secciones suyas, con unidades o evaluaciones.') }}</x-ui.empty-state>
        @else
            <form wire:submit="importar" class="flex flex-col gap-3">
                @foreach ($origenes as $o)
                    <label @class(['flex items-center gap-3 rounded-lg p-3 ring-1 ring-borde', 'hover:bg-zinc-50' => ! $o->importada, 'opacity-60' => $o->importada]) wire:key="origen-{{ $o->id }}">
                        <input type="radio" wire:model="origen" value="{{ $o->id }}" @disabled($o->importada) class="text-marca-600 focus:ring-marca-600">
                        <span class="text-sm"><span class="font-semibold">{{ $o->code }} · {{ $o->grupo }}</span> — {{ $o->name }}<span class="block text-xs text-texto-suave">{{ __('Período :p · :u unidades · :e evaluaciones', ['p' => $o->periodo, 'u' => $o->unidades, 'e' => $o->evaluaciones]) }}</span></span>
                        @if ($o->importada)<x-ui.badge class="ml-auto">{{ __('Ya importada') }}</x-ui.badge>@endif
                    </label>
                @endforeach
                @error('origen')<p class="text-xs font-medium text-red-700">{{ $message }}</p>@enderror
                <div><x-ui.button type="submit" icono="arrow-up-tray">{{ __('Importar contenido') }}</x-ui.button></div>
            </form>
        @endif
    </x-ui.card>
</div>
