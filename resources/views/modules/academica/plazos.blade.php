{{-- Plazos de matrícula (RF29, ADR-051): fecha límite por departamento, su prórroga y prórrogas por estudiante. --}}
<div class="flex flex-col gap-5">
    <x-ui.encabezado :titulo="__('Matrícula')" :subtitulo="__('Vencido el plazo no se inscribe a nadie, ni con clave ni a mano. Rige la fecha del departamento de la carrera del estudiante.')" />
    <x-ui.tabs :pestanas="$pestanas" />

    @if ($gestiona)
        <x-ui.card :titulo="__('Fecha límite por departamento')" :descripcion="__('Si el departamento no fija una fecha, rige la de por defecto: el inicio del período más las semanas configuradas.')">
            <x-ui.table :vacio="__('No hay un período en curso o no tiene departamentos en su ámbito.')">
                <x-slot:cabecera><th>{{ __('Departamento') }}</th><th>{{ __('Fecha que rige') }}</th><th>{{ __('Estado') }}</th><th class="text-right">{{ __('Acciones') }}</th></x-slot:cabecera>
                @foreach ($plazos as $p)
                    <tr wire:key="plazo-{{ $p->id }}">
                        <td class="font-semibold">{{ $p->name }}</td>
                        <td>
                            {{ $p->rige->format('d/m/Y') }}
                            @if ($p->deadline === null)
                                <span class="text-xs text-texto-suave">{{ __('por defecto') }}</span>
                            @endif
                            @if ($p->extended_until)
                                <p class="text-xs text-texto-suave">{{ __('Prorrogada hasta el :fecha — :motivo', ['fecha' => $p->extended_until->format('d/m/Y'), 'motivo' => $p->extension_reason]) }}</p>
                                <p class="text-xs text-texto-suave">{{ __('Fecha original: :fecha', ['fecha' => ($p->deadline ?? $p->por_defecto)->format('d/m/Y')]) }}</p>
                            @endif
                        </td>
                        <td><x-ui.badge :color="$p->vencido ? 'rojo' : 'verde'">{{ $p->vencido ? __('Vencida') : __('Abierta') }}</x-ui.badge></td>
                        <td class="text-right whitespace-nowrap">
                            <x-ui.button tamano="sm" variante="fantasma" wire:click="cambiarFecha({{ $p->id }})">{{ __('Cambiar fecha') }}</x-ui.button>
                            @if ($puedeProrrogar)
                                <x-ui.button tamano="sm" variante="secundario" wire:click="prorrogar({{ $p->id }})">{{ __('Prorrogar') }}</x-ui.button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif

    @if ($otorga)
        <x-ui.card :titulo="__('Prórrogas por estudiante')" :descripcion="__('Permiten inscribir solo a ese estudiante después de la fecha límite, con clave o a mano. Hay una por estudiante en el período: si ya tiene, se reemplaza.')">
            <form wire:submit="otorgarProrroga" class="mb-6 flex flex-col gap-4">
                <x-ui.field :etiqueta="__('Estudiante')" para="estudianteBuscar" error="estudianteId" :ayuda="__('Busque por carné, correo o nombre.')" requerido>
                    <x-ui.search id="estudianteBuscar" wire:model.live.debounce.300ms="estudianteBuscar" :placeholder="__('gs21004')" />
                </x-ui.field>
                @if ($candidatos->isNotEmpty())
                    <div class="flex flex-col gap-1">
                        @foreach ($candidatos as $c)
                            <label wire:key="candidato-{{ $c->id }}" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-zinc-50"><input type="radio" wire:model="estudianteId" value="{{ $c->id }}" class="text-marca-600 focus:ring-marca-600"> {{ $c->first_name }} {{ $c->last_name }} <span class="text-texto-suave">{{ $c->student_code ?? $c->institutional_email }}</span></label>
                        @endforeach
                    </div>
                @endif
                <div class="flex flex-wrap items-start gap-3">
                    <x-ui.field class="max-w-xs" :etiqueta="__('Puede inscribirse hasta el')" para="hastaEstudiante" error="hastaEstudiante" requerido>
                        <x-ui.input id="hastaEstudiante" type="date" wire:model="hastaEstudiante" />
                    </x-ui.field>
                    <x-ui.field class="min-w-64 flex-1" :etiqueta="__('Motivo')" para="motivoEstudiante" error="motivoEstudiante" requerido>
                        <x-ui.textarea id="motivoEstudiante" wire:model="motivoEstudiante" />
                    </x-ui.field>
                </div>
                <div><x-ui.button type="submit">{{ __('Otorgar prórroga') }}</x-ui.button></div>
            </form>

            <div class="mb-4 flex flex-wrap items-end gap-3">
                <x-ui.field class="min-w-64 flex-1" :etiqueta="__('Buscar prórroga')" para="buscarProrroga">
                    <x-ui.search id="buscarProrroga" wire:model.live.debounce.300ms="buscar" :placeholder="__('Nombre o correo del estudiante…')" />
                </x-ui.field>
                @if ($buscar !== '')
                    <x-ui.button tamano="sm" variante="fantasma" icono="x-mark" wire:click="limpiar">{{ __('Limpiar filtros') }}</x-ui.button>
                @endif
            </div>
            <x-ui.table :vacio="__('No hay prórrogas por estudiante en el período.')">
                <x-slot:cabecera><th>{{ __('Estudiante') }}</th><th>{{ __('Hasta') }}</th><th>{{ __('Motivo') }}</th><th>{{ __('Otorgada por') }}</th></x-slot:cabecera>
                @foreach ($prorrogas as $x)
                    <tr wire:key="prorroga-{{ $x->id }}">
                        <td>{{ $x->first_name }} {{ $x->last_name }}<p class="text-xs text-texto-suave">{{ $x->institutional_email }}</p></td>
                        <td>{{ $x->until->format('d/m/Y') }} @if (! $x->vigente)<x-ui.badge>{{ __('Vencida') }}</x-ui.badge>@endif</td>
                        <td class="text-texto-suave">{{ $x->reason }}</td>
                        <td>{{ $x->otorgada_por }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif

    <x-ui.modal nombre="fecha-departamento" :titulo="__('Fecha límite de inscripción')">
        <form wire:submit="guardarFecha" id="form-fecha-departamento" class="flex flex-col gap-4">
            <p class="text-sm text-texto-suave">{{ __('Los estudiantes de las carreras de este departamento podrán inscribirse hasta el final de ese día.') }}</p>
            <x-ui.field :etiqueta="__('Fecha límite')" para="fechaDepartamento" error="fecha" requerido>
                <x-ui.input id="fechaDepartamento" type="date" wire:model="fecha" />
            </x-ui.field>
        </form>
        <x-slot:pie>
            <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'fecha-departamento')">{{ __('Cancelar') }}</x-ui.button>
            <x-ui.button type="submit" form="form-fecha-departamento">{{ __('Guardar') }}</x-ui.button>
        </x-slot:pie>
    </x-ui.modal>

    @if ($puedeProrrogar)
        <x-ui.modal nombre="prorroga-departamento" :titulo="__('Prorrogar la inscripción del departamento')">
            <form wire:submit="guardarProrroga" id="form-prorroga-departamento" class="flex flex-col gap-4">
                <p class="text-sm text-texto-suave">{{ __('La fecha original se conserva; la prórroga queda registrada en la auditoría con su motivo.') }}</p>
                <x-ui.field :etiqueta="__('Nueva fecha límite')" para="hastaDepartamento" error="hasta" requerido>
                    <x-ui.input id="hastaDepartamento" type="date" wire:model="hasta" />
                </x-ui.field>
                <x-ui.field :etiqueta="__('Motivo')" para="motivoDepartamento" error="motivo" requerido>
                    <x-ui.textarea id="motivoDepartamento" wire:model="motivo" />
                </x-ui.field>
            </form>
            <x-slot:pie>
                <x-ui.button variante="secundario" x-on:click="$dispatch('cerrar-modal', 'prorroga-departamento')">{{ __('Cancelar') }}</x-ui.button>
                <x-ui.button type="submit" form="form-prorroga-departamento">{{ __('Prorrogar') }}</x-ui.button>
            </x-slot:pie>
        </x-ui.modal>
    @endif
</div>
