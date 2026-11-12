{{-- Matrícula · Estudiantes (RF09, maqueta 35). --}}
@php($vias = ['key' => __('Clave'), 'teacher' => __('Docente'), 'coordinator' => __('Coordinación'), 'head' => __('Jefatura'), 'admin' => __('Administración'), 'import' => __('Importación'), 'group_change' => __('Cambio de grupo')])
<div class="flex flex-col gap-5 lg:h-full">
    <x-ui.encabezado :titulo="__('Matrícula')" :subtitulo="__('Se validan cupo, duplicado y choque de horario. Los prerrequisitos no se exigen.')">
        <x-slot:acciones><x-ui.button icono="user-plus" x-on:click="$dispatch('abrir-modal', 'inscribir')">{{ __('Inscribir estudiante') }}</x-ui.button></x-slot:acciones>
    </x-ui.encabezado>
    <x-ui.tabs :pestanas="$pestanas" />

    <x-ui.card ajustar>
        <x-ui.search class="mb-4" wire:model.live.debounce.300ms="buscar" :placeholder="__('Buscar por estudiante, correo o asignatura…')" />
        <x-ui.table ajustar :vacio="__('No hay matrículas con esos filtros.')">
            <x-slot:cabecera><th>{{ __('Estudiante') }}</th><th>{{ __('Sección') }}</th><th>{{ __('Vía') }}</th><th>{{ __('Estado') }}</th><th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
            @foreach ($matriculas as $m)
                <tr wire:key="mat-{{ $m->id }}">
                    <td>
                        @if (in_array((int) $m->estudiante_id, $conPerfil, true))
                            <a href="{{ route('personas.show', ['usuario' => $m->estudiante_id]) }}" wire:navigate class="hover:text-marca-700 hover:underline">{{ $m->first_name }} {{ $m->last_name }}</a>
                        @else
                            {{ $m->first_name }} {{ $m->last_name }}
                        @endif
                        <p class="text-xs text-texto-suave">{{ $m->student_code ?? $m->institutional_email }}</p>
                    </td>
                    <td><span class="font-semibold">{{ $m->code }}</span> · {{ $m->grupo }}</td>
                    <td>{{ $vias[$m->enrolled_via] ?? $m->enrolled_via }}</td>
                    <td><x-ui.badge :color="['active' => 'verde', 'withdrawn' => 'gris', 'transferred' => 'azul'][$m->status]">{{ ['active' => __('Activa'), 'withdrawn' => __('Retirada'), 'transferred' => __('Trasladada')][$m->status] }}</x-ui.badge></td>
                    <td class="text-right">@if ($puedeRetirar && $m->status === 'active')<x-ui.button tamano="sm" variante="peligro" wire:click="confirmarRetiro({{ $m->id }})">{{ __('Retirar') }}</x-ui.button>@endif</td>
                </tr>
            @endforeach
        </x-ui.table>
        {{ $matriculas->links() }}
    </x-ui.card>

    <x-ui.modal nombre="inscribir" :titulo="__('Inscribir estudiante')">
        <form wire:submit="inscribir" id="form-inscribir" class="flex flex-col gap-4">
            @error('plazo')<x-ui.alert tipo="error">{{ $message }}</x-ui.alert>@enderror
            <x-ui.field :etiqueta="__('Estudiante')" para="estudianteBuscar" error="estudianteId" :ayuda="__('Busque por carné, correo o nombre.')" requerido>
                <x-ui.search id="estudianteBuscar" wire:model.live.debounce.300ms="estudianteBuscar" :placeholder="__('gs21004')" />
            </x-ui.field>
            @if ($candidatos->isNotEmpty())
                <div class="flex flex-col gap-1">
                    @foreach ($candidatos as $c)
                        <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-zinc-50"><input type="radio" wire:model.live="estudianteId" value="{{ $c->id }}" class="text-marca-600 focus:ring-marca-600"> {{ $c->first_name }} {{ $c->last_name }} <span class="text-texto-suave">{{ $c->student_code }}</span></label>
                    @endforeach
                </div>
            @endif
            <x-ui.field :etiqueta="__('Sección')" para="seccionId" error="seccion" :ayuda="__('Al elegir al estudiante, solo quedan las asignaturas del plan de su carrera.')" requerido>
                <x-ui.select id="seccionId" wire:model="seccionId"><option value="">{{ __('Elija…') }}</option>@foreach ($secciones as $s)<option value="{{ $s->id }}">{{ $s->code }} · {{ $s->grupo }} — {{ $s->name }}</option>@endforeach</x-ui.select>
            </x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'inscribir')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-inscribir">{{ __('Inscribir') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>

    <x-ui.modal nombre="retirar" :titulo="__('Retirar matrícula')">
        <form wire:submit="retirar" id="form-retirar" class="flex flex-col gap-4">
            <p class="text-sm text-texto-suave">{{ __('La matrícula queda retirada; su historial se conserva y el cambio queda en la auditoría.') }}</p>
            <x-ui.field :etiqueta="__('Motivo')" para="motivoRetiro" error="motivo" requerido><x-ui.textarea id="motivoRetiro" wire:model="motivo" /></x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'retirar')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-retirar" variante="peligro-solido">{{ __('Retirar') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>
</div>
