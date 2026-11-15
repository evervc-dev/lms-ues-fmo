<?php

namespace Database\Seeders\Escenarios;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Programar la sesión virtual y que caiga en el calendario de los inscritos (RF21, RF06, RF32).
 *
 * Este escenario **no siembra la videoconferencia**: lo que se prueba es que el docente la programe y
 * que la invitación de Google Calendar les llegue a todos los matriculados, que es lo que pidió Ever el
 * 14/11/2026. Por eso las cuentas del reparto llevan el correo institucional real de cada tester: con
 * correos inventados el evento se crea igual y nadie recibe nada, y la prueba no prueba nada.
 *
 * `CalendarGateway::crearClase()` ya invita a los matriculados; hace falta que el docente haya conectado
 * su Google Calendar en Mi perfil y que `GOOGLE_CALENDAR_ENABLED=true` en el entorno.
 */
class SesionVirtualYCalendario extends Escenario
{
    public static function clave(): string
    {
        return 'sesiones-y-meet';
    }

    public static function titulo(): string
    {
        return 'Programar sesión virtual y verla en el calendario';
    }

    public static function descripcion(): string
    {
        return 'La sección lista para que el docente agende la clase virtual y la invitación de Meet llegue al calendario de todos los inscritos.';
    }

    public static function roles(): array
    {
        return [
            'docente' => self::rol('Docente: conecta su Google Calendar y agenda la clase virtual'),
            'estudiante' => self::rol('Estudiantes que deben recibir la invitación', 1, 10),
            'administrador' => self::rol('Administrador: revisa las integraciones y el calendario del período', 0, 1),
            'coordinador' => self::rol('Coordinador: fija fechas del calendario de su carrera', 0, 1),
        ];
    }

    public static function rfs(): array
    {
        return ['RF06', 'RF21', 'RF32'];
    }

    public static function rellenoSugerido(): int
    {
        return 5;
    }

    public function preparar(Reparto $reparto, Esqueleto $esqueleto): Resultado
    {
        $resultado = new Resultado;
        $materia = $esqueleto->materia('RED115');
        $seccion = $esqueleto->seccion($materia);
        $docente = $esqueleto->docente($reparto->uno('docente'), $seccion);
        $esqueleto->computos($seccion);
        $periodo = $seccion->periodo;

        $correos = [];
        foreach ($reparto->varios('estudiante') as $estudiante) {
            $esqueleto->matricular($estudiante, $seccion);
            $correos[] = (string) DB::table('users')->where('id', $estudiante)->value('institutional_email');
        }
        $esqueleto->relleno($reparto->relleno, $seccion);

        // Fechas del calendario del período: una global y, si hay coordinador, una de la carrera (ADR-068).
        $esqueleto->registro->insertar('period_calendar_events', [
            'period_id' => $periodo->id,
            'title' => "Semana de parciales · escenario {$esqueleto->codigo}",
            'starts_at' => Carbon::now()->addDays(7)->setTime(7, 0),
            'ends_at' => Carbon::now()->addDays(11)->setTime(18, 0),
        ] + $esqueleto->sellos());

        if ($reparto->unoONulo('coordinador') !== null) {
            $esqueleto->coordinador($reparto->uno('coordinador'));
            $esqueleto->registro->insertar('period_calendar_events', [
                'period_id' => $periodo->id,
                'career_id' => $esqueleto->carrera()->id,
                'title' => "Entrega de notas de la carrera · escenario {$esqueleto->codigo}",
                'starts_at' => Carbon::now()->addDays(14)->setTime(8, 0),
                'ends_at' => Carbon::now()->addDays(14)->setTime(17, 0),
            ] + $esqueleto->sellos());
        }

        // Un cambio de modalidad pedido y sin resolver: la clase del jueves pasaría a virtual (RF32).
        $esqueleto->registro->insertar('class_cancellations', [
            'scope' => 'section',
            'section_id' => $seccion->id,
            'date' => Carbon::now()->next(Carbon::THURSDAY)->toDateString(),
            'kind' => 'modality_change',
            'new_modality' => 'virtual',
            'reason' => "Prueba del escenario {$esqueleto->codigo}: la clase del jueves pasa a virtual.",
            'status' => 'requested',
            'requested_by' => $docente,
        ] + $esqueleto->sellos());

        $calendario = (bool) config('services.google.calendar.enabled', false);

        $resultado
            ->enlace('docente', 'perfil.google-calendar', [], 'Conectar Google Calendar (hágalo PRIMERO)')
            ->enlace('docente', 'secciones.asistencia', ['seccion' => $seccion->ulid], 'Desde aquí se abre la sesión virtual y se agenda el Meet')
            ->enlace('docente', 'secciones.participantes', ['seccion' => $seccion->ulid], 'Quiénes están matriculados (a ellos les llega la invitación)')
            ->enlace('docente', 'clases-canceladas', [], 'El cambio de modalidad del jueves, pendiente')
            ->enlace('docente', 'periodos.calendario', ['periodo' => $periodo->id], 'Calendario del período')
            ->enlace('estudiante', 'materias', [], 'Mis materias: debe aparecer la nueva sección')
            ->enlace('estudiante', 'mi-asistencia', [], 'Mi asistencia')
            ->enlace('estudiante', 'notificaciones', [], 'Notificaciones del LMS');

        if ($reparto->unoONulo('administrador') !== null) {
            $esqueleto->administrador($reparto->uno('administrador'));
            $resultado
                ->enlace('administrador', 'integraciones', [], 'Estado de las integraciones con Google')
                ->enlace('administrador', 'periodos.calendario', ['periodo' => $periodo->id], 'Calendario del período, con las fechas sembradas');
        }
        if ($reparto->unoONulo('coordinador') !== null) {
            $resultado->enlace('coordinador', 'periodos.calendario', ['periodo' => $periodo->id], 'Fechas de la carrera en el calendario');
        }

        $resultado
            ->dato('seccion_id', $seccion->id)
            ->dato('seccion_ulid', $seccion->ulid)
            ->dato('materia', $materia->code)
            ->dato('correos_invitados', $correos)
            ->dato('google_calendar_activo', $calendario)
            ->nota("Sección {$materia->code}-01 de «{$materia->name}», con ".count($correos).' estudiantes del equipo matriculados.')
            ->nota('La videoconferencia NO viene sembrada: la gracia es que el docente la programe y llegue la invitación.')
            ->nota('Hay un cambio de modalidad a virtual pedido para el próximo jueves, sin resolver.')
            ->nota('Fechas nuevas en el calendario del período: semana de parciales (en 7 días).');

        if (! $calendario) {
            $resultado->nota('⚠ GOOGLE_CALENDAR_ENABLED está apagado en este entorno: el evento se simula y NO se envía ninguna invitación.');
        }

        return $resultado;
    }
}
