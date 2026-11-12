{{-- Vista de sección › Asistencia › Clases virtuales (RF21, ADR-003). --}}
@php
    $fecha = fn ($f) => \Illuminate\Support\Carbon::parse($f)->timezone(config('app.timezone'))->format('d/m/Y H:i');
    $colorEstado = ['scheduled' => 'azul', 'in_progress' => 'verde', 'finished' => 'gris'];
@endphp
<div class="flex flex-col gap-5">
    <x-ui.card :titulo="__('Clases virtuales')" :descripcion="$gestiona ? __('Al terminar la clase, suba el reporte de asistencia de Meet: queda Presente quien estuvo al menos el :u % de la duración programada.', ['u' => $umbral]) : null">
        @if ($gestiona)
            <x-slot:acciones>
                <x-ui.button icono="calendar-days" x-on:click="$dispatch('abrir-modal', 'programar-clase')">{{ __('Programar clase') }}</x-ui.button>
            </x-slot:acciones>
        @endif

        <x-ui.table :vacio="__('Aún no hay clases virtuales.')">
            <x-slot:cabecera>
                <th>{{ __('Tema') }}</th><th>{{ __('Fecha y hora') }}</th><th>{{ __('Duración') }}</th><th>{{ __('Estado') }}</th><th>{{ __('Enlaces') }}</th>
                @if ($gestiona)<th class="text-right">{{ __('Asistencia') }}</th>@endif
            </x-slot:cabecera>
            @foreach ($clases as $c)
                <tr wire:key="virtual-{{ $c->id }}">
                    <td class="max-w-xs">{{ $c->topic }}</td>
                    <td class="whitespace-nowrap">{{ $fecha($c->starts_at) }}</td>
                    <td class="whitespace-nowrap">{{ __(':n min', ['n' => $c->duration_minutes]) }}</td>
                    <td><x-ui.badge :color="$colorEstado[$c->status] ?? 'gris'">{{ __($estados[$c->status] ?? $c->status) }}</x-ui.badge></td>
                    <td class="whitespace-nowrap">
                        @if ($c->meet_url)<a href="{{ $c->meet_url }}" target="_blank" rel="noopener" class="text-marca-700 underline">{{ __('Unirse a Meet') }}</a>@endif
                        @if ($c->recording_url)<a href="{{ $c->recording_url }}" target="_blank" rel="noopener" class="ml-2 text-marca-700 underline">{{ __('Ver grabación') }}</a>@endif
                        @if (! $c->meet_url && ! $c->recording_url)<span class="text-texto-suave">—</span>@endif
                    </td>
                    @if ($gestiona)
                        <td class="text-right whitespace-nowrap">
                            @if ($c->sesion_ulid)
                                <x-ui.badge color="verde">{{ __(':n presentes', ['n' => $c->presentes]) }}</x-ui.badge>
                                <x-ui.button tamano="sm" variante="fantasma" :href="route('secciones.asistencia.sesion', ['seccion' => $seccion, 'sesion' => $c->sesion_ulid])">{{ __('Ver sesión') }}</x-ui.button>
                            @endif
                            @if ($c->borrador_id)
                                <x-ui.button tamano="sm" variante="secundario" wire:click="$set('reporteId', {{ $c->borrador_id }})">{{ __('Vista previa') }}</x-ui.button>
                            @endif
                            @if ($c->status === 'finished')
                                <x-ui.button tamano="sm" :variante="$c->sesion_ulid ? 'fantasma' : 'primario'" icono="arrow-up-tray"
                                    wire:click="$set('claseId', {{ $c->id }})" x-on:click="$dispatch('abrir-modal', 'subir-reporte')">{{ $c->reporte_id ? __('Reemplazar reporte') : __('Subir reporte') }}</x-ui.button>
                                <x-ui.button tamano="sm" variante="fantasma" x-on:click="$dispatch('abrir-modal', 'grabacion-{{ $c->id }}')">{{ __('Grabación') }}</x-ui.button>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    @if ($gestiona && $previa)
        @php($borrador = $previa['reporte']->confirmed_at === null)
        <x-ui.card :titulo="__('Vista previa del reporte · :t', ['t' => $previa['clase']->topic])"
            :descripcion="__(':f · duración programada :n min. Presente desde el :u %.', ['f' => $fecha($previa['clase']->starts_at), 'n' => $previa['clase']->duration_minutes, 'u' => $umbral])">
            <x-slot:acciones>
                <x-ui.button variante="secundario" wire:click="$set('reporteId', null)">{{ __('Cerrar') }}</x-ui.button>
                @if ($borrador)
                    <x-ui.button icono="check-circle" wire:click="confirmar" :disabled="$previa['sin_asociar']->isNotEmpty()"
                        wire:confirm="{{ __('Se registrará la asistencia de la clase con este reporte. ¿Confirmar?') }}">{{ __('Confirmar asistencia') }}</x-ui.button>
                @endif
            </x-slot:acciones>

            @error('reporte')<x-ui.alert tipo="error" class="mb-4">{{ $message }}</x-ui.alert>@enderror
            @if ($borrador && $previa['sin_asociar']->isNotEmpty())
                <x-ui.alert tipo="aviso" class="mb-4">{{ __('Asigne o descarte las filas sin asociar para poder confirmar.') }}</x-ui.alert>
            @endif

            <div class="grid gap-5 xl:grid-cols-3">
                @foreach (['presentes' => __('Presentes'), 'ausentes' => __('Ausentes')] as $grupo => $titulo)
                    <div>
                        <h3 class="mb-2 text-sm font-semibold">{{ $titulo }} ({{ $previa[$grupo]->count() }})</h3>
                        <x-ui.table :vacio="__('Nadie en este grupo.')">
                            <x-slot:cabecera><th>{{ __('Estudiante') }}</th><th class="text-right">{{ __('Minutos') }}</th><th class="text-right">%</th></x-slot:cabecera>
                            @foreach ($previa[$grupo] as $e)
                                <tr wire:key="previa-{{ $grupo }}-{{ $e->matricula }}">
                                    <td>{{ $e->nombre }}@if ($e->carnet)<p class="text-xs text-texto-suave">{{ $e->carnet }}</p>@endif</td>
                                    <td class="text-right">{{ $e->minutos }}</td>
                                    <td class="text-right"><x-ui.badge :color="$e->estado === 'present' ? 'verde' : 'rojo'">{{ number_format($e->porcentaje, 2, ',', ' ') }} %</x-ui.badge></td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    </div>
                @endforeach

                <div>
                    <h3 class="mb-2 text-sm font-semibold">{{ __('Sin asociar') }} ({{ $previa['sin_asociar']->count() }})</h3>
                    <x-ui.table :vacio="__('Todas las filas están asociadas.')">
                        <x-slot:cabecera><th>{{ __('Participante') }}</th><th class="text-right">{{ __('Minutos') }}</th></x-slot:cabecera>
                        @foreach ($previa['sin_asociar'] as $f)
                            <tr wire:key="sin-asociar-{{ $f->id }}">
                                <td>
                                    {{ $f->name ?: __('Sin nombre') }}<p class="text-xs text-texto-suave">{{ $f->email ?: __('Sin correo') }}</p>
                                    @if ($borrador)
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <x-ui.select wire:model="asignaciones.{{ $f->id }}" :aria-label="__('Estudiante')" class="max-w-52">
                                                <option value="">{{ __('Elija…') }}</option>
                                                @foreach ($previa['presentes']->concat($previa['ausentes'])->sortBy('nombre') as $e)<option value="{{ $e->matricula }}">{{ $e->nombre }}</option>@endforeach
                                            </x-ui.select>
                                            <x-ui.button tamano="sm" variante="fantasma" wire:click="asignar({{ $f->id }})">{{ __('Asignar') }}</x-ui.button>
                                            <x-ui.button tamano="sm" variante="peligro" wire:click="descartar({{ $f->id }})" wire:confirm="{{ __('¿Descartar esta fila del reporte? No se contará para la asistencia.') }}">{{ __('Descartar') }}</x-ui.button>
                                        </div>
                                        @error('asignaciones.'.$f->id)<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                                    @endif
                                </td>
                                <td class="text-right align-top">{{ $f->minutes }}</td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                    @if ($previa['descartadas']->isNotEmpty())
                        <p class="mt-3 text-xs text-texto-suave">{{ __('Descartadas: :n', ['n' => $previa['descartadas']->map(fn ($f) => $f->name ?: $f->email)->implode(', ')]) }}</p>
                    @endif
                </div>
            </div>
        </x-ui.card>
    @endif

    @if ($gestiona)
        <x-ui.modal nombre="programar-clase" :titulo="__('Programar clase virtual')">
            <form wire:submit="programar" id="form-programar-clase" class="flex flex-col gap-4">
                <x-ui.field :etiqueta="__('Tema')" para="virtual-tema" error="tema" requerido><x-ui.input id="virtual-tema" wire:model="tema" maxlength="200" /></x-ui.field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field :etiqueta="__('Fecha y hora de inicio')" para="virtual-inicio" error="inicio" requerido><x-ui.input id="virtual-inicio" type="datetime-local" wire:model="inicio" /></x-ui.field>
                    <x-ui.field :etiqueta="__('Duración (15 a 300 min)')" para="virtual-duracion" error="duracion" requerido><x-ui.input id="virtual-duracion" type="number" min="15" max="300" wire:model="duracion" /></x-ui.field>
                </div>
                <x-ui.field :etiqueta="__('Enlace de Meet (opcional)')" para="virtual-meet" error="meetUrl" :ayuda="__('Ej.: https://meet.google.com/abc-defg-hij')"><x-ui.input id="virtual-meet" type="url" wire:model="meetUrl" /></x-ui.field>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'programar-clase')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-programar-clase">{{ __('Programar') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>

        <x-ui.modal nombre="subir-reporte" :titulo="__('Subir reporte de Meet')">
            <form wire:submit="subir" id="form-subir-reporte" class="flex flex-col gap-4">
                <x-ui.field :etiqueta="__('Reporte de asistencia')" para="virtual-archivo" error="archivo" requerido
                    :ayuda="__('Descargue el reporte de asistencia de Meet (Hoja de cálculo de Google) como CSV o Excel (.xlsx). Máximo 5 MB.')">
                    <input id="virtual-archivo" type="file" wire:model="archivo" accept=".csv,.txt,.xlsx" class="text-sm">
                </x-ui.field>
                <p class="text-xs text-texto-suave">{{ __('Si la clase ya tenía un reporte, el nuevo lo reemplaza al confirmarlo.') }}</p>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'subir-reporte')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-subir-reporte" wire:loading.attr="disabled" wire:target="archivo">{{ __('Leer reporte') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>

        @foreach ($clases->where('status', 'finished') as $c)
            <x-ui.modal :nombre="'grabacion-'.$c->id" :titulo="__('Grabación · :t', ['t' => $c->topic])" wire:key="modal-grabacion-{{ $c->id }}">
                <form wire:submit="guardarGrabacion({{ $c->id }})" id="form-grabacion-{{ $c->id }}" class="flex flex-col gap-4">
                    <x-ui.field :etiqueta="__('Enlace de la grabación')" :para="'grabacion-'.$c->id" :error="'grabaciones.'.$c->id" :ayuda="__('Déjelo vacío para quitarla. La grabación no cuenta como asistencia.')">
                        <x-ui.input :id="'grabacion-'.$c->id" type="url" wire:model="grabaciones.{{ $c->id }}" />
                    </x-ui.field>
                    @error('grabacion')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                </form>
                <x-slot:pie>
                    <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'grabacion-{{ $c->id }}')">{{ __('Cancelar') }}</x-ui.button>
                    <x-ui.button type="submit" :form="'form-grabacion-'.$c->id">{{ __('Guardar') }}</x-ui.button>
                </x-slot:pie>
            </x-ui.modal>
        @endforeach
    @endif
</div>
