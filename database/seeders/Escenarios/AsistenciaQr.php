<?php

namespace Database\Seeders\Escenarios;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Toma de asistencia con QR (RF19, RF20, RF27, RF28).
 *
 * Deja la clase de hoy con la sesión **abierta ahora mismo** y su QR rotando, para que el docente la
 * proyecte y los estudiantes la marquen desde el teléfono. Siembra además tres clases pasadas con sus
 * registros, porque sin historial no se puede ver «Mi asistencia» ni dispararse la alerta de
 * inasistencias: al último estudiante del reparto se le deja con tres ausencias seguidas.
 */
class AsistenciaQr extends Escenario
{
    public static function clave(): string
    {
        return 'asistencia-qr';
    }

    public static function titulo(): string
    {
        return 'Toma de asistencia con QR';
    }

    public static function descripcion(): string
    {
        return 'Clase de hoy abierta con su QR rotando, tres clases pasadas con historial y un estudiante al borde de la alerta por inasistencias.';
    }

    public static function roles(): array
    {
        return [
            'docente' => self::rol('Docente de la sección: abre la sesión y proyecta el QR'),
            'estudiante' => self::rol('Estudiantes que marcan asistencia desde el teléfono', 1, 10),
            'jefe_departamento' => self::rol('Jefe del departamento: ve la asistencia de sus secciones', 0, 1),
            'coordinador' => self::rol('Coordinador de la carrera', 0, 1),
            'administrador' => self::rol('Administrador: configura el área de asistencia', 0, 1),
            'soporte_tecnico' => self::rol('Soporte: resuelve los cambios de dispositivo', 0, 1),
        ];
    }

    public static function rfs(): array
    {
        return ['RF19', 'RF20', 'RF22', 'RF27', 'RF28'];
    }

    public function preparar(Reparto $reparto, Esqueleto $esqueleto): Resultado
    {
        $resultado = new Resultado;
        $materia = $esqueleto->materia('PRN115');
        $seccion = $esqueleto->seccion($materia);
        $docente = $esqueleto->docente($reparto->uno('docente'), $seccion);
        $computo = $esqueleto->computoDeHoy($seccion);
        $area = (int) DB::table('attendance_areas')->where('is_active', true)->value('id');

        $matriculas = [];
        foreach ($reparto->varios('estudiante') as $estudiante) {
            $matriculas[$estudiante] = $esqueleto->matricular($estudiante, $seccion);
        }
        $relleno = $esqueleto->relleno($reparto->relleno, $seccion);

        // Tres clases pasadas, para que haya historial y porcentaje de asistencia.
        $ausente = array_key_last($matriculas);
        foreach ([6, 4, 2] as $diasAtras) {
            $abierta = Carbon::now()->subDays($diasAtras)->setTime(7, 0);
            $sesionId = $esqueleto->registro->insertar('attendance_sessions', [
                'ulid' => (string) Str::ulid(),
                'section_id' => $seccion->id,
                'grading_component_id' => $computo->id,
                'area_id' => $area,
                'session_date' => $abierta->toDateString(),
                'modality' => 'in_person',
                'status' => 'closed',
                'opened_at' => $abierta,
                'qr_validity_minutes' => 15,
                'qr_rotation_seconds' => 10,
                'late_after_minutes' => 15,
                'closed_at' => $abierta->copy()->addMinutes(15),
                'opened_by' => $docente,
            ] + $esqueleto->sellos($abierta));

            $filas = [];
            foreach ($matriculas as $estudiante => $matricula) {
                // El último queda ausente en las tres: así la alerta de inasistencias tiene de qué quejarse.
                $presente = $estudiante !== $ausente;
                $filas[] = $this->registro($sesionId, $seccion, $matricula, $presente ? 'present' : 'absent', $presente ? 'qr' : 'manual', $abierta, $esqueleto);
            }
            foreach ($relleno as $indice => $alumno) {
                $estado = $indice % 9 === 0 ? 'absent' : ($indice % 7 === 0 ? 'late' : 'present');
                $filas[] = $this->registro($sesionId, $seccion, $alumno['matricula'], $estado, $estado === 'absent' ? 'manual' : 'qr', $abierta, $esqueleto);
            }
            $esqueleto->registro->insertarVarias('attendance_records', $filas);
        }

        // La clase de hoy: abierta ahora, con tres tokens de QR (el vigente y dos vencidos).
        $abiertaHoy = Carbon::now()->subMinutes(3);
        $ulidHoy = (string) Str::ulid();
        $sesionHoy = $esqueleto->registro->insertar('attendance_sessions', [
            'ulid' => $ulidHoy,
            'section_id' => $seccion->id,
            'grading_component_id' => $computo->id,
            'area_id' => $area,
            'session_date' => Carbon::now()->toDateString(),
            'modality' => 'in_person',
            'status' => 'open',
            'opened_at' => $abiertaHoy,
            'qr_validity_minutes' => 15,
            'qr_rotation_seconds' => 10,
            'late_after_minutes' => 15,
            'opened_by' => $docente,
        ] + $esqueleto->sellos($abiertaHoy));

        // Los tokens del QR no se siembran: AsistenciaQrService los emite al pedir el QR, uno por ventana
        // de rotación. Sembrarlos solo dejaría tokens vencidos que nadie usa.

        $this->enlaces($resultado, $reparto, $seccion, $ulidHoy);

        return $resultado
            ->dato('seccion_id', $seccion->id)
            ->dato('sesion_id', $sesionHoy)
            ->dato('sesion_ulid', $ulidHoy)
            ->dato('materia', $materia->code)
            ->dato('clave_inscripcion', $seccion->clave)
            ->nota("Sección {$materia->code}-01 de «{$materia->name}», lunes y miércoles de 07:00 a 08:40.")
            ->nota('La sesión de hoy está ABIERTA desde hace 3 minutos; el QR se genera al abrir la proyección y rota cada 10 segundos.')
            ->nota('Hay 3 clases pasadas con historial; al último estudiante del reparto se le dejó con 3 ausencias.')
            ->nota('El código temporal del RF28 vive 30 segundos, así que no se siembra: el docente lo genera en vivo desde la sesión.')
            ->nota("Clave de inscripción de la materia: {$seccion->clave}");
    }

    /**
     * @return array<string, mixed>
     */
    private function registro(int $sesionId, object $seccion, int $matriculaId, string $estado, string $metodo, Carbon $abierta, Esqueleto $esqueleto): array
    {
        $presente = $estado !== 'absent';

        return [
            'session_id' => $sesionId,
            'section_id' => $seccion->id,
            'enrollment_id' => $matriculaId,
            'status' => $estado,
            'method' => $metodo,
            'recorded_at' => $abierta->copy()->addMinutes($estado === 'late' ? 18 : 4),
            'distance_m' => $presente && $metodo === 'qr' ? random_int(5, 120) : null,
            'accuracy_m' => $presente && $metodo === 'qr' ? random_int(4, 25) : null,
            'inside_area' => $presente && $metodo === 'qr' ? true : null,
        ] + $esqueleto->sellos($abierta);
    }

    private function enlaces(Resultado $resultado, Reparto $reparto, object $seccion, string $sesionHoy): void
    {
        $resultado
            ->enlace('docente', 'secciones.asistencia', ['seccion' => $seccion->ulid], 'Lista de sesiones de la sección')
            ->enlace('docente', 'secciones.asistencia.sesion', ['seccion' => $seccion->ulid, 'sesion' => $sesionHoy], 'La sesión abierta de hoy')
            ->enlace('docente', 'secciones.asistencia.proyectar', ['seccion' => $seccion->ulid, 'sesion' => $sesionHoy], 'QR a pantalla completa para proyectar')
            ->enlace('docente', 'secciones.estudiantes', ['seccion' => $seccion->ulid], 'Estudiantes y su porcentaje de asistencia')
            ->enlace('estudiante', 'asistencia.escanear', [], 'Escanear el QR (desde el teléfono)')
            ->enlace('estudiante', 'mi-asistencia', [], 'Mi asistencia, con el historial de las 3 clases')
            ->enlace('estudiante', 'perfil.dispositivo', [], 'Registrar el teléfono, si aún no lo hizo');

        if ($reparto->unoONulo('jefe_departamento') !== null) {
            $resultado->enlace('jefe_departamento', 'reportes', [], 'Reportes del departamento, con el de riesgo por inasistencias');
        }
        if ($reparto->unoONulo('coordinador') !== null) {
            $resultado->enlace('coordinador', 'secciones', [], 'Secciones de la carrera');
        }
        if ($reparto->unoONulo('administrador') !== null) {
            $resultado->enlace('administrador', 'areas-asistencia', [], 'Área del campus que valida el QR (RF26)');
            $resultado->enlace('administrador', 'asistencia.diagnostico', [], 'Diagnóstico de la asistencia');
        }
        if ($reparto->unoONulo('soporte_tecnico') !== null) {
            $resultado->enlace('soporte_tecnico', 'dispositivos', [], 'Solicitudes de cambio de dispositivo');
        }
    }
}
