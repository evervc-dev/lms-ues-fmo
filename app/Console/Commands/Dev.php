<?php

namespace App\Console\Commands;

use App\Support\Procesos;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Entorno de desarrollo en una sola terminal: servidor web, workers de la cola, scheduler y Vite (y Reverb con
 * --reverb). Los workers son Horizon en Linux con Redis; en Windows o sin Redis, `queue:listen`, igual que los
 * scripts `dev` y `dev:win` de Composer. Si un proceso termina, se detienen todos.
 */
class Dev extends Command
{
    private const COLAS = 'notificaciones,default,google,importaciones,reportes';

    private const COLORES = ['cyan', 'magenta', 'yellow', 'green', 'blue'];

    protected $signature = 'dev {--reverb : Incluye el servidor de Reverb} {--dry-run : Muestra los comandos sin ejecutarlos}';

    protected $description = 'Levanta el servidor, los workers, el scheduler y Vite para desarrollo';

    /** @var array<string, string> nombre => texto de la línea incompleta que falta por imprimir */
    private array $pendientes = [];

    private bool $detener = false;

    public function handle(): int
    {
        $procesos = $this->procesos();

        if ($this->option('dry-run')) {
            foreach ($procesos as $nombre => $proceso) {
                $this->line("[{$nombre}] ".$proceso->getCommandLine());
            }

            return self::SUCCESS;
        }

        // Reverb no arranca si su puerto está ocupado y, como si un proceso termina se detiene todo, lo mejor es
        // comprobarlo antes de levantar nada y decir por qué, en vez de dejar un stack trace de 16 líneas.
        $puertoReverb = (int) config('reverb.servers.reverb.port');
        if ($this->option('reverb') && ! $this->puertoLibre($puertoReverb)) {
            $this->error("El puerto {$puertoReverb} de Reverb ya está en uso, así que no se levantó nada.");
            $this->line('Puede ser otro `php artisan dev`, el proxy de Coolify o un contenedor de Docker en esta máquina.');
            $this->line('Para ver quién lo ocupa: ss -lntp | grep :'.$puertoReverb);
            $this->line('Para usar otro puerto, en .env cambie los dos valores juntos, por ejemplo a '.($puertoReverb + 1).': REVERB_SERVER_PORT y REVERB_PORT.');

            return self::FAILURE;
        }

        if (extension_loaded('pcntl')) {
            $this->trap([SIGINT, SIGTERM], fn () => $this->detener = true);
        }

        $colores = [];
        foreach (array_keys($procesos) as $i => $nombre) {
            $colores[$nombre] = self::COLORES[$i % count(self::COLORES)];
        }
        foreach ($procesos as $nombre => $proceso) {
            $this->pendientes[$nombre] = '';
            $proceso->start(fn (string $tipo, string $datos) => $this->imprimir($nombre, $colores[$nombre], $datos));
        }
        $this->info('Entorno de desarrollo en marcha: '.implode(', ', array_keys($procesos)).'. Ctrl+C para detenerlo.');

        $codigo = self::SUCCESS;
        while (! $this->detener) {
            foreach ($procesos as $nombre => $proceso) {
                if (! $proceso->isRunning()) {
                    $this->error("[{$nombre}] terminó (código {$proceso->getExitCode()}); se detiene todo.");
                    $codigo = self::FAILURE;
                    break 2;
                }
            }
            usleep(100_000);
        }

        foreach ($procesos as $nombre => $proceso) {
            Procesos::detener($proceso);
            $this->imprimir($nombre, $colores[$nombre], "\n");
        }

        return $codigo;
    }

    /**
     * @return array<string, Process>
     */
    private function procesos(): array
    {
        $artisan = [PHP_BINARY, base_path('artisan')];
        $procesos = [
            'web' => [...$artisan, 'serve'],
            'workers' => $this->usaHorizon()
                ? [...$artisan, 'horizon']
                : [...$artisan, 'queue:listen', (string) config('queue.default'), '--queue='.self::COLAS, '--tries=3'],
            'scheduler' => [...$artisan, 'schedule:work'],
            'vite' => [(new ExecutableFinder)->find('bun', 'bun'), 'run', 'dev'],
        ];
        if ($this->option('reverb')) {
            $procesos['reverb'] = [...$artisan, 'reverb:start', '--debug'];
        }

        return array_map(fn (array $comando) => (new Process($comando, base_path()))->setTimeout(null), $procesos);
    }

    /**
     * Si el puerto se puede abrir en todas las interfaces, que es como lo abre Reverb.
     */
    private function puertoLibre(int $puerto): bool
    {
        $socket = @stream_socket_server("tcp://0.0.0.0:{$puerto}");
        if ($socket === false) {
            return false;
        }
        fclose($socket);

        return true;
    }

    private function usaHorizon(): bool
    {
        return PHP_OS_FAMILY !== 'Windows' && config('queue.default') === 'redis';
    }

    /** Imprime solo líneas completas, cada una con el nombre del proceso; lo que sobra espera al siguiente bloque. */
    private function imprimir(string $nombre, string $color, string $datos): void
    {
        $lineas = explode("\n", $this->pendientes[$nombre].str_replace("\r", '', $datos));
        $this->pendientes[$nombre] = array_pop($lineas);

        foreach ($lineas as $linea) {
            if (trim($linea) !== '') {
                $this->output->writeln("<fg={$color}>[{$nombre}]</> ".OutputFormatter::escape($linea));
            }
        }
    }
}
