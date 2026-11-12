<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Asistencia\Services\CalendarConexionService;
use App\Modules\Asistencia\Services\CalendarGoogle;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Prueba de humo de Google Calendar (RF21, ADR-018): crea hoy, en el calendario de un docente que ya conectó su cuenta
 * en Mi perfil · Google Calendar, un evento de prueba con enlace de Meet. Solo cuentas @ues.edu.sv; el único invitado
 * es el propio docente (o los correos de --invitar, también del dominio).
 */
class ProbarCalendar extends Command
{
    protected $signature = 'google:calendar-prueba
        {correo : Correo institucional de un usuario que ya conectó Google Calendar}
        {--minutos=30 : Duración del evento}
        {--invitar=* : Otros correos del dominio a invitar}';

    protected $description = 'Crea hoy un evento de prueba con enlace de Meet en el Google Calendar de un usuario @ues.edu.sv';

    public function handle(CalendarConexionService $conexion, CalendarGoogle $calendario): int
    {
        $correo = mb_strtolower(trim((string) $this->argument('correo')));
        $invitados = array_map(fn ($c) => mb_strtolower(trim((string) $c)), (array) $this->option('invitar'));

        foreach ([$correo, ...$invitados] as $c) {
            if (! $conexion->delDominio($c)) {
                $this->error(__('Solo se permiten cuentas @:dominio: :correo.', ['dominio' => $conexion->dominio(), 'correo' => $c]));

                return self::FAILURE;
            }
        }
        if (blank(config('services.google.client_id')) || blank(config('services.google.client_secret'))) {
            $this->error(__('Falta el cliente OAuth de Google (GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET).'));

            return self::FAILURE;
        }

        $usuario = User::query()->where('institutional_email', $correo)->first();
        if ($usuario === null || ! $conexion->conectado($usuario)) {
            $this->error(__(':correo no ha conectado Google Calendar: entre al LMS con esa cuenta y use Mi perfil › Google Calendar › Conectar con Google.', ['correo' => $correo]));

            return self::FAILURE;
        }

        $minutos = max(5, min(240, (int) $this->option('minutos')));
        $inicio = $this->inicioDeHoy($minutos);

        try {
            $evento = $calendario->crearClase($usuario, __('Prueba del LMS UES · Google Calendar'), $inicio, $minutos, array_values(array_unique([$correo, ...$invitados])));
        } catch (Throwable $excepcion) {
            $this->error(__('Google rechazó la solicitud: :error', ['error' => $excepcion->getMessage()]));

            return self::FAILURE;
        }
        if ($evento === null) {
            $this->error(__('Google revocó el permiso de :correo; vuelva a conectar Google Calendar.', ['correo' => $correo]));

            return self::FAILURE;
        }

        $this->table([__('Dato'), __('Valor')], [
            [__('Evento'), $evento['evento']],
            [__('Inicio'), $inicio->format('d/m/Y H:i')],
            [__('Duración'), __(':n min', ['n' => $minutos])],
            [__('Meet'), $evento['meet'] ?? __('(Google no devolvió enlace)')],
        ]);
        $this->info(__('Evento creado en el calendario de :correo.', ['correo' => $correo]));

        return self::SUCCESS;
    }

    /**
     * La próxima media hora de hoy; si ya no cabe antes de medianoche, empieza en 5 minutos.
     */
    private function inicioDeHoy(int $minutos): Carbon
    {
        $ahora = now();
        $inicio = $ahora->copy()->addMinutes(30 - $ahora->minute % 30)->startOfMinute();

        return $inicio->copy()->addMinutes($minutos)->isSameDay($ahora) ? $inicio : $ahora->copy()->addMinutes(5)->startOfMinute();
    }
}
