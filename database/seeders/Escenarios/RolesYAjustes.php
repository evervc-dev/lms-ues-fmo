<?php

namespace Database\Seeders\Escenarios;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Gestión de usuarios, roles, dispositivos y ajustes (RF03, RF04, RF05, RF24, RF26, RF27).
 *
 * Siembra los casos que el Administrador y el Soporte tienen que resolver: una cuenta que entró con
 * Google y quedó sin rol (ADR-038), una cuenta desactivada, una persona con dos roles a la vez y dos
 * solicitudes de cambio de dispositivo esperando respuesta.
 *
 * No toca `system_settings`: esas claves son configuración real de la aplicación (el mes de inicio del
 * ciclo II, los topes de carga académica, el plazo de inscripción) y cambiarlas desde un escenario
 * alteraría el comportamiento de todo el entorno, no solo de la prueba.
 */
class RolesYAjustes extends Escenario
{
    public static function clave(): string
    {
        return 'roles-y-ajustes';
    }

    public static function titulo(): string
    {
        return 'Usuarios, roles, dispositivos y ajustes';
    }

    public static function descripcion(): string
    {
        return 'Una cuenta sin rol, una desactivada, alguien con dos roles y dos solicitudes de dispositivo por resolver.';
    }

    public static function roles(): array
    {
        return [
            'administrador' => self::rol('Administrador: asigna roles, activa cuentas y revisa la auditoría'),
            'soporte_tecnico' => self::rol('Soporte: resuelve los cambios de dispositivo', 0, 1),
            'docente' => self::rol('Docente al que se le dará también el rol de coordinador', 0, 1),
            'estudiante' => self::rol('Estudiante que pide cambio de dispositivo', 0, 2),
        ];
    }

    public static function rfs(): array
    {
        return ['RF03', 'RF04', 'RF05', 'RF24', 'RF26', 'RF27'];
    }

    public static function rellenoSugerido(): int
    {
        return 0;
    }

    public function preparar(Reparto $reparto, Esqueleto $esqueleto): Resultado
    {
        $resultado = new Resultado;
        $esqueleto->administrador($reparto->uno('administrador'));

        // Cuenta recién autorregistrada con Google, sin rol: el Administrador debe asignarle uno (ADR-038).
        $sinRol = $esqueleto->registro->insertar('users', [
            'institutional_email' => Str::lower($esqueleto->codigo).'-sinrol@ues.edu.sv',
            'first_name' => 'Nuevo',
            'last_name' => 'Sin Rol',
            'is_active' => true,
            'last_login_at' => Carbon::now()->subHours(2),
        ] + $esqueleto->sellos());

        // Cuenta desactivada: debe rechazarse al entrar y el Administrador puede reactivarla.
        $desactivada = $esqueleto->registro->insertar('users', [
            'institutional_email' => Str::lower($esqueleto->codigo).'-inactiva@ues.edu.sv',
            'first_name' => 'Cuenta',
            'last_name' => 'Desactivada',
            'is_active' => false,
            'deactivated_at' => Carbon::now()->subDays(3),
            'deactivated_by' => $reparto->uno('administrador'),
        ] + $esqueleto->sellos());

        // Intentos de inicio de sesión fallidos, para la pantalla de auditoría y el historial (RF05).
        $esqueleto->registro->insertarVarias('login_history', [
            [
                'user_id' => null,
                'attempted_email' => Str::lower($esqueleto->codigo).'-ajeno@gmail.com',
                'ip_address' => '10.10.7.21',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'result' => 'failure',
                'failure_reason' => 'domain',
                'occurred_at' => Carbon::now()->subHours(5),
            ],
            [
                'user_id' => $desactivada,
                'attempted_email' => Str::lower($esqueleto->codigo).'-inactiva@ues.edu.sv',
                'ip_address' => '10.10.7.22',
                'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64)',
                'result' => 'failure',
                'failure_reason' => 'inactive',
                'occurred_at' => Carbon::now()->subHours(1),
            ],
        ]);

        // Solicitudes de cambio de dispositivo sin resolver (RF27).
        $pendientes = 0;
        foreach ($reparto->varios('estudiante') as $indice => $estudiante) {
            $esqueleto->matricularSinSeccion($estudiante);
            $esqueleto->registro->insertar('device_change_requests', [
                'student_id' => $estudiante,
                'reason' => $indice === 0 ? 'Me robaron el teléfono en el bus.' : 'Cambié de teléfono porque se dañó la pantalla.',
                'status' => 'pending',
            ] + $esqueleto->sellos(Carbon::now()->subHours(3 + $indice)));
            $pendientes++;
        }

        $resultado
            ->enlace('administrador', 'usuarios', [], 'Usuarios: aquí están la cuenta sin rol y la desactivada')
            ->enlace('administrador', 'usuarios.show', ['usuario' => $sinRol], 'La cuenta sin rol')
            ->enlace('administrador', 'usuarios.roles', ['usuario' => $sinRol], 'Asignarle un rol')
            ->enlace('administrador', 'usuarios.show', ['usuario' => $desactivada], 'La cuenta desactivada')
            ->enlace('administrador', 'usuarios.crear', [], 'Crear un usuario a mano')
            ->enlace('administrador', 'usuarios.importar', [], 'Importar usuarios desde Excel')
            ->enlace('administrador', 'roles', [], 'Roles y permisos (RF03)')
            ->enlace('administrador', 'auditoria', [], 'Auditoría: los intentos fallidos y los cambios de rol (RF05)')
            ->enlace('administrador', 'areas-asistencia', [], 'Áreas de asistencia (RF26)')
            ->enlace('administrador', 'integraciones', [], 'Integraciones con Google')
            ->enlace('administrador', 'periodos', [], 'Períodos académicos')
            ->enlace('administrador', 'catalogo.estructura', [], 'Departamentos, carreras y planes');

        if ($reparto->unoONulo('soporte_tecnico') !== null) {
            $esqueleto->soporte($reparto->uno('soporte_tecnico'));
            $resultado->enlace('soporte_tecnico', 'dispositivos', [], "Solicitudes de dispositivo ({$pendientes} pendientes)");
        }
        if ($reparto->unoONulo('docente') !== null) {
            $resultado->enlace('administrador', 'usuarios.roles', ['usuario' => $reparto->uno('docente')], 'Darle también el rol de coordinador y ver el cambio en la auditoría');
        }
        if ($reparto->varios('estudiante') !== []) {
            $resultado->enlace('estudiante', 'perfil.dispositivo', [], 'Pedir el cambio de dispositivo y ver el estado');
            $resultado->enlace('estudiante', 'perfil', [], 'Mi perfil');
        }

        return $resultado
            ->dato('usuario_sin_rol', $sinRol)
            ->dato('usuario_desactivado', $desactivada)
            ->dato('solicitudes_dispositivo', $pendientes)
            ->nota('Cuenta sin rol sembrada: debe entrar al inicio con el aviso de «aún no tiene un rol asignado».')
            ->nota('Cuenta desactivada sembrada: al intentar entrar debe rechazarse y quedar en el historial.')
            ->nota("Hay {$pendientes} solicitudes de cambio de dispositivo esperando a Soporte.")
            ->nota('No se tocó ningún ajuste de system_settings: son configuración real del entorno.');
    }
}
