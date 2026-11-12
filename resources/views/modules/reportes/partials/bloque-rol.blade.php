{{--
    Bloque del panel correspondiente a un rol (RF24). Variables: $rol, $b, $fecha, $alertas.
    Vive aparte para poder dibujarlo con acordeón (varios roles) o directo (uno solo) sin partir el
    componente entre dos @if: Blade empareja las etiquetas al compilar y se perdería el contenido.
--}}
@switch($rol)
    @case('estudiante')
        {{-- Sesión de asistencia abierta (RF24). El escáner solo en dispositivos táctiles:
             en un equipo de escritorio no hay cámara que apuntar al QR. --}}
        @if ($b['sesionAbierta'])
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-marca-600 p-4 text-white">
                <div class="min-w-0">
                    <p class="flex items-center gap-2 text-sm font-semibold">
                        <span class="size-2 animate-pulse rounded-full bg-white"></span>
                        {{ __('Asistencia abierta · :m', ['m' => $b['sesionAbierta']->code]) }}
                    </p>
                    <p class="truncate text-sm text-white/80">{{ $b['sesionAbierta']->name }}</p>
                </div>
                <x-ui.button variante="secundario" icono="qr-code" class="hidden pointer-coarse:inline-flex"
                    :href="route('asistencia.escanear')">{{ __('Escanear QR') }}</x-ui.button>
                <p class="text-sm text-white/80 pointer-coarse:hidden">{{ __('Abra esta pantalla en su teléfono para escanear el código.') }}</p>
            </div>
        @endif

        <div class="flex flex-col gap-4">
            <x-ui.card :titulo="__('Pendientes por fecha')" :descripcion="__('Lo que vence en las próximas dos semanas.')">
                @forelse ($b['pendientes'] as $p)
                    <a href="{{ route($p->assessment_type === 'quiz' ? 'secciones.cuestionarios.show' : 'secciones.tareas.show', ['seccion' => $p->seccion, $p->assessment_type === 'quiz' ? 'cuestionario' : 'tarea' => $p->ulid]) }}" wire:navigate class="flex items-center justify-between gap-3 border-b border-borde py-2 text-sm last:border-0 hover:text-marca-700">
                        <span><x-ui.badge color="marca">{{ $p->code }}</x-ui.badge> {{ $p->title }}</span>
                        <span class="whitespace-nowrap text-xs text-texto-suave">{{ __('vence :f', ['f' => $fecha($p->closes_at)->format('d/m H:i')]) }}</span>
                    </a>
                @empty
                    <p class="text-sm text-texto-suave">{{ __('No tiene entregas pendientes en las próximas dos semanas.') }}</p>
                @endforelse
            </x-ui.card>

            <x-ui.card :titulo="__('Mis materias')" :descripcion="trans_choice('{0} Sin materias este ciclo|{1} 1 materia inscrita|[2,*] :count materias inscritas', count($b['materias']))">
                @if (count($b['materias']))
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($b['materias'] as $m)
                            <div wire:key="materia-{{ $m->id }}">@include('modules.reportes.partials.materia', ['m' => $m])</div>
                        @endforeach
                    </div>
                @else
                    <x-ui.empty-state icono="academic-cap" :titulo="__('Aún no tiene materias inscritas')">
                        {{ __('Inscríbase con la clave que le dio su docente desde Mis materias.') }}
                    </x-ui.empty-state>
                @endif
            </x-ui.card>

            <div class="grid gap-4 lg:grid-cols-2">
                <x-ui.card :titulo="__('Agenda de la semana')" :descripcion="__('Clases, entregas y videoconferencias de los próximos 7 días.')">
                    @include('modules.reportes.partials.agenda', ['agenda' => $b['agenda']])
                </x-ui.card>

                <div class="flex flex-col gap-4">
                    <x-ui.card :titulo="__('Últimas notas')">
                        @forelse ($b['notas'] as $n)
                            <p class="flex justify-between border-b border-borde py-1.5 text-sm last:border-0"><span>{{ $n->code }} · {{ $n->title }}</span><span class="font-semibold">{{ number_format((float) $n->score, 1) }}</span></p>
                        @empty
                            <p class="text-sm text-texto-suave">{{ __('Aún no hay notas publicadas.') }}</p>
                        @endforelse
                    </x-ui.card>

                    <x-ui.card :titulo="__('Próximas videoconferencias')">
                        @forelse ($b['clasesVirtuales'] as $v)
                            <p class="py-1 text-sm">{{ $v->code }} · {{ $v->topic }} <span class="text-texto-suave">{{ $fecha($v->starts_at)->format('d/m H:i') }}</span></p>
                        @empty
                            <p class="text-sm text-texto-suave">{{ __('No hay clases virtuales programadas.') }}</p>
                        @endforelse
                    </x-ui.card>
                </div>
            </div>
        </div>
        @break

    @case('docente')
        <div class="grid gap-3 sm:grid-cols-3">
            <x-ui.stat :etiqueta="__('Pendientes de calificar')" :valor="$b['porCalificar']" icono="inbox-stack" :tono="$b['porCalificar'] ? 'aviso' : 'exito'" />
            <x-ui.stat :etiqueta="__('Matrícula y carga académica')" :valor="$b['matricula']" icono="users" :detalle="trans_choice('{1} en 1 sección|[0,*] en :count secciones', $b['secciones'])" />
            <x-ui.stat :etiqueta="__('Estudiantes en alerta')" :valor="$b['alertas']->count()" icono="exclamation-triangle" :tono="$b['alertas']->isNotEmpty() ? 'error' : 'exito'" />
        </div>
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <x-ui.card :titulo="__('Clases de hoy')">
                @forelse ($b['clasesHoy'] as $c)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-borde py-2 last:border-0">
                        <span class="text-sm"><span class="font-semibold">{{ $c->code }} · {{ $c->grupo }}</span> {{ $c->name }}<span class="block text-xs text-texto-suave">{{ substr($c->start_time, 0, 5) }}–{{ substr($c->end_time, 0, 5) }}{{ $c->aula ? ' · '.$c->aula : '' }}</span></span>
                        @if ($c->sesion_abierta)
                            <x-ui.button tamano="sm" :href="route('secciones.asistencia.sesion', ['seccion' => $c->ulid, 'sesion' => $c->sesion_abierta])">{{ __('Ver sesión abierta') }}</x-ui.button>
                        @else
                            <x-ui.button tamano="sm" icono="qr-code" :href="route('secciones.asistencia', ['seccion' => $c->ulid])">{{ __('Abrir asistencia') }}</x-ui.button>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-texto-suave">{{ __('Hoy no tiene clases.') }}</p>
                @endforelse
            </x-ui.card>
            <x-ui.card :titulo="__('Alertas de estudiantes')">
                @forelse ($b['alertas'] as $a)
                    <p class="flex justify-between py-1 text-sm"><span>{{ $a->first_name }} {{ $a->last_name }} · {{ $a->code }}</span><x-ui.badge :color="$a->alert_type === 'attendance_85' ? 'ambar' : 'rojo'">{{ $alertas[$a->alert_type] }}</x-ui.badge></p>
                @empty
                    <p class="text-sm text-texto-suave">{{ __('Sin alertas abiertas.') }}</p>
                @endforelse
            </x-ui.card>
        </div>
        @break

    @case('coordinador')
    @case('jefe_departamento')
    @case('administrador')
        {{-- Las etiquetas dicen de quién y de qué es cada cifra: antes «Asistencia» o «Matrícula» no
             aclaraban si eran de los docentes o de los estudiantes (revisión del rol Jefe de departamento). --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat :etiqueta="__('Matrículas activas de estudiantes')" :valor="number_format($b['matricula'])" icono="users"
                :detalle="trans_choice('{1} 1 sección|[0,*] :count secciones', $b['secciones']).' · '.__('una por estudiante y materia')" />
            <x-ui.stat :etiqueta="__('Asistencia de estudiantes')" :valor="$b['asistencia'] !== null ? $b['asistencia'].' %' : '—'" icono="clipboard-document-check"
                :tono="$b['asistencia'] !== null && $b['asistencia'] < 85 ? 'aviso' : 'exito'"
                :detalle="__('Marcas de los matriculados en estas secciones; no incluye la de los docentes')" />
            <x-ui.stat :etiqueta="__('Matrículas de estudiantes en riesgo')" :valor="$b['riesgo']" icono="exclamation-triangle" :tono="$b['riesgo'] ? 'error' : 'exito'"
                :detalle="__('Asistencia bajo el 75 % o riesgo de reprobar por notas')" />
            <x-ui.stat :etiqueta="__('Secciones sin docente asignado')" :valor="$b['sinDocente']" icono="user-plus" :tono="$b['sinDocente'] ? 'aviso' : 'exito'"
                :detalle="trans_choice('{0} Todas tienen docente|{1} 1 sección por asignar|[2,*] :count secciones por asignar', $b['sinDocente'])" />
        </div>
        @if ($rol === 'coordinador')
            <x-ui.card class="mt-4" :titulo="__('Pendientes de decisión')">
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-ui.button variante="secundario" :href="route('matricula.solicitudes')">{{ __('Cambios de grupo: :n', ['n' => $b['pendientes']['cambios']]) }}</x-ui.button>
                    <x-ui.button variante="secundario" :href="route('actas')">{{ __('Actas por validar: :n', ['n' => $b['pendientes']['actas']]) }}</x-ui.button>
                    <x-ui.button variante="secundario" :href="route('clases-canceladas')">{{ __('Cancelaciones: :n', ['n' => $b['pendientes']['cancelaciones']]) }}</x-ui.button>
                </div>
            </x-ui.card>
        @elseif ($rol === 'jefe_departamento')
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <x-ui.card :titulo="__('Estado del cierre')" :descripcion="__('Actas de las secciones de su departamento en el período en curso.')">
                    <p class="mb-2 text-sm">{{ __(':v de :t actas validadas por los coordinadores', ['v' => $b['actas']['validadas'], 't' => $b['actas']['total']]) }}</p>
                    <x-ui.progress :valor="$b['actas']['total'] ? 100 * $b['actas']['validadas'] / $b['actas']['total'] : 0" />
                    <x-ui.button class="mt-3" tamano="sm" variante="secundario" :href="route('cierre')">{{ __('Ir al cierre') }}</x-ui.button>
                </x-ui.card>
                <x-ui.card :titulo="__('Auditoría reciente')" :descripcion="__('Últimos cambios registrados en su departamento.')">
                    @forelse ($b['auditoria'] as $a)<p class="py-1 font-mono text-xs">{{ $fecha($a->occurred_at)->format('d/m H:i') }} · {{ $a->event_type ?: $a->entity_type }}</p>@empty<p class="text-sm text-texto-suave">{{ __('Sin eventos.') }}</p>@endforelse
                </x-ui.card>
            </div>
        @else
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <x-ui.stat :etiqueta="__('Estado del período')" :valor="$b['periodo']?->name ?? '—'" icono="calendar-days" :detalle="__('Cierres: :a de :d departamentos', ['a' => $b['cierres']['autorizados'], 'd' => $b['cierres']['departamentos']])" />
                <x-ui.stat :etiqueta="__('Actividad en la plataforma')" :valor="number_format($b['usuarios']['accesosHoy'])" icono="users" :detalle="__(':n cuentas activas', ['n' => number_format($b['usuarios']['activos'])])" />
                <x-ui.stat :etiqueta="__('Estado técnico')" :valor="$b['colas']" icono="queue-list" :tono="$b['colas'] ? 'error' : 'exito'" :detalle="__('trabajos fallidos en colas')" />
            </div>
        @endif
        @break

    @case('soporte_tecnico')
        <div class="grid gap-3 sm:grid-cols-3">
            <a href="{{ route('dispositivos') }}" wire:navigate><x-ui.stat :etiqueta="__('Solicitudes de teléfono pendientes')" :valor="$b['solicitudes']" icono="device-phone-mobile" :tono="$b['solicitudes'] ? 'aviso' : 'exito'" /></a>
            <a href="{{ route('asistencia.diagnostico') }}" wire:navigate><x-ui.stat :etiqueta="__('Rechazos de asistencia de hoy')" :valor="array_sum($b['rechazosHoy'])" icono="signal" :tono="array_sum($b['rechazosHoy']) ? 'aviso' : 'exito'" /></a>
            @can('usuarios.ver')
                <a href="{{ route('usuarios', ['rol' => 'estudiante', 'telefono' => 'sin']) }}" wire:navigate><x-ui.stat :etiqueta="__('Estudiantes sin teléfono')" :valor="$b['sinTelefono']" icono="exclamation-triangle" :detalle="__('Ver quiénes son')" /></a>
            @else
                <x-ui.stat :etiqueta="__('Estudiantes sin teléfono')" :valor="$b['sinTelefono']" icono="exclamation-triangle" />
            @endcan
        </div>

        {{-- Las tarjetas solas dejaban la pantalla casi vacía: debajo va el trabajo del día, para entrar a
             resolver sin pasar antes por el menú (revisión de diseño del 25/10/2026). --}}
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <x-ui.card :titulo="__('Solicitudes de teléfono por resolver')" :descripcion="__('De la más antigua a la más reciente.')">
                <x-slot:acciones><x-ui.button tamano="sm" variante="fantasma" :href="route('dispositivos')">{{ __('Ver todas') }}</x-ui.button></x-slot:acciones>
                @forelse ($b['pendientes'] as $p)
                    <a href="{{ route('dispositivos') }}" wire:navigate class="flex items-start justify-between gap-3 border-b border-borde py-2 text-sm last:border-0 hover:text-marca-700">
                        <span class="min-w-0">
                            <span class="font-medium">{{ $p->first_name }} {{ $p->last_name }}</span>
                            <span class="block truncate text-xs text-texto-suave">{{ $p->student_code ?? '' }} · {{ $p->reason }}</span>
                        </span>
                        <span class="whitespace-nowrap text-xs text-texto-suave">{{ $fecha($p->created_at)->diffForHumans() }}</span>
                    </a>
                @empty
                    <p class="text-sm text-texto-suave">{{ __('No hay solicitudes pendientes.') }}</p>
                @endforelse
            </x-ui.card>

            <x-ui.card :titulo="__('Últimos rechazos de asistencia')" :descripcion="__('Lo que el sistema no aceptó al marcar con el QR.')">
                @can('asistencia.diagnostico.ver')
                    <x-slot:acciones><x-ui.button tamano="sm" variante="fantasma" :href="route('asistencia.diagnostico')">{{ __('Ir al diagnóstico') }}</x-ui.button></x-slot:acciones>
                @endcan
                @forelse ($b['ultimosRechazos'] as $r)
                    <div class="flex items-center justify-between gap-3 border-b border-borde py-2 text-sm last:border-0">
                        <span class="min-w-0">
                            <span class="font-medium">{{ $r->first_name }} {{ $r->last_name }}</span>
                            <span class="block truncate text-xs text-texto-suave">{{ $r->student_code ?? '' }}{{ $r->asignatura ? ' · '.$r->asignatura : '' }} · {{ $fecha($r->occurred_at)->format('d/m H:i') }}</span>
                        </span>
                        <x-ui.badge color="rojo">{{ __(\App\Modules\Asistencia\Services\DiagnosticoService::MOTIVOS[$r->result] ?? $r->result) }}</x-ui.badge>
                    </div>
                @empty
                    <p class="text-sm text-texto-suave">{{ __('No hay rechazos registrados.') }}</p>
                @endforelse
            </x-ui.card>
        </div>
        @break
@endswitch
