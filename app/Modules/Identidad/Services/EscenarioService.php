<?php

namespace App\Modules\Identidad\Services;

use App\Support\DbContext;
use Database\Seeders\Escenarios\AsistenciaQr;
use Database\Seeders\Escenarios\AsistenciaVirtualMeet;
use Database\Seeders\Escenarios\CicloCompleto;
use Database\Seeders\Escenarios\Cuestionario;
use Database\Seeders\Escenarios\EntregaDeTarea;
use Database\Seeders\Escenarios\Escenario;
use Database\Seeders\Escenarios\Esqueleto;
use Database\Seeders\Escenarios\NotasYCierre;
use Database\Seeders\Escenarios\Registro;
use Database\Seeders\Escenarios\Reparto;
use Database\Seeders\Escenarios\RolesYAjustes;
use Database\Seeders\Escenarios\SesionVirtualYCalendario;
use Database\Seeders\Escenarios\Tester;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Motor de escenarios de prueba.
 *
 * Prepara el estado de la base para probar un flujo con personas concretas, guarda el reparto, la lista
 * de lo sembrado y la checklist, y devuelve los enlaces que debe abrir cada rol.
 *
 * Solo funciona fuera de producción y con `LMS_DEV_LOGIN=true`, igual que «Entrar como…»: siembra
 * usuarios y les asigna roles, así que en producción no debe existir ni la pantalla ni el comando.
 */
class EscenarioService
{
    /** @var array<int, class-string<Escenario>> */
    public const FLUJOS = [
        AsistenciaQr::class,
        AsistenciaVirtualMeet::class,
        EntregaDeTarea::class,
        Cuestionario::class,
        SesionVirtualYCalendario::class,
        CicloCompleto::class,
        NotasYCierre::class,
        RolesYAjustes::class,
    ];

    /** Roles que puede pedir un escenario, con su nombre para la interfaz. */
    public const ROLES = [
        'administrador' => 'Administrador',
        'jefe_departamento' => 'Jefe de departamento',
        'coordinador' => 'Coordinador de carrera',
        'docente' => 'Docente',
        'estudiante' => 'Estudiante',
        'soporte_tecnico' => 'Soporte técnico',
    ];

    public function __construct(private CasosDePruebaService $casos) {}

    public function habilitado(): bool
    {
        return config('lms.dev_login') === true && ! app()->isProduction();
    }

    /**
     * Igual que `habilitado()`, pero explota. La pantalla Livewire usa `habilitado()` y responde 404;
     * los comandos de Artisan usan esto, porque un abort() en consola solo deja un stack trace.
     */
    public function exigirHabilitado(): void
    {
        if (! $this->habilitado()) {
            throw new RuntimeException('Los escenarios de prueba están apagados: hacen falta LMS_DEV_LOGIN=true y un entorno distinto de producción.');
        }
    }

    /**
     * El catálogo de flujos, con lo que el wizard necesita preguntar.
     *
     * @return array<string, array{clase: class-string<Escenario>, titulo: string, descripcion: string, roles: array<string, array{etiqueta: string, minimo: int, maximo: int}>, rfs: array<int, string>, relleno: int}>
     */
    public function catalogo(): array
    {
        $catalogo = [];

        foreach (self::FLUJOS as $clase) {
            $catalogo[$clase::clave()] = [
                'clase' => $clase,
                'titulo' => $clase::titulo(),
                'descripcion' => $clase::descripcion(),
                'roles' => $clase::roles(),
                'rfs' => $clase::rfs(),
                'relleno' => $clase::rellenoSugerido(),
            ];
        }

        return $catalogo;
    }

    /**
     * @return array{clase: class-string<Escenario>, titulo: string, descripcion: string, roles: array<string, array{etiqueta: string, minimo: int, maximo: int}>, rfs: array<int, string>, relleno: int}
     */
    public function flujo(string $clave): array
    {
        return $this->catalogo()[$clave] ?? throw new InvalidArgumentException("No existe el flujo «{$clave}».");
    }

    /**
     * Los testers del equipo, con su cuenta ya creada. Es idempotente: si la persona ya existe por su
     * correo institucional, se reutiliza; nunca se duplica ni se le cambia el nombre.
     *
     * @return Collection<int, Tester>
     */
    public function testers(): Collection
    {
        /** @var array<string, array{nombre: string, apellido: string}> $testers */
        $testers = require database_path('seeders/data/testers.php');
        $correos = array_map(fn (string $due) => "{$due}@ues.edu.sv", array_keys($testers));
        $existentes = DB::table('users')->whereIn('institutional_email', $correos)->pluck('id', 'institutional_email');

        $faltantes = [];
        foreach ($testers as $due => $datos) {
            $correo = "{$due}@ues.edu.sv";

            if (! $existentes->has($correo)) {
                $faltantes[] = [
                    'institutional_email' => $correo,
                    'first_name' => $datos['nombre'],
                    'last_name' => $datos['apellido'],
                    'is_active' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        if ($faltantes !== []) {
            /*
             * `users` es una tabla auditada: en PostgreSQL el trigger `fn_audit_row()` rechaza con AC900
             * cualquier escritura sin actor (ADR-027). Sembrar las cuentas del equipo es trabajo del
             * sistema, no de la persona que abre el wizard, así que va dentro de `DbContext::system()`.
             */
            DbContext::system(fn () => DB::table('users')->insert($faltantes), 'escenario.testers');

            $existentes = DB::table('users')->whereIn('institutional_email', $correos)->pluck('id', 'institutional_email');
        }

        $personas = [];
        foreach ($testers as $due => $datos) {
            $correo = "{$due}@ues.edu.sv";
            $personas[] = new Tester((int) $existentes[$correo], $due, $correo, "{$datos['nombre']} {$datos['apellido']}");
        }

        return collect($personas)->sortBy('nombre')->values();
    }

    /**
     * Prepara un escenario y devuelve su fila.
     *
     * @param  array<string, array<int, int>>  $reparto  rol => id de usuario
     */
    public function preparar(string $flujo, array $reparto, ?int $relleno = null, ?int $preparadoPor = null, ?string $notas = null): object
    {
        $this->exigirHabilitado();

        $definicion = $this->flujo($flujo);
        $this->validarReparto($definicion, $reparto);

        $clave = $this->codigoNuevo();
        $relleno ??= $definicion['relleno'];

        try {
            return DbContext::system(function () use ($definicion, $flujo, $clave, $reparto, $relleno, $preparadoPor, $notas): object {
                $escenarioId = (int) DB::table('test_scenarios')->insertGetId([
                    'code' => $clave,
                    'flow' => $flujo,
                    'title' => $definicion['titulo'],
                    'status' => 'ready',
                    'prepared_by' => $preparadoPor,
                    'notes' => $notas,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                $registro = new Registro($escenarioId);
                $esqueleto = new Esqueleto($registro, $clave);

                /** @var Escenario $constructor */
                $constructor = new $definicion['clase'];
                $resultado = $constructor->preparar(Reparto::de($reparto, $relleno), $esqueleto);

                DB::table('test_scenarios')->where('id', $escenarioId)->update([
                    'period_id' => $esqueleto->periodo()->id,
                    'payload' => json_encode([
                        'enlaces' => $resultado->enlaces,
                        'datos' => $resultado->datos,
                        'resumen' => $resultado->resumen,
                    ], JSON_UNESCAPED_UNICODE),
                    'updated_at' => Carbon::now(),
                ]);

                $this->guardarActores($escenarioId, $reparto, $definicion);
                $this->guardarChecklist($escenarioId, $definicion['rfs']);

                return (object) ['id' => $escenarioId, 'code' => $clave, 'flow' => $flujo, 'title' => $definicion['titulo']];
            }, 'test_scenario.prepared');
        } catch (Throwable $excepcion) {
            // La transacción ya deshizo lo sembrado; se vuelve a lanzar con el flujo en el mensaje.
            throw new RuntimeException("No se pudo preparar el escenario «{$flujo}»: {$excepcion->getMessage()}", previous: $excepcion);
        }
    }

    /**
     * Deshace un escenario: borra lo que sembró, al revés de como lo creó, y lo deja marcado.
     *
     * @return int cuántas filas se borraron
     */
    public function limpiar(int $escenarioId): int
    {
        $this->exigirHabilitado();

        return DbContext::system(function () use ($escenarioId): int {
            $objetos = DB::table('test_scenario_objects')->where('scenario_id', $escenarioId)->orderByDesc('id')->get();
            $borradas = 0;

            foreach ($objetos as $objeto) {
                /** @var array<string, mixed> $clave */
                $clave = json_decode((string) $objeto->key_json, true) ?: [];

                if ($clave === []) {
                    continue;
                }

                $borradas += DB::table($objeto->table_name)->where($clave)->delete();
            }

            DB::table('test_scenario_objects')->where('scenario_id', $escenarioId)->delete();
            DB::table('test_scenarios')->where('id', $escenarioId)->update([
                'status' => 'cleaned',
                'cleaned_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            return $borradas;
        }, 'test_scenario.cleaned');
    }

    /**
     * @return Collection<int, stdClass>
     */
    public function lista(?string $estado = 'ready'): Collection
    {
        $consulta = DB::table('test_scenarios as s')
            ->leftJoin('users as u', 'u.id', '=', 's.prepared_by')
            ->select('s.*', 'u.institutional_email as preparado_por')
            ->orderByDesc('s.id');

        if ($estado !== null) {
            $consulta->where('s.status', $estado);
        }

        return $consulta->get();
    }

    public function porCodigo(string $codigo): ?object
    {
        return DB::table('test_scenarios')->where('code', Str::upper(trim($codigo)))->first();
    }

    /**
     * Todo lo que la página del escenario necesita: reparto, enlaces con su URL ya resuelta y checklist.
     *
     * @return array{escenario: object, actores: Collection<int, stdClass>, enlaces: array<string, array<int, array{titulo: string, url: string|null, nota: string|null}>>, datos: array<string, mixed>, resumen: array<int, string>, checklist: Collection<int, stdClass>}
     */
    public function detalle(object $escenario): array
    {
        /** @var array{enlaces?: array<int, array{rol: string, vista: string, params: array<string, mixed>, nota: string|null}>, datos?: array<string, mixed>, resumen?: array<int, string>} $payload */
        $payload = json_decode((string) ($escenario->payload ?? '{}'), true) ?: [];

        $enlaces = [];
        foreach ($payload['enlaces'] ?? [] as $enlace) {
            $enlaces[$enlace['rol']][] = [
                'titulo' => $this->tituloDeVista($enlace['vista']),
                'url' => $this->url($enlace['vista'], $enlace['params']),
                'nota' => $enlace['nota'] ?? null,
            ];
        }

        return [
            'escenario' => $escenario,
            'actores' => DB::table('test_scenario_actors as a')
                ->join('users as u', 'u.id', '=', 'a.user_id')
                ->where('a.scenario_id', $escenario->id)
                ->orderBy('a.role')->orderBy('u.first_name')
                ->get(['a.role', 'a.label', 'a.is_filler', 'u.id', 'u.institutional_email', 'u.first_name', 'u.last_name']),
            'enlaces' => $enlaces,
            'datos' => $payload['datos'] ?? [],
            'resumen' => $payload['resumen'] ?? [],
            'checklist' => DB::table('test_scenario_checks as c')
                ->leftJoin('users as u', 'u.id', '=', 'c.checked_by')
                ->where('c.scenario_id', $escenario->id)
                ->orderBy('c.position')
                ->get(['c.*', 'u.institutional_email as marcado_por']),
        ];
    }

    /**
     * Marca un caso de la checklist.
     */
    public function marcar(int $checkId, string $estado, ?string $nota, ?int $usuarioId): void
    {
        if (! in_array($estado, ['pending', 'pass', 'fail', 'blocked'], true)) {
            throw new InvalidArgumentException("«{$estado}» no es un resultado válido.");
        }

        DB::table('test_scenario_checks')->where('id', $checkId)->update([
            'status' => $estado,
            'note' => $nota,
            'checked_by' => $estado === 'pending' ? null : $usuarioId,
            'checked_at' => $estado === 'pending' ? null : Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * La URL de una vista del registro, o null si la ruta no existe (por si cambió de nombre).
     *
     * @param  array<string, mixed>  $params
     */
    public function url(string $vista, array $params = []): ?string
    {
        if (! Route::has($vista)) {
            return null;
        }

        try {
            return route($vista, $params);
        } catch (Throwable) {
            return null;
        }
    }

    private function tituloDeVista(string $vista): string
    {
        /** @var array<string, array{titulo?: string}> $vistas */
        $vistas = config('vistas', []);

        return $vistas[$vista]['titulo'] ?? $vista;
    }

    /**
     * @param  array{roles: array<string, array{etiqueta: string, minimo: int, maximo: int}>, titulo: string}  $definicion
     * @param  array<string, array<int, int>>  $reparto
     */
    private function validarReparto(array $definicion, array $reparto): void
    {
        foreach ($definicion['roles'] as $rol => $regla) {
            $cuantos = count(array_unique($reparto[$rol] ?? []));

            if ($cuantos < $regla['minimo']) {
                throw new InvalidArgumentException("El escenario «{$definicion['titulo']}» necesita al menos {$regla['minimo']} ".self::ROLES[$rol].'.');
            }
            if ($cuantos > $regla['maximo']) {
                throw new InvalidArgumentException("El escenario «{$definicion['titulo']}» admite como máximo {$regla['maximo']} ".self::ROLES[$rol].'.');
            }
        }

        $sobrantes = array_diff(array_keys(array_filter($reparto)), array_keys($definicion['roles']));
        if ($sobrantes !== []) {
            throw new InvalidArgumentException('El escenario no usa estos roles: '.implode(', ', $sobrantes).'.');
        }
    }

    /**
     * @param  array<string, array<int, int>>  $reparto
     * @param  array{roles: array<string, array{etiqueta: string, minimo: int, maximo: int}>}  $definicion
     */
    private function guardarActores(int $escenarioId, array $reparto, array $definicion): void
    {
        $filas = [];

        foreach ($reparto as $rol => $usuarios) {
            foreach (array_unique($usuarios) as $usuarioId) {
                $filas[] = [
                    'scenario_id' => $escenarioId,
                    'user_id' => $usuarioId,
                    'role' => $rol,
                    'label' => $definicion['roles'][$rol]['etiqueta'] ?? (self::ROLES[$rol] ?? $rol),
                    'is_filler' => false,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        if ($filas !== []) {
            DB::table('test_scenario_actors')->insert($filas);
        }
    }

    /**
     * @param  array<int, string>  $rfs
     */
    private function guardarChecklist(int $escenarioId, array $rfs): void
    {
        $filas = [];

        foreach ($this->casos->deVariosRf($rfs) as $posicion => $caso) {
            $filas[] = [
                'scenario_id' => $escenarioId,
                'case_id' => $caso['id'],
                'statement' => $caso['enunciado'],
                'status' => 'pending',
                'position' => $posicion,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        foreach (array_chunk($filas, 100) as $parte) {
            DB::table('test_scenario_checks')->insert($parte);
        }
    }

    /**
     * Código corto y dictable por teléfono: sin vocales (así no forma palabras) y sin 0/O ni 1/I, que se
     * confunden al leerlos. Va dentro del código de la materia sembrada, así que se mantiene breve.
     */
    private function codigoNuevo(): string
    {
        $alfabeto = 'BCDFGHJKLMNPQRSTVWXYZ23456789';

        do {
            $codigo = 'E';
            for ($i = 0; $i < 4; $i++) {
                $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
        } while (DB::table('test_scenarios')->where('code', $codigo)->exists());

        return $codigo;
    }
}
