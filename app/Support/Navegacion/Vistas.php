<?php

namespace App\Support\Navegacion;

use App\Models\User;
use Illuminate\Routing\Route as RutaRegistrada;
use Illuminate\Support\Facades\Route;

/**
 * Registro de vistas (config/vistas.php, ADR-035): rutas provisionales, migas y pestañas de sección.
 */
class Vistas
{
    /** Equipo responsable por módulo (docs/00 Plan de desarrollo.md). */
    public const EQUIPOS = [
        'Identidad' => 'Equipo 1', 'Auditoria' => 'Equipo 1',
        'Academica' => 'Equipo 2', 'Reportes' => 'Equipo 2',
        'Contenidos' => 'Equipo 3', 'Comunicacion' => 'Equipo 3',
        'Evaluaciones' => 'Equipo 4', 'Calificaciones' => 'Equipo 4',
        'Asistencia' => 'Equipo 5',
    ];

    /** Hito de cada RF (docs/12 Desarrollo/Roadmap.md). */
    public const HITOS = [
        'H0' => ['RF01', 'RF03'],
        'H1' => ['RF04', 'RF05', 'RF06', 'RF07', 'RF08', 'RF11', 'RF15', 'RF16', 'RF19', 'RF23', 'RF26', 'RF27', 'RF28'],
        'H2' => ['RF09', 'RF12', 'RF13', 'RF14', 'RF17', 'RF20', 'RF24', 'RF29', 'RF30', 'RF32'],
        'H3' => ['RF10', 'RF18', 'RF21', 'RF22', 'RF25', 'RF31', 'RF33', 'RF34'],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function todas(): array
    {
        return config('vistas', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function vista(string $nombre): ?array
    {
        return self::todas()[$nombre] ?? null;
    }

    /**
     * Registra como «En construcción» las vistas que ningún módulo implementó todavía.
     * Se llama cuando todos los ServiceProvider ya cargaron sus rutas.
     */
    public static function registrarPendientes(): void
    {
        Route::getRoutes()->refreshNameLookups();

        foreach (self::todas() as $nombre => $vista) {
            if (Route::has($nombre)) {
                continue;
            }

            $middleware = ['web', 'auth'];
            if ($vista['permiso'] ?? null) {
                $middleware[] = 'permission:'.$vista['permiso'];
            }

            Route::get($vista['uri'], EnConstruccion::class)
                ->middleware($middleware)
                ->where($vista['donde'] ?? [])
                ->name($nombre);
        }

        Route::getRoutes()->refreshNameLookups();
    }

    public static function pendiente(string $nombre): bool
    {
        $ruta = Route::getRoutes()->getByName($nombre);

        return $ruta instanceof RutaRegistrada && $ruta->getActionName() === EnConstruccion::class;
    }

    public static function equipo(string $nombre): ?string
    {
        return self::EQUIPOS[self::vista($nombre)['modulo'] ?? ''] ?? null;
    }

    public static function hito(string $nombre): ?string
    {
        $rf = self::vista($nombre)['rf'][0] ?? null;
        foreach (self::HITOS as $hito => $rfs) {
            if (in_array($rf, $rfs, true)) {
                return $hito;
            }
        }

        return null;
    }

    /**
     * Migas de pan: Inicio › vistas padre › esta vista. `$actual` cambia la última por lo que de verdad se
     * muestra (el manual abierto en la Ayuda), para no repetir la de la vista (N-08, QA 27/10).
     *
     * @param  array<int, array{etiqueta: string, url?: string}>  $intermedias
     * @return array<int, array{etiqueta: string, url?: string}>
     */
    public static function migas(string $nombre, array $intermedias = [], ?string $actual = null): array
    {
        $vista = self::vista($nombre) ?? [];
        $migas = [['etiqueta' => __('Inicio'), 'url' => route('inicio')]];

        foreach ($vista['migas'] ?? [] as $padre) {
            $migas[] = ['etiqueta' => __(self::vista($padre)['titulo'] ?? $padre), 'url' => Route::has($padre) ? route($padre) : null];
        }

        return [...$migas, ...$intermedias, ['etiqueta' => $actual ?? __($vista['titulo'] ?? $nombre)]];
    }

    /**
     * Pestañas de la Vista de sección que el usuario puede ver (docs/13 Layout/Vista de sección.md).
     *
     * @return array<int, array{nombre: string, etiqueta: string, url: string, activa: bool}>
     */
    public static function pestanas(User $usuario, string $seccion, string $actual): array
    {
        $activa = self::vista($actual)['pestana'] ?? $actual;
        $pestanas = [];

        foreach (self::todas() as $nombre => $vista) {
            if (! ($vista['seccion'] ?? false) || isset($vista['pestana'])) {
                continue;
            }
            $permisos = explode('|', (string) $vista['permiso']);
            if (! collect($permisos)->contains(fn (string $permiso) => $usuario->checkPermissionTo($permiso))) {
                continue;
            }
            $pestanas[] = ['nombre' => $nombre, 'etiqueta' => __($vista['titulo']), 'url' => route($nombre, ['seccion' => $seccion]), 'activa' => $nombre === $activa];
        }

        return $pestanas;
    }
}
