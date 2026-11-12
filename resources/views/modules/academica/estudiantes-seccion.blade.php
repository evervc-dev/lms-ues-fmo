{{-- Vista de sección › Estudiantes (RF09, maqueta 38). --}}
<div class="flex flex-col gap-5 lg:h-full">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    <x-ui.card ajustar :titulo="__('Estudiantes')" :descripcion="trans_choice('{1} 1 matrícula activa|[0,*] :count matrículas activas', $estudiantes->where('status', 'active')->count())">
        <form wire:submit="inscribir" class="mb-4 flex flex-wrap items-end gap-2">
            <x-ui.field class="min-w-64 flex-1" :etiqueta="__('Inscribir por carné o correo')" para="carne" error="carne"><x-ui.input id="carne" wire:model="carne" placeholder="gs21004" /></x-ui.field>
            <x-ui.button type="submit" icono="user-plus">{{ __('Inscribir') }}</x-ui.button>
        </form>

        <x-ui.table ajustar :vacio="__('Aún no hay estudiantes en la sección.')">
            <x-slot:cabecera><th>{{ __('Estudiante') }}</th><th>{{ __('Estado') }}</th><th class="text-right">{{ __('Asistencia') }}</th><th class="text-right">{{ __('Promedio') }}</th><th>{{ __('Alertas') }}</th><th></th></x-slot:cabecera>
            @foreach ($estudiantes as $e)
                <tr wire:key="est-{{ $e->id }}" @class(['opacity-60' => $e->status !== 'active'])>
                    <td>{{ $e->first_name }} {{ $e->last_name }}<p class="text-xs text-texto-suave">{{ $e->student_code ?? $e->institutional_email }}</p></td>
                    <td><x-ui.badge :color="['active' => 'verde', 'withdrawn' => 'gris', 'transferred' => 'azul'][$e->status]">{{ ['active' => __('Activa'), 'withdrawn' => __('Retirada'), 'transferred' => __('Trasladada')][$e->status] }}</x-ui.badge></td>
                    <td class="text-right">@if ($e->asistencia !== null)<span @class(['text-red-700' => $e->asistencia < 75, 'text-amber-700' => $e->asistencia >= 75 && $e->asistencia < 85])>{{ $e->asistencia }} %</span>@else — @endif</td>
                    <td class="text-right">@if ($e->promedio !== null)<span @class(['text-red-700' => $e->promedio < 6])>{{ number_format((float) $e->promedio, 1) }}</span>@else — @endif</td>
                    <td>@if ($e->alertas)<x-ui.badge color="rojo">{{ $e->alertas }}</x-ui.badge>@endif</td>
                    <td class="text-right">@if ($puedeRetirar && $e->status === 'active')<x-ui.button tamano="sm" variante="peligro" wire:click="confirmarRetiro({{ $e->id }})">{{ __('Retirar') }}</x-ui.button>@endif</td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.modal nombre="retirar-seccion" :titulo="__('Retirar de la sección')">
        <form wire:submit="retirar" id="form-retirar-seccion" class="flex flex-col gap-4">
            <x-ui.field :etiqueta="__('Motivo')" para="motivoSeccion" error="motivo" requerido><x-ui.textarea id="motivoSeccion" wire:model="motivo" /></x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'retirar-seccion')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-retirar-seccion" variante="peligro-solido">{{ __('Retirar') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
