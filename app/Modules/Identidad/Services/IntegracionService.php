<?php

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Support\Alcance;

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
     * @return array<string, array{titulo: string, configurada: bool, campos: list<array{etiqueta: string, variable: string, valor: string, ok: bool}>}>
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

        return collect($integraciones)->map(fn (array $i) => [
            'titulo' => $i[0], 'campos' => $i[1], 'configurada' => collect($i[1])->every(fn (array $c) => $c['ok']),
        ])->all();
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
