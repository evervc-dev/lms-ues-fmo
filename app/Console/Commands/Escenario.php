<?php

namespace App\Console\Commands;

use App\Modules\Identidad\Services\EscenarioService;
use Database\Seeders\Escenarios\Tester;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;
use Throwable;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Escenarios de prueba desde la terminal.
 *
 * Sin opciones pregunta el flujo y el reparto; con ellas no pregunta nada, que es lo que necesita CI o
 * un script que prepare varios escenarios de una vez.
 *
 *   php artisan escenario:preparar asistencia-qr --docente=vc21033 --estudiante=rr19118,hp19012 --relleno=25
 *   php artisan escenario:listar
 *   php artisan escenario:limpiar E7K2M
 */
class Escenario extends Command
{
    protected $signature = 'escenario
        {accion=preparar : preparar, listar o limpiar}
        {objetivo? : el flujo (al preparar) o el código del escenario (al limpiar)}
        {--administrador=* : DUE o correo de quien hace de Administrador}
        {--jefe=* : DUE o correo del Jefe de departamento}
        {--coordinador=* : DUE o correo del Coordinador de carrera}
        {--docente=* : DUE o correo de los docentes}
        {--estudiante=* : DUE o correo de los estudiantes}
        {--soporte=* : DUE o correo de Soporte técnico}
        {--relleno= : cuántos estudiantes inventados sembrar}
        {--notas= : nota del escenario}
        {--todos : al limpiar, limpia todos los escenarios preparados}';

    protected $description = 'Prepara, lista o limpia escenarios de prueba';

    /** Opción del comando => rol del escenario. */
    private const OPCIONES = [
        'administrador' => 'administrador',
        'jefe' => 'jefe_departamento',
        'coordinador' => 'coordinador',
        'docente' => 'docente',
        'estudiante' => 'estudiante',
        'soporte' => 'soporte_tecnico',
    ];

    public function handle(EscenarioService $escenarios): int
    {
        try {
            $escenarios->exigirHabilitado();
        } catch (Throwable $excepcion) {
            $this->error($excepcion->getMessage());

            return self::FAILURE;
        }

        return match ((string) $this->argument('accion')) {
            'preparar' => $this->preparar($escenarios),
            'listar' => $this->listar($escenarios),
            'limpiar' => $this->limpiar($escenarios),
            default => $this->invalida(),
        };
    }

    private function preparar(EscenarioService $escenarios): int
    {
        $catalogo = $escenarios->catalogo();
        $flujo = (string) ($this->argument('objetivo') ?? '');

        if ($flujo === '') {
            $flujo = select('¿Qué flujo quiere probar?', collect($catalogo)->map(fn (array $d) => $d['titulo'])->all());
        }

        if (! isset($catalogo[$flujo])) {
            $this->error("No existe el flujo «{$flujo}». Los que hay: ".implode(', ', array_keys($catalogo)).'.');

            return self::FAILURE;
        }

        $definicion = $catalogo[$flujo];
        $testers = $escenarios->testers();
        $reparto = [];
        $interactivo = true;

        foreach (self::OPCIONES as $opcion => $rol) {
            $valores = $this->opcionComoLista($opcion);

            if ($valores !== []) {
                $interactivo = false;
            }

            if (! isset($definicion['roles'][$rol])) {
                if ($valores !== []) {
                    $this->warn("El flujo «{$flujo}» no usa el rol «{$rol}»; se ignora --{$opcion}.");
                }

                continue;
            }

            $ids = [];
            foreach ($valores as $valor) {
                $persona = $testers->first(fn (object $t) => $t->due === $valor || $t->correo === $valor);

                if ($persona === null) {
                    $this->error("«{$valor}» no está en el catálogo de testers (database/seeders/data/testers.php).");

                    return self::FAILURE;
                }

                $ids[] = $persona->id;
            }

            if ($ids !== []) {
                $reparto[$rol] = $ids;
            }
        }

        // Nada por opciones: se pregunta rol por rol.
        if ($interactivo) {
            $opciones = $testers->mapWithKeys(fn (Tester $t) => [$t->id => "{$t->nombre} ({$t->due})"])->all();

            foreach ($definicion['roles'] as $rol => $regla) {
                $etiqueta = EscenarioService::ROLES[$rol].' · '.$regla['etiqueta'];
                $elegidos = multiselect(
                    label: $etiqueta,
                    options: $opciones,
                    required: $regla['minimo'] > 0,
                    hint: $regla['maximo'] === 1 ? 'Elija una persona.' : "De {$regla['minimo']} a {$regla['maximo']}.",
                );

                if ($elegidos !== []) {
                    $reparto[$rol] = array_map('intval', $elegidos);
                }
            }
        }

        $relleno = $this->option('relleno') !== null
            ? (int) $this->option('relleno')
            : ($interactivo ? (int) text('¿Cuántos estudiantes de relleno?', default: (string) $definicion['relleno']) : $definicion['relleno']);

        try {
            $escenario = $escenarios->preparar($flujo, $reparto, $relleno, null, $this->option('notas') ? (string) $this->option('notas') : null);
        } catch (Throwable $excepcion) {
            $this->error($excepcion->getMessage());

            return self::FAILURE;
        }

        $detalle = $escenarios->detalle($escenarios->porCodigo($escenario->code));

        $this->newLine();
        $this->info("Escenario {$escenario->code} · {$escenario->title}");
        $this->newLine();

        foreach ($detalle['resumen'] as $linea) {
            $this->line("  · {$linea}");
        }

        $this->newLine();
        $this->line('  Enlaces por rol:');
        foreach ($detalle['enlaces'] as $rol => $enlaces) {
            $this->line('  '.(EscenarioService::ROLES[$rol] ?? $rol).':');
            foreach ($enlaces as $enlace) {
                $this->line("    {$enlace['url']}");
            }
        }

        $this->newLine();
        $this->line('  Checklist: '.count($detalle['checklist']).' casos de '.implode(', ', $definicion['rfs']).'.');
        $this->line('  Página para compartir: '.route('dev.escenarios.show', ['codigo' => $escenario->code]));
        $this->newLine();

        return self::SUCCESS;
    }

    private function listar(EscenarioService $escenarios): int
    {
        $escenarios_ = $escenarios->lista(null);

        if ($escenarios_->isEmpty()) {
            $this->line('No hay escenarios preparados.');

            return self::SUCCESS;
        }

        $this->table(
            ['Código', 'Flujo', 'Estado', 'Casos', 'Pasan', 'Fallan', 'Preparado'],
            $escenarios_->map(function (stdClass $e): array {
                $conteo = DB::table('test_scenario_checks')->where('scenario_id', $e->id)
                    ->selectRaw("count(*) as total, sum(case when status = 'pass' then 1 else 0 end) as pasan, sum(case when status = 'fail' then 1 else 0 end) as fallan")
                    ->first();

                return [$e->code, $e->flow, $e->status === 'ready' ? 'preparado' : 'limpiado',
                    (int) $conteo->total, (int) $conteo->pasan, (int) $conteo->fallan,
                    Carbon::parse($e->created_at)->format('d/m H:i')];
            })->all(),
        );

        return self::SUCCESS;
    }

    private function limpiar(EscenarioService $escenarios): int
    {
        if ($this->option('todos')) {
            $total = 0;
            foreach ($escenarios->lista('ready') as $escenario) {
                $cuenta = $escenarios->limpiar((int) $escenario->id);
                $this->line("  {$escenario->code}: {$this->resumen($cuenta)}.");
                $total += $cuenta['borradas'] + $cuenta['retiradas'];
            }
            $this->info("Listo: {$total} filas borradas o retiradas.");

            return self::SUCCESS;
        }

        $codigo = (string) ($this->argument('objetivo') ?? '');

        if ($codigo === '') {
            $vivos = $escenarios->lista('ready');

            if ($vivos->isEmpty()) {
                $this->line('No hay escenarios que limpiar.');

                return self::SUCCESS;
            }

            $codigo = select('¿Cuál limpia?', $vivos->mapWithKeys(fn (stdClass $e) => [$e->code => "{$e->code} · {$e->title}"])->all());
        }

        $escenario = $escenarios->porCodigo($codigo);

        if ($escenario === null) {
            $this->error("No existe el escenario «{$codigo}».");

            return self::FAILURE;
        }

        $cuenta = $escenarios->limpiar((int) $escenario->id);
        $this->info("Escenario {$escenario->code} limpiado: {$this->resumen($cuenta)}.");

        return self::SUCCESS;
    }

    /**
     * El resultado de limpiar, en palabras. Lo retirado y lo conservado es lo que ADR-027 no deja
     * borrar: matrículas retiradas, cuentas desactivadas y los historiales, las notas y la asistencia.
     *
     * @param  array{borradas: int, retiradas: int, conservadas: int}  $cuenta
     */
    private function resumen(array $cuenta): string
    {
        return "{$cuenta['borradas']} filas borradas, {$cuenta['retiradas']} retiradas y {$cuenta['conservadas']} conservadas";
    }

    private function invalida(): int
    {
        $this->error('La acción debe ser preparar, listar o limpiar.');

        return self::FAILURE;
    }

    /**
     * @return array<int, string>
     */
    private function opcionComoLista(string $opcion): array
    {
        $valores = [];

        foreach ((array) $this->option($opcion) as $valor) {
            foreach (explode(',', (string) $valor) as $parte) {
                if (trim($parte) !== '') {
                    $valores[] = strtolower(trim($parte));
                }
            }
        }

        return array_values(array_unique($valores));
    }
}
