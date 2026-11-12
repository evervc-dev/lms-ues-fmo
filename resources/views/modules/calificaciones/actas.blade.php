{{-- Actas por validar (RF34, maqueta 25). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Actas de su carrera')" :subtitulo="__('Valide cada acta publicada o devuélvala con observaciones.')">
        <x-slot:acciones><x-ui.select wire:model.live="estado" :aria-label="__('Estado')">@foreach ($estados as $v => $e)<option value="{{ $v }}">{{ $e }}</option>@endforeach</x-ui.select></x-slot:acciones>
    </x-ui.encabezado>
    <x-ui.card ajustar>
        <x-ui.table ajustar :vacio="__('No hay actas en este estado.')">
            <x-slot:cabecera><th>{{ __('Sección') }}</th><th>{{ __('Docente') }}</th><th>{{ __('Publicada') }}</th><th class="text-right">{{ __('Aprobados') }}</th><th class="text-right">{{ __('Promedio') }}</th><th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
            @foreach ($actas as $a)
                <tr wire:key="acta-{{ $a->section_id }}">
                    <td><a href="{{ route('secciones.notas', ['seccion' => $a->ulid]) }}" wire:navigate class="font-semibold hover:text-marca-700">{{ $a->code }} · {{ $a->grupo }}</a><p class="text-xs text-texto-suave">{{ $a->name }}</p></td>
                    <td>{{ $a->docente ?? '—' }}</td>
                    <td>{{ $a->published_at ? \Illuminate\Support\Carbon::parse($a->published_at)->format('d/m/Y') : '—' }}</td>
                    <td class="text-right">{{ $a->aprobados }} / {{ $a->estudiantes }}</td>
                    <td class="text-right">{{ $a->promedio ?? '—' }}</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($a->status === 'published')
                            <x-ui.button tamano="sm" variante="peligro" wire:click="confirmarDevolucion({{ $a->section_id }})">{{ __('Devolver') }}</x-ui.button>
                            <x-ui.button tamano="sm" variante="fantasma" wire:click="validar({{ $a->section_id }})" wire:confirm="{{ __('¿Validar el acta de :s? Pasa a la jefatura y el docente ya no puede cambiarla.', ['s' => $a->code.' · '.$a->grupo]) }}">{{ __('Validar') }}</x-ui.button>
                        @elseif ($a->status === 'validated')
                            <x-ui.button tamano="sm" variante="peligro" wire:click="confirmarDevolucion({{ $a->section_id }})">{{ __('Devolver') }}</x-ui.button>
                        @elseif ($a->status === 'returned')<span class="text-xs text-texto-suave">{{ $a->return_reason }}</span>@endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.modal nombre="devolver-acta" :titulo="__('Devolver acta')">
        <form wire:submit="devolver" id="form-devolver" class="flex flex-col gap-4">
            <x-ui.field :etiqueta="__('Observaciones para el docente')" para="motivoActa" error="motivo" requerido><x-ui.textarea id="motivoActa" wire:model="motivo" /></x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'devolver-acta')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-devolver" variante="peligro-solido">{{ __('Devolver') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
