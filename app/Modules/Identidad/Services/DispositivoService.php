<?php

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Modules\Comunicacion\Services\NotificacionService;
use App\Modules\Identidad\Models\DeviceEvent;
use App\Modules\Identidad\Models\StudentDevice;
use App\Modules\Identidad\Notifications\DispositivoCambioAprobado;
use App\Modules\Identidad\Notifications\DispositivoCambioRechazado;
use App\Modules\Identidad\Notifications\DispositivoCambioResuelto;
use App\Modules\Identidad\Notifications\DispositivoCambioSolicitado;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Teléfono de asistencia (RF27, Flujo Registro y cambio de dispositivo, ADR-045).
 * El estudiante registra su teléfono (token de dispositivo) y solicita el cambio; Soporte aprueba (revoca el
 * anterior) o rechaza con motivo (que queda en `resolution_note` y se ve en «Resueltas»), y en ambos casos se
 * avisa al estudiante.
 *
 * Quienes atienden la bandeja (`dispositivos.gestionar`) también reciben aviso: cuando llega una solicitud
 * (`device_request.pending`) y cuando otra persona del equipo la resuelve (`device_request.resolved`).
 */
class DispositivoService
{
    public function __construct(private Alcance $alcance, private NotificacionService $notificaciones) {}

    /**
     * El estudiante registra su teléfono. Devuelve el token en claro, una única vez: el servidor solo guarda su
     * SHA-256 en `student_devices.credential_id` y quien llama debe entregarlo al navegador (cookie y localStorage).
     *
     * @throws ValidationException si ya tiene un dispositivo activo (debe solicitar el cambio a Soporte)
     */
    public function registrar(User $actor): string
    {
        $this->alcance->exigir($actor, 'dispositivo.registrar');

        $yaTiene = fn () => StudentDevice::query()->where('student_id', $actor->id)->where('status', 'active')->exists();
        $mensaje = ['dispositivo' => __('Ya tiene un teléfono registrado. Para cambiarlo, solicite el cambio a Soporte.')];

        if ($yaTiene()) {
            throw ValidationException::withMessages($mensaje);
        }

        $token = bin2hex(random_bytes(32));

        try {
            DbContext::run(function () use ($actor, $token) {
                $dispositivo = StudentDevice::create(['student_id' => $actor->id, 'credential_id' => StudentDevice::hashDe($token), 'status' => 'active']);
                DeviceEvent::create(['device_id' => $dispositivo->id, 'event_type' => 'registered', 'occurred_at' => now()]);
            }, 'device.registered');
        } catch (UniqueConstraintViolationException) {
            // Dos registros a la vez: gana el primero (índice único parcial de un solo activo).
            throw ValidationException::withMessages($mensaje);
        }

        return $token;
    }

    /**
     * Dispositivo activo del estudiante si alguno de los dos tokens (cookie o localStorage) coincide con su hash,
     * o null (ADR-045). Lo usa RF20 desde su Service: no exige permiso y no actualiza `last_used_at`.
     */
    public function validar(User $estudiante, ?string $tokenCookie, ?string $tokenLocal): ?StudentDevice
    {
        $hashes = collect([$tokenCookie, $tokenLocal])->filter(fn (?string $t) => filled($t))
            ->map(fn (string $t) => StudentDevice::hashDe($t))->unique()->values()->all();

        if ($hashes === []) {
            return null;
        }

        return StudentDevice::query()->where('student_id', $estudiante->id)->where('status', 'active')
            ->whereIn('credential_id', $hashes)->first();
    }

    /**
     * @return Collection<int, object>
     */
    public function solicitudes(User $actor, string $estado = 'pending'): Collection
    {
        $this->alcance->exigir($actor, 'dispositivos.gestionar');

        return DB::table('device_change_requests as r')->join('users as u', 'u.id', '=', 'r.student_id')
            ->leftJoin('student_profiles as p', 'p.user_id', '=', 'u.id')
            ->leftJoin('users as s', 's.id', '=', 'r.resolved_by')
            ->when($estado === 'pending', fn ($q) => $q->where('r.status', 'pending'), fn ($q) => $q->where('r.status', '!=', 'pending'))
            ->orderBy($estado === 'pending' ? 'r.created_at' : 'r.resolved_at', $estado === 'pending' ? 'asc' : 'desc')
            ->limit(100)
            ->get(['r.id', 'r.student_id', 'r.reason', 'r.status', 'r.created_at', 'r.resolved_at', 'r.resolution_note', 'r.justification_path',
                'u.first_name', 'u.last_name', 'u.institutional_email', 'p.student_code',
                DB::raw("s.first_name || ' ' || s.last_name as resuelta_por")]);
    }

    /**
     * Teléfonos y cambios anteriores del estudiante.
     *
     * @return array{dispositivos: Collection<int, object>, cambioReciente: bool}
     */
    public function historial(int $estudianteId): array
    {
        $dispositivos = DB::table('student_devices')->where('student_id', $estudianteId)->orderByDesc('created_at')
            ->get(['id', 'status', 'created_at', 'last_used_at', 'revoked_at']);

        return ['dispositivos' => $dispositivos, 'cambioReciente' => $this->cambioReciente($estudianteId)];
    }

    public function aprobar(User $actor, int $solicitudId): void
    {
        $this->alcance->exigir($actor, 'dispositivos.gestionar');
        $solicitud = $this->pendiente($solicitudId);

        DbContext::run(function () use ($actor, $solicitud) {
            $activos = DB::table('student_devices')->where('student_id', $solicitud->student_id)->where('status', 'active')->pluck('id');
            DB::table('student_devices')->whereIn('id', $activos)->update(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by' => $actor->id, 'updated_at' => now()]);
            foreach ($activos as $id) {
                DB::table('device_events')->insert(['device_id' => $id, 'event_type' => 'revoked', 'occurred_at' => now()]);
            }
            DB::table('device_change_requests')->where('id', $solicitud->id)->update(['status' => 'approved', 'resolved_by' => $actor->id, 'resolved_at' => now(), 'updated_at' => now()]);
        }, 'device.change_approved');

        $this->notificaciones->enviar([$solicitud->student_id], new DispositivoCambioAprobado($solicitud->id, route('perfil.dispositivo')));
        $this->avisarAlEquipo($actor, $solicitud, aprobada: true);
    }

    public function rechazar(User $actor, int $solicitudId, string $motivo): void
    {
        $this->alcance->exigir($actor, 'dispositivos.gestionar');
        $solicitud = $this->pendiente($solicitudId);

        DbContext::run(fn () => DB::table('device_change_requests')->where('id', $solicitud->id)
            ->update(['status' => 'rejected', 'resolved_by' => $actor->id, 'resolved_at' => now(), 'resolution_note' => $motivo, 'updated_at' => now()]), 'device.change_rejected', $motivo);

        $this->notificaciones->enviar([$solicitud->student_id], new DispositivoCambioRechazado($solicitud->id, $motivo, route('perfil.dispositivo')));
        $this->avisarAlEquipo($actor, $solicitud, aprobada: false);
    }

    /**
     * El estudiante pide cambiar de teléfono. Si ya cambió en los últimos 30 días, debe adjuntar una justificación.
     */
    public function solicitarCambio(User $estudiante, string $motivo, ?string $justificacion = null): void
    {
        $this->alcance->exigir($estudiante, 'dispositivo.solicitar-cambio');

        if (DB::table('device_change_requests')->where('student_id', $estudiante->id)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['motivo' => __('Ya tiene una solicitud pendiente. Espere la respuesta de Soporte.')]);
        }
        if ($this->cambioReciente($estudiante->id) && $justificacion === null) {
            throw ValidationException::withMessages(['justificacion' => __('Cambió de teléfono hace menos de 30 días: adjunte una justificación.')]);
        }

        $solicitudId = DbContext::run(fn () => DB::table('device_change_requests')->insertGetId([
            'student_id' => $estudiante->id, 'reason' => $motivo, 'justification_path' => $justificacion, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]), 'device.change_requested');

        $this->notificaciones->enviar(
            $this->equipoDeSoporte(),
            new DispositivoCambioSolicitado((int) $solicitudId, $estudiante->fullName(), $motivo, route('dispositivos')),
        );
    }

    /**
     * Avisa del cierre de la solicitud a los demás de la bandeja: quien la resolvió ya lo sabe.
     */
    private function avisarAlEquipo(User $actor, object $solicitud, bool $aprobada): void
    {
        $estudiante = User::query()->find($solicitud->student_id);

        $this->notificaciones->enviar(
            $this->equipoDeSoporte($actor->id),
            new DispositivoCambioResuelto((int) $solicitud->id, $estudiante?->fullName() ?? '', $actor->fullName(), $aprobada, route('dispositivos')),
        );
    }

    /**
     * Cuentas activas que atienden las solicitudes de dispositivo, sin la indicada.
     *
     * @return array<int, int>
     */
    private function equipoDeSoporte(?int $excepto = null): array
    {
        return User::query()->permission('dispositivos.gestionar')->where('is_active', true)
            ->when($excepto !== null, fn ($q) => $q->whereKeyNot($excepto))
            ->pluck('id')->all();
    }

    private function cambioReciente(int $estudianteId): bool
    {
        return DB::table('device_change_requests')->where('student_id', $estudianteId)->where('status', 'approved')
            ->where('resolved_at', '>=', now()->subDays(30))->exists();
    }

    private function pendiente(int $solicitudId): object
    {
        $solicitud = DB::table('device_change_requests')->where('id', $solicitudId)->first();
        if ($solicitud === null || $solicitud->status !== 'pending') {
            throw ValidationException::withMessages(['solicitud' => __('La solicitud ya fue resuelta.')]);
        }

        return $solicitud;
    }
}
