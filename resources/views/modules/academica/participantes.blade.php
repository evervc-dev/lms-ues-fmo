{{-- Vista de sección › Participantes (RF36). El enlace al perfil sale solo donde hay vínculo (ADR-056). --}}
<div class="flex flex-col gap-5">
    <x-seccion.encabezado :resumen="$resumen" :pestanas="$pestanas" />

    <div class="grid gap-5 lg:grid-cols-2">
        @foreach ([
            ['titulo' => __('Docentes'), 'personas' => $docentes, 'vacio' => __('La sección no tiene docentes asignados.')],
            ['titulo' => __('Coordinación de la carrera'), 'personas' => $coordinadores, 'vacio' => __('Ninguna carrera con esta asignatura tiene coordinador asignado.')],
            ['titulo' => __('Estudiantes (:n)', ['n' => $estudiantes->count()]), 'personas' => $estudiantes, 'vacio' => __('Aún no hay estudiantes inscritos.')],
        ] as $grupo)
            <x-ui.card :titulo="$grupo['titulo']" :class="$loop->last ? 'lg:col-span-2' : ''">
                @if ($grupo['personas']->isEmpty())
                    <p class="text-sm text-texto-suave">{{ $grupo['vacio'] }}</p>
                @else
                    <ul @class(['grid gap-1', 'sm:grid-cols-2 lg:grid-cols-3' => $loop->last])>
                        @foreach ($grupo['personas'] as $p)
                            <li wire:key="participante-{{ $loop->parent->index }}-{{ $p->id }}" class="flex items-center gap-3 rounded-lg px-2 py-1.5 text-sm hover:bg-zinc-50">
                                <x-ui.avatar :iniciales="mb_strtoupper(mb_substr($p->first_name, 0, 1).mb_substr($p->last_name, 0, 1))" :foto="$p->avatar_url" tamano="sm" />
                                @if ($p->visible)
                                    <a href="{{ route('personas.show', ['usuario' => $p->id]) }}" wire:navigate class="font-medium text-texto hover:text-marca-700 hover:underline">{{ $p->first_name }} {{ $p->last_name }}</a>
                                @else
                                    <span class="font-medium text-texto">{{ $p->first_name }} {{ $p->last_name }}</span>
                                @endif
                                @isset($p->is_responsible)
                                    @if ($p->is_responsible)<x-ui.badge>{{ __('Responsable') }}</x-ui.badge>@endif
                                @endisset
                                @isset($p->carrera)
                                    <span class="text-xs text-texto-suave">{{ $p->carrera }}</span>
                                @endisset
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endforeach
    </div>
</div>
