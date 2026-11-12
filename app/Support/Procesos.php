<?php

namespace App\Support;

use Symfony\Component\Process\Process;

/**
 * Utilidades para procesos hijos del entorno de desarrollo (`php artisan dev`).
 */
class Procesos
{
    /**
     * Detiene un proceso **y todo lo que lanzó**. Detener solo al hijo directo deja vivos a sus descendientes:
     * `artisan serve` lanza `php -S` y `bun run dev` lanza `node vite`, que seguirían ocupando su puerto y harían
     * fallar el siguiente arranque.
     *
     * Los descendientes se recogen **antes** de detener al padre: cuando este muere, sus hijos pasan a colgar de
     * init y ya no hay forma de saber de quién eran. En Windows, Symfony ya mata el árbol entero.
     */
    public static function detener(Process $proceso, int $esperaSegundos = 5): void
    {
        // getPid() es null en cuanto el proceso deja de correr: hay que leerlo antes de stop().
        $pid = $proceso->getPid();
        $descendientes = $pid !== null && PHP_OS_FAMILY !== 'Windows' ? self::descendientes($pid) : [];
        $proceso->stop($esperaSegundos);

        if ($descendientes === [] || ! function_exists('posix_kill')) {
            return;
        }

        self::enviar($descendientes, SIGTERM);

        $limite = microtime(true) + $esperaSegundos;
        while (microtime(true) < $limite && self::vivos($descendientes) !== []) {
            usleep(100_000);
        }

        self::enviar(self::vivos($descendientes), SIGKILL);
    }

    /**
     * Pids de todos los descendientes de un proceso, de los más profundos a los más cercanos. Con el pid 0 o 1
     * devuelve vacío: sus descendientes son todo el sistema, incluida la sesión gráfica del usuario.
     *
     * @return array<int, int>
     */
    public static function descendientes(int $pid): array
    {
        if ($pid <= 1) {
            return [];
        }

        $tabla = new Process(['ps', '-eo', 'pid=,ppid=']);
        $tabla->run();
        if (! $tabla->isSuccessful()) {
            return [];
        }

        $hijosDe = [];
        foreach (preg_split('/\R/', trim($tabla->getOutput())) ?: [] as $linea) {
            if (preg_match('/^\s*(\d+)\s+(\d+)\s*$/', $linea, $m)) {
                $hijosDe[(int) $m[2]][] = (int) $m[1];
            }
        }

        $encontrados = [];
        $pendientes = [$pid];
        while ($pendientes !== []) {
            foreach ($hijosDe[array_pop($pendientes)] ?? [] as $hijo) {
                $encontrados[] = $hijo;
                $pendientes[] = $hijo;
            }
        }

        return array_reverse($encontrados);
    }

    /**
     * @param  array<int, int>  $pids
     * @return array<int, int> los que siguen vivos
     */
    private static function vivos(array $pids): array
    {
        return array_values(array_filter($pids, fn (int $pid) => posix_kill($pid, 0)));
    }

    /**
     * @param  array<int, int>  $pids
     */
    private static function enviar(array $pids, int $senal): void
    {
        foreach ($pids as $pid) {
            @posix_kill($pid, $senal);
        }
    }
}
