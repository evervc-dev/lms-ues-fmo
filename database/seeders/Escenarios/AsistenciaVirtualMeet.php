<?php

namespace Database\Seeders\Escenarios;

use App\Modules\Asistencia\Services\ReporteMeetService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Asistencia virtual con el reporte de Meet (RF21, RF19).
 *
 * La clase virtual ya se dio: quedó finalizada hace una hora, de 100 minutos, y su sesión de asistencia
 * está abierta esperando el reporte. Lo que se prueba es que el docente suba el CSV de Meet y que el
 * umbral del 70 % (ADR-003) reparta Presente y Ausente como debe.
 *
 * El escenario **no sube el reporte**, pero sí deja escrito el CSV exacto que hay que subir, con los
 * correos reales de los matriculados y unos minutos a propósito por encima y por debajo del umbral: 70
 * minutos de 100 es justo el límite, 69 no pasa.
 */
class AsistenciaVirtualMeet extends Escenario
{
    private const DURACION = 100;

    public static function clave(): string
    {
        return 'asistencia-virtual';
    }

    public static function titulo(): string
    {
        return 'Asistencia virtual con reporte de Meet';
    }

    public static function descripcion(): string
    {
        return 'Clase virtual de 100 minutos ya finalizada y su CSV de Meet listo para subir, con casos justo en el umbral del 70 %.';
    }

    public static function roles(): array
    {
        return [
            'docente' => self::rol('Docente: sube el reporte de Meet y confirma la asistencia'),
            'estudiante' => self::rol('Estudiantes que aparecen en el reporte', 2, 10),
            'jefe_departamento' => self::rol('Jefe del departamento', 0, 1),
        ];
    }

    public static function rfs(): array
    {
        return ['RF19', 'RF21', 'RF22'];
    }

    public static function rellenoSugerido(): int
    {
        return 6;
    }

    public function preparar(Reparto $reparto, Esqueleto $esqueleto): Resultado
    {
        $resultado = new Resultado;
        $materia = $esqueleto->materia('DPW115');
        $seccion = $esqueleto->seccion($materia);
        $docente = $esqueleto->docente($reparto->uno('docente'), $seccion);
        $computo = $esqueleto->computoDeHoy($seccion);

        $personas = [];
        foreach ($reparto->varios('estudiante') as $estudiante) {
            $esqueleto->matricular($estudiante, $seccion);
            $personas[] = DB::table('users')->where('id', $estudiante)->first(['institutional_email', 'first_name', 'last_name']);
        }
        $relleno = $esqueleto->relleno($reparto->relleno, $seccion);

        // La clase virtual ya terminó: empezó hace 2 h y duró 100 minutos.
        $inicio = Carbon::now()->subHours(2);
        $codigo = Str::lower(Str::random(3).'-'.Str::random(4).'-'.Str::random(3));
        $videoId = $esqueleto->registro->insertar('video_sessions', [
            'section_id' => $seccion->id,
            'meet_url' => "https://meet.google.com/{$codigo}",
            'topic' => "Clase virtual de {$materia->name} · escenario {$esqueleto->codigo}",
            'starts_at' => $inicio,
            'duration_minutes' => self::DURACION,
            'status' => 'finished',
        ] + $esqueleto->sellos($inicio));

        $sesionUlid = (string) Str::ulid();
        $esqueleto->registro->insertar('attendance_sessions', [
            'ulid' => $sesionUlid,
            'section_id' => $seccion->id,
            'grading_component_id' => $computo->id,
            'area_id' => null,
            'video_session_id' => $videoId,
            'session_date' => $inicio->toDateString(),
            'modality' => 'virtual',
            'status' => 'open',
            'opened_at' => $inicio,
            'qr_validity_minutes' => 15,
            'qr_rotation_seconds' => 10,
            'late_after_minutes' => 15,
            'opened_by' => $docente,
        ] + $esqueleto->sellos($inicio));

        $umbral = (int) ceil(self::DURACION * ReporteMeetService::UMBRAL / 100);

        return $resultado
            ->enlace('docente', 'secciones.asistencia', ['seccion' => $seccion->ulid], 'Clases virtuales: aquí se sube el reporte de Meet')
            ->enlace('docente', 'secciones.asistencia.sesion', ['seccion' => $seccion->ulid, 'sesion' => $sesionUlid], 'La sesión virtual, esperando el reporte')
            ->enlace('docente', 'secciones.estudiantes', ['seccion' => $seccion->ulid], 'Estudiantes y su asistencia')
            ->enlace('estudiante', 'mi-asistencia', [], 'Mi asistencia: debe cambiar cuando el docente confirme el reporte')
            ->enlace('estudiante', 'notificaciones', [], 'Notificaciones')
            ->enlace('jefe_departamento', 'reportes', [], 'Reportes del departamento')
            ->dato('seccion_id', $seccion->id)
            ->dato('seccion_ulid', $seccion->ulid)
            ->dato('sesion_ulid', $sesionUlid)
            ->dato('video_session_id', $videoId)
            ->dato('materia', $materia->code)
            ->dato('csv_meet', $this->csv($personas, $relleno, $umbral))
            ->nota("Sección {$materia->code}-01 de «{$materia->name}».")
            ->nota('La clase virtual empezó hace 2 horas, duró '.self::DURACION." minutos y ya está finalizada; el umbral son {$umbral} minutos.")
            ->nota('La sesión de asistencia está ABIERTA y sin reporte: el docente sube el CSV y confirma.')
            ->nota('El CSV de prueba está más abajo: el primer estudiante queda justo en el umbral y el segundo a un minuto de quedarse fuera.');
    }

    /**
     * El CSV que se espera subir, con los encabezados que reconoce el servicio.
     *
     * @param  array<int, object>  $personas
     * @param  array<int, array{usuario: int, matricula: int}>  $relleno
     */
    private function csv(array $personas, array $relleno, int $umbral): string
    {
        $lineas = ['Correo,Nombre,Apellido,Duración'];
        // Al primero se le dan los minutos exactos del umbral y al segundo uno menos: el caso de borde.
        $minutos = [$umbral, $umbral - 1];

        foreach ($personas as $indice => $persona) {
            $duracion = $minutos[$indice] ?? max(20, self::DURACION - ($indice * 7));
            $lineas[] = "{$persona->institutional_email},{$persona->first_name},{$persona->last_name},{$duracion} min";
        }

        foreach ($relleno as $indice => $alumno) {
            $correo = (string) DB::table('users')->where('id', $alumno['usuario'])->value('institutional_email');
            $duracion = $indice % 4 === 3 ? random_int(5, $umbral - 2) : random_int($umbral, self::DURACION);
            $lineas[] = "{$correo},Estudiante,Relleno {$indice},{$duracion} min";
        }

        // Un participante ajeno a la sección: debe quedar sin emparejar en la vista previa.
        $lineas[] = 'visitante.externo@gmail.com,Visitante,Externo,45 min';

        return implode("\n", $lineas);
    }
}
