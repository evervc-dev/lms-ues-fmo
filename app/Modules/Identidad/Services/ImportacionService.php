<?php

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Modules\Identidad\Events\ImportacionTerminada;
use App\Modules\Identidad\Jobs\ProcesarImportacionUsuarios;
use App\Support\Alcance;
use App\Support\DbContext;
use App\Support\HojaCalculo;
use App\Support\Nombre;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Carga masiva de usuarios (RF04, Flujo Carga masiva de usuarios). Columnas: carnet, nombres, apellidos,
 * correo, rol, carrera, plan, ciclo. El carné del estudiante sale del correo; la columna carnet es opcional y, si viene, debe
 * coincidir. Importan el Administrador, cualquier rol, y Soporte, estudiantes de cualquier carrera
 * (`usuarios.importar`, ADR-047). Lee CSV y .xlsx con App\Support\HojaCalculo
 * (ADR-048), y la pantalla ofrece la plantilla ya llena con una carrera y un plan reales.
 *
 * El lote se procesa en la cola `importaciones` (ProcesarImportacionUsuarios): cada fila va en su propia
 * transacción, así una fila que falla no deshace las demás, y al terminar se avisa a quien lo subió. Docente,
 * Coordinador y Jefe necesitan la columna carrera: de ella salen su departamento o su carrera, con las mismas
 * reglas que el alta manual (AsignacionService).
 */
class ImportacionService
{
    public const COLUMNAS = ['carnet', 'nombres', 'apellidos', 'correo', 'rol', 'carrera', 'plan', 'ciclo'];

    /** Roles que necesitan un ámbito al crearse (perfil docente, coordinación o jefatura). */
    private const ROLES_CON_AMBITO = ['docente', 'coordinador', 'jefe_departamento'];

    public function __construct(private Alcance $alcance, private JerarquiaService $jerarquia, private AsignacionService $asignacion) {}

    /**
     * Plantilla del archivo a llenar: los encabezados y una fila de ejemplo **con una carrera y un plan reales
     * del alcance de quien la descarga**, para que el ejemplo pase la validación en vez de ser un relleno.
     * Si no alcanza ninguna carrera, la fila va con los marcadores entre llaves.
     *
     * @return array{columnas: array<int, string>, filas: array<int, array<int, string>>}
     */
    public function plantilla(User $actor): array
    {
        $alcance = $this->alcance->exigir($actor, 'usuarios.importar');

        $carrera = DB::table('careers')
            ->when($alcance === 'C', fn ($q) => $q->whereIn('careers.id', $this->alcance->carreras($actor)))
            ->join('study_plans', 'study_plans.career_id', '=', 'careers.id')
            ->orderBy('careers.code')->orderByDesc('study_plans.name')
            ->first(['careers.code', 'study_plans.name as plan']);

        return [
            'columnas' => self::COLUMNAS,
            'filas' => [[
                'll22017',
                'Luis Alberto',
                'López Ramírez',
                'll22017@ues.edu.sv',
                'estudiante',
                $carrera?->code ?? '{código de la carrera}',
                $carrera?->plan ?? '{nombre del plan}',
                '1',
            ]],
        ];
    }

    /**
     * Lee y valida el archivo sin guardar nada.
     *
     * @return array{validas: Collection<int, array<string, mixed>>, errores: Collection<int, array<string, mixed>>, duplicados: int}
     */
    public function vistaPrevia(User $actor, string $ruta, string $extension = 'csv'): array
    {
        $alcance = $this->alcance->exigir($actor, 'usuarios.importar');
        $controlTotal = $this->jerarquia->controlTotal($actor);
        $filas = $this->leer($ruta, $extension);
        $carreras = DB::table('careers')->when($alcance === 'C', fn ($q) => $q->whereIn('id', $this->alcance->carreras($actor)))->pluck('id', 'code')->mapWithKeys(fn ($id, $c) => [mb_strtoupper($c) => (int) $id]);
        $existentes = DB::table('users')->pluck('institutional_email')->map(fn ($c) => mb_strtolower($c))->flip();
        $carnesExistentes = DB::table('student_profiles')->pluck('student_code')->map(fn ($c) => mb_strtolower($c))->flip();
        $vistos = [];
        $carnesVistos = [];
        $validas = collect();
        $errores = collect();
        $duplicados = 0;

        foreach ($filas as $n => $f) {
            $correo = mb_strtolower(trim($f['correo'] ?? ''));
            $rol = mb_strtolower(trim($f['rol'] ?? 'estudiante')) ?: 'estudiante';
            $carrera = $carreras[mb_strtoupper(trim($f['carrera'] ?? ''))] ?? null;
            // El carné sale del correo (ll22017@ues.edu.sv → ll22017); la columna carnet solo cuenta si el correo no da uno.
            $carnet = mb_strtolower(trim($f['carnet'] ?? ''));
            $delCorreo = AsignacionService::carneDeCorreo($correo);
            $carne = $delCorreo ?? $carnet;
            $error = match (true) {
                ! str_ends_with($correo, '@ues.edu.sv') => ['correo', __('El correo no es @ues.edu.sv')],
                $existentes->has($correo) || isset($vistos[$correo]) => ['correo', __('El correo ya existe')],
                $rol === 'estudiante' && $delCorreo !== null && $carnet !== '' && $carnet !== $delCorreo => ['carnet', __('El carnet no coincide con el correo (:carne)', ['carne' => $delCorreo])],
                $rol === 'estudiante' && ($carne === '' || mb_strlen($carne) > 20) => ['carnet', __('Falta el carnet o tiene más de 20 caracteres')],
                $rol === 'estudiante' && ($carnesExistentes->has($carne) || isset($carnesVistos[$carne])) => ['carnet', __('El carnet ya existe')],
                ! in_array($rol, ['estudiante', 'docente', 'coordinador', 'jefe_departamento', 'administrador', 'soporte_tecnico'], true) => ['rol', __('El rol no existe')],
                ! $controlTotal && $rol !== 'estudiante' => ['rol', __('Solo puede importar estudiantes')],
                $rol === 'estudiante' && $carrera === null => ['carrera', __('La carrera no existe o no es suya')],
                in_array($rol, self::ROLES_CON_AMBITO, true) && $carrera === null => ['carrera', __('Ese rol necesita la carrera: de ella salen su departamento o la carrera que coordina')],
                $rol === 'estudiante' && ! DB::table('study_plans')->where('career_id', $carrera)->where('name', trim($f['plan'] ?? ''))->exists() => ['plan', __('El plan no existe en la carrera')],
                $rol === 'estudiante' && ((int) ($f['ciclo'] ?? 0) < 1 || (int) ($f['ciclo'] ?? 0) > AsignacionService::CICLO_MAXIMO) => ['ciclo', __('El ciclo debe estar entre 1 y :maximo', ['maximo' => AsignacionService::CICLO_MAXIMO])],
                blank($f['nombres'] ?? null) || blank($f['apellidos'] ?? null) => ['nombres', __('Faltan nombres o apellidos')],
                ($problema = Nombre::error((string) $f['nombres']) ?? Nombre::error((string) $f['apellidos'])) !== null => ['nombres', $problema],
                default => null,
            };
            if ($error) {
                in_array($error[1], [__('El correo ya existe'), __('El carnet ya existe')], true) && $duplicados++;
                $errores->push(['fila' => $n + 2, 'campo' => $error[0], 'mensaje' => $error[1], 'datos' => $f]);

                continue;
            }
            $vistos[$correo] = true;
            if ($rol === 'estudiante') {
                $carnesVistos[$carne] = true;
            }
            $validas->push($f + ['_correo' => $correo, '_rol' => $rol, '_carrera' => $carrera, '_carne' => $carne, '_fila' => $n + 2]);
        }

        return ['validas' => $validas, 'errores' => $errores, 'duplicados' => $duplicados];
    }

    /**
     * Registra el lote y lo encola en `importaciones` (RF04). El archivo se copia para que el job lo lea después.
     * Se lee una vez aquí para rechazar enseguida un archivo ilegible o sin las columnas.
     */
    public function importar(User $actor, string $ruta, string $nombre): int
    {
        $extension = pathinfo($nombre, PATHINFO_EXTENSION) ?: 'csv';
        $previa = $this->vistaPrevia($actor, $ruta, $extension);
        $guardado = 'importaciones/'.Str::ulid().'.'.$extension;
        Storage::disk('local')->put($guardado, (string) file_get_contents($ruta));

        $lote = (int) DbContext::run(fn () => DB::table('import_batches')->insertGetId(['kind' => 'users', 'file_name' => $nombre, 'uploaded_by' => $actor->id,
            'total_rows' => $previa['validas']->count() + $previa['errores']->count(), 'valid_rows' => 0, 'error_rows' => 0,
            'status' => 'queued', 'created_at' => now(), 'updated_at' => now()]), 'users.import_queued');

        ProcesarImportacionUsuarios::dispatch($lote, $actor->id, $guardado, $extension);

        return $lote;
    }

    /**
     * Procesa el lote (lo llama el job). Cada fila válida va en su propia transacción: si una falla, se registra
     * como error y las demás siguen. Al terminar, el lote queda con sus conteos y se avisa a quien lo subió.
     */
    public function procesar(int $loteId, User $actor, string $guardado, string $extension): void
    {
        $lote = DB::table('import_batches')->where('id', $loteId)->first() ?? abort(404);
        if ($lote->status === 'finished') {
            return;
        }
        // El job no tiene sesión: el actor del lote queda como responsable de cada escritura (DbContext, ADR-027).
        Auth::setUser($actor);
        DbContext::run(fn () => DB::table('import_batches')->where('id', $loteId)->update(['status' => 'processing', 'updated_at' => now()]), 'users.import_started');

        $previa = $this->vistaPrevia($actor, Storage::disk('local')->path($guardado), $extension);
        $errores = $previa['errores'];
        $creados = 0;
        foreach ($previa['validas'] as $f) {
            try {
                DbContext::run(fn () => $this->crearFila($actor, $loteId, $f), 'users.imported');
                $creados++;
            } catch (Throwable $e) {
                $mensaje = $e instanceof ValidationException ? (string) collect($e->errors())->flatten()->first() : __('No se pudo crear la cuenta.');
                $errores->push(['fila' => $f['_fila'], 'campo' => 'fila', 'mensaje' => $mensaje, 'datos' => array_diff_key($f, array_flip(['_correo', '_rol', '_carrera', '_carne', '_fila']))]);
            }
        }

        DbContext::run(function () use ($loteId, $errores, $creados, $previa) {
            foreach ($errores as $e) {
                DB::table('import_batch_errors')->insert(['batch_id' => $loteId, 'row_number' => $e['fila'], 'field' => $e['campo'], 'message' => $e['mensaje'], 'raw_data' => json_encode($e['datos']), 'created_at' => now()]);
            }
            DB::table('import_batches')->where('id', $loteId)->update(['total_rows' => $previa['validas']->count() + $previa['errores']->count(),
                'valid_rows' => $creados, 'error_rows' => $errores->count(), 'status' => 'finished', 'finished_at' => now(), 'updated_at' => now()]);
        }, 'users.import_finished');
        Storage::disk('local')->delete($guardado);

        ImportacionTerminada::dispatch($loteId);
    }

    /**
     * Crea una cuenta del lote con su rol y, si lo necesita, su perfil o ámbito. Corre dentro del DbContext::run()
     * de procesar(), una transacción por fila.
     *
     * @param  array<string, mixed>  $f
     */
    private function crearFila(User $actor, int $loteId, array $f): void
    {
        $usuario = User::query()->create(['institutional_email' => $f['_correo'], 'first_name' => Nombre::normalizar($f['nombres']), 'last_name' => Nombre::normalizar($f['apellidos']), 'is_active' => true]);
        DB::table('users')->where('id', $usuario->id)->update(['import_batch_id' => $loteId]);
        // ADR-053: la carga masiva pasa por la misma comprobación de roles compatibles que la gestión manual.
        $this->jerarquia->exigirRolesCompatibles([...$usuario->getRoleNames()->all(), $f['_rol']]);
        $usuario->assignRole($f['_rol']);
        if ($f['_rol'] === 'estudiante') {
            DB::table('student_profiles')->insert(['user_id' => $usuario->id, 'student_code' => $f['_carne'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('student_careers')->insert(['student_id' => $usuario->id, 'career_id' => $f['_carrera'],
                'study_plan_id' => DB::table('study_plans')->where('career_id', $f['_carrera'])->where('name', trim($f['plan']))->value('id'),
                'current_cycle' => (int) $f['ciclo'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        } elseif (in_array($f['_rol'], self::ROLES_CON_AMBITO, true)) {
            // Igual que el alta manual (ADR-037): perfil docente con departamento, coordinación de la carrera o jefatura.
            $departamento = (int) DB::table('careers')->where('id', $f['_carrera'])->value('department_id');
            $this->asignacion->escribir($actor, $usuario->id, $f['_rol'], ['carrera' => $f['_carrera'], 'departamento' => $departamento]);
        }
    }

    /**
     * @return array{lote: object, errores: Collection<int, object>}
     */
    public function lote(User $actor, int $id): array
    {
        $alcance = $this->alcance->exigir($actor, 'usuarios.importar');
        $lote = DB::table('import_batches as b')->join('users as u', 'u.id', '=', 'b.uploaded_by')->where('b.id', $id)
            ->when($alcance !== 'G', fn ($q) => $q->where('b.uploaded_by', $actor->id))
            ->first(['b.*', DB::raw("u.first_name || ' ' || u.last_name as subio")]) ?? abort(404);

        return ['lote' => $lote, 'errores' => DB::table('import_batch_errors')->where('batch_id', $id)->orderBy('row_number')->get()];
    }

    /**
     * @return Collection<int, object>
     */
    public function lotes(User $actor): Collection
    {
        $alcance = $this->alcance->exigir($actor, 'usuarios.importar');

        return DB::table('import_batches')->when($alcance !== 'G', fn ($q) => $q->where('uploaded_by', $actor->id))->orderByDesc('created_at')->limit(20)->get();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function leer(string $ruta, string $extension): array
    {
        try {
            $filas = HojaCalculo::leer($ruta, $extension);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['archivo' => __('No se pudo leer el archivo.')]);
        }

        $primera = array_shift($filas) ?? [];
        $encabezado = array_map(fn (string $c) => mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', $c))), $primera);
        if (array_diff(['correo', 'nombres', 'apellidos'], $encabezado)) {
            throw ValidationException::withMessages(['archivo' => __('Faltan columnas. Use: :c.', ['c' => implode(', ', self::COLUMNAS)])]);
        }

        return array_map(
            fn (array $linea) => array_combine($encabezado, array_pad(array_slice($linea, 0, count($encabezado)), count($encabezado), '')),
            $filas,
        );
    }
}
