<?php

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Support\Alcance;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Integraciones (RNF09): Google Workspace y correo institucional, de solo lectura. Las credenciales salen del `.env`
 * (`config/services.php` y `config/mail.php`), como decidió el equipo en RF01 (Bitácora del 17/10/2026): esta
 * pantalla muestra lo que está en uso para que el Administrador sepa si falta algo, sin revelar los secretos. Se
 * cambian en el servidor y se verifican con `php artisan google:verificar`.
 */
class IntegracionService
{
    public function __construct(private Alcance $alcance) {}

    /**
     * Estado de cada integración: `ok` (configurada), `revisar` (tiene datos, pero así no funciona) o `pendiente`.
     *
     * @return array<string, array{titulo: string, configurada: bool, estado: string, aviso: ?string, campos: list<array{etiqueta: string, variable: string, valor: string, ok: bool}>}>
     */
    public function listar(User $admin): array
    {
        $this->alcance->exigir($admin, 'integraciones.gestionar');

        $integraciones = [
            'google_workspace' => [__('Google Workspace'), [
                $this->campo(__('ID de cliente OAuth'), 'GOOGLE_CLIENT_ID', config('services.google.client_id')),
                $this->secreto(__('Secreto del cliente'), 'GOOGLE_CLIENT_SECRET', config('services.google.client_secret')),
                $this->campo(__('URI de redirección'), 'GOOGLE_REDIRECT_URI', config('services.google.redirect')),
                $this->campo(__('Dominio permitido'), 'GOOGLE_ALLOWED_DOMAIN', config('services.google.allowed_domain')),
            ]],
            'correo' => [__('Correo institucional (SMTP)'), [
                $this->campo(__('Servidor'), 'MAIL_HOST', config('mail.mailers.smtp.host')),
                $this->campo(__('Puerto'), 'MAIL_PORT', config('mail.mailers.smtp.port')),
                $this->campo(__('Usuario'), 'MAIL_USERNAME', config('mail.mailers.smtp.username'), opcional: true),
                $this->secreto(__('Contraseña'), 'MAIL_PASSWORD', config('mail.mailers.smtp.password'), opcional: true),
                $this->campo(__('Remitente'), 'MAIL_FROM_ADDRESS', config('mail.from.address')),
            ]],
        ];

        $avisoCorreo = $this->problemaDelCorreo();

        return collect($integraciones)->map(function (array $i, string $clave) use ($avisoCorreo): array {
            $completa = collect($i[1])->every(fn (array $c) => $c['ok']);
            $aviso = $clave === 'correo' ? $avisoCorreo : null;

            return [
                'titulo' => $i[0], 'campos' => $i[1], 'configurada' => $completa && $aviso === null,
                'estado' => ! $completa ? 'pendiente' : ($aviso !== null ? 'revisar' : 'ok'), 'aviso' => $aviso,
            ];
        })->all();
    }

    /**
     * Por qué el correo, aun con datos, no saldría (QA admin 21/11): el mailer no envía (`log`, `array`) o el servidor es
     * local (127.0.0.1) fuera del entorno local, donde en Railway no hay servidor de correo.
     */
    public function problemaDelCorreo(): ?string
    {
        $mailer = (string) config('mail.default');
        if (in_array($mailer, ['log', 'array'], true)) {
            return __('El correo está en modo «:m»: los avisos se guardan en el registro y no se envían.', ['m' => $mailer]);
        }
        $host = mb_strtolower(trim((string) config('mail.mailers.smtp.host')));
        if ($mailer === 'smtp' && in_array($host, ['127.0.0.1', 'localhost', '::1', '0.0.0.0'], true) && ! app()->environment('local')) {
            return __('El servidor de correo es local (:h) y en este servidor no hay uno: los avisos por correo probablemente no salen.', ['h' => $host]);
        }

        return null;
    }

    /**
     * Envía un correo de prueba al Administrador, en el momento (no en cola), para que vea enseguida si falla.
     *
     * @throws ValidationException con el error del servidor de correo.
     */
    public function enviarCorreoDePrueba(User $admin): void
    {
        $this->alcance->exigir($admin, 'integraciones.gestionar');

        try {
            Mail::raw(__('Este es un correo de prueba del LMS UES FMO. Si lo recibió, el envío de avisos por correo funciona.'),
                fn ($mensaje) => $mensaje->to($admin->institutional_email)->subject(__('Correo de prueba · LMS UES FMO')));
        } catch (Throwable $excepcion) {
            throw ValidationException::withMessages(['correo' => __('No se pudo enviar: :e', ['e' => str($excepcion->getMessage())->limit(200)])]);
        }
    }

    /**
     * @return array{etiqueta: string, variable: string, valor: string, ok: bool}
     */
    private function campo(string $etiqueta, string $variable, mixed $valor, bool $opcional = false): array
    {
        $texto = trim((string) $valor);

        return ['etiqueta' => $etiqueta, 'variable' => $variable, 'valor' => $texto === '' ? __('Vacío') : $texto, 'ok' => $opcional || $texto !== ''];
    }

    /**
     * @return array{etiqueta: string, variable: string, valor: string, ok: bool}
     */
    private function secreto(string $etiqueta, string $variable, mixed $valor, bool $opcional = false): array
    {
        $definido = filled($valor);

        return ['etiqueta' => $etiqueta, 'variable' => $variable, 'valor' => $definido ? __('Definido (no se muestra)') : __('Vacío'), 'ok' => $opcional || $definido];
    }
}
