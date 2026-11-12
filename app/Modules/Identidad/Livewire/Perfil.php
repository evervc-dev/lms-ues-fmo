<?php

namespace App\Modules\Identidad\Livewire;

use App\Models\User;
use App\Modules\Identidad\Services\DispositivoService;
use App\Modules\Identidad\Services\PerfilService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Mi perfil (maqueta 16): Datos personales · Dispositivo de asistencia · Notificaciones · Sesiones, y la pestaña
 * Google Calendar del docente, que es otro componente (RF21).
 * Cada pestaña tiene su ruta del registro de vistas (perfil, perfil.dispositivo, perfil.notificaciones, perfil.sesiones).
 */
#[Layout('layouts::app')]
class Perfil extends Component
{
    use PaginaLivewire;
    use WithFileUploads;

    public string $pestana = 'datos';

    public string $motivo = '';

    /** @var TemporaryUploadedFile|null */
    public $justificacion = null;

    public function mount(string $pestana = 'datos'): void
    {
        $this->pestana = $pestana;
    }

    public function alternarCorreo(string $evento, PerfilService $perfil): void
    {
        $activo = $perfil->alternarCorreo(auth()->user(), $evento);
        $this->dispatch('aviso', tipo: 'exito', mensaje: $activo ? __('Recibirá ese aviso también por correo.') : __('Ese aviso ya no le llegará por correo; lo verá en la plataforma.'));
    }

    public function cerrarOtras(PerfilService $perfil): void
    {
        $n = $perfil->cerrarOtrasSesiones(auth()->user(), session()->getId());
        $this->dispatch('aviso', tipo: 'exito', mensaje: trans_choice('{0} No había otras sesiones abiertas.|{1} Se cerró 1 sesión.|[2,*] Se cerraron :count sesiones.', $n));
    }

    /**
     * Registra este teléfono (ADR-045): el token va a una cookie HttpOnly y Secure de 1 año y, por el evento del
     * navegador, a una copia en localStorage. El token no se guarda en el estado del componente.
     */
    public function registrarDispositivo(DispositivoService $dispositivos): void
    {
        $token = $dispositivos->registrar(auth()->user());

        cookie()->queue(cookie('device_token', $token, 525600, '/', null, true, true, false, 'lax'));
        $this->dispatch('dispositivo-registrado', token: $token);
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Teléfono registrado. Ya puede marcar asistencia con QR desde este teléfono.'));
    }

    public function solicitarCambio(DispositivoService $dispositivos): void
    {
        $this->validate([
            'motivo' => 'required|string|min:5|max:300',
            'justificacion' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [], ['motivo' => 'motivo', 'justificacion' => 'justificación']);

        $ruta = $this->justificacion?->store('justificaciones-dispositivo');
        $dispositivos->solicitarCambio(auth()->user(), $this->motivo, $ruta ?: null);

        $this->reset('motivo', 'justificacion');
        $this->dispatch('cerrar-modal', 'solicitar-cambio');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Solicitud enviada. Soporte le avisará cuando la resuelva.'));
    }

    /**
     * Pestañas de Mi perfil según los permisos. «Google Calendar» es otro componente (RF21) con su propia ruta.
     *
     * @return list<array{etiqueta: string, url: string, activa: bool}>
     */
    public static function pestanas(User $usuario, string $activa): array
    {
        return collect([
            ['clave' => 'datos', 'ruta' => 'perfil', 'etiqueta' => __('Datos personales'), 'visible' => true],
            ['clave' => 'dispositivo', 'ruta' => 'perfil.dispositivo', 'etiqueta' => __('Dispositivo de asistencia'), 'visible' => $usuario->checkPermissionTo('dispositivo.registrar')],
            ['clave' => 'notificaciones', 'ruta' => 'perfil.notificaciones', 'etiqueta' => __('Notificaciones'), 'visible' => $usuario->checkPermissionTo('notificaciones.preferencias')],
            ['clave' => 'sesiones', 'ruta' => 'perfil.sesiones', 'etiqueta' => __('Sesiones'), 'visible' => $usuario->checkPermissionTo('perfil.sesiones.cerrar')],
            ['clave' => 'google-calendar', 'ruta' => 'perfil.google-calendar', 'etiqueta' => __('Google Calendar'), 'visible' => $usuario->hasAnyPermission(['videoconferencias.gestionar', 'periodos.gestionar'])],
        ])->filter(fn ($p) => $p['visible'])
            ->map(fn ($p) => ['etiqueta' => $p['etiqueta'], 'url' => route($p['ruta']), 'activa' => $p['clave'] === $activa])->values()->all();
    }

    public function render(PerfilService $perfil): View
    {
        $usuario = auth()->user();
        // Las preferencias vienen filtradas por rol. La pestaña depende del permiso y no de que la lista traiga algo:
        // ocultarla cuando sale vacía la hacía desaparecer para el Administrador (H-13, QA 27/10), y la vista ya
        // explica cuándo no hay avisos configurables.
        $puedePreferencias = $usuario->checkPermissionTo('notificaciones.preferencias');
        $preferencias = $puedePreferencias && $this->pestana === 'notificaciones' ? $perfil->preferencias($usuario) : collect();

        return $this->pagina('modules.identidad.perfil', [
            'usuario' => $usuario,
            'pestanas' => self::pestanas($usuario, $this->pestana),
            'datos' => $this->pestana === 'datos' ? $perfil->datos($usuario) : null,
            'preferencias' => $preferencias,
            'sesiones' => $this->pestana === 'sesiones' ? $perfil->sesiones($usuario) : null,
            'dispositivo' => $this->pestana === 'dispositivo' ? $perfil->dispositivo($usuario) : null,
        ], titulo: __('Mi perfil'));
    }
}
