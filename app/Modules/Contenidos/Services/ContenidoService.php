<?php

namespace App\Modules\Contenidos\Services;

use App\Models\User;
use App\Modules\Contenidos\Jobs\RegistrarAccesoRecurso;
use App\Modules\Evaluaciones\Services\ReutilizacionService;
use App\Support\Alcance;
use App\Support\DbContext;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Contenidos de la sección (RF11–RF14, RF18): unidades y recursos, papelera de 30 días, avance del
 * estudiante, analítica de acceso y reutilización entre períodos.
 */
class ContenidoService
{
    public const TIPOS = ['file' => 'Archivo', 'page' => 'Página', 'link' => 'Enlace', 'video' => 'Video', 'folder' => 'Carpeta'];

    /** Extensiones admitidas al subir un archivo (RF11). La usan el componente y el `accept` del input. */
    public const EXTENSIONES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp', 'txt', 'zip'];

    /** Tamaño máximo del archivo, en kilobytes (50 MB). */
    public const MAXIMO_KB = 51200;

    /** Archivos máximos de una carpeta (RF11): una carpeta de materiales, no un respaldo del disco. */
    public const MAXIMO_ARCHIVOS = 50;

    /** Tamaño máximo de la carpeta completa, en kilobytes (80 MB), sumando todos sus archivos. */
    public const MAXIMO_TOTAL_KB = 81920;

    /** Días que lo eliminado se queda en la papelera antes de la purga definitiva. */
    public const DIAS_PAPELERA = 30;

    /** Modos de un PDF: incrustado en la plataforma o solo descarga (RF11). */
    public const MODOS_PDF = ['viewer' => 'Ver en la plataforma', 'download' => 'Solo descargar'];

    /** Cada cuántos segundos la página del recurso envía el tiempo visible (RF13; decidido por el usuario el 01/11/2026). */
    public const LATIDO_SEGUNDOS = 30;

    /** Máximo de segundos que se aceptan por envío: el latido más un margen por la demora de la red. */
    public const TOPE_LATIDO = 35;

    public function __construct(private Alcance $alcance, private ReutilizacionService $reutilizacion, private ProgresoService $progreso) {}

    /**
     * Regla de validación del archivo: extensión declarada y mime real, más el tamaño máximo.
     */
    public static function reglaDeArchivo(): string
    {
        return 'file|mimes:'.implode(',', self::EXTENSIONES).'|extensions:'.implode(',', self::EXTENSIONES).'|max:'.self::MAXIMO_KB;
    }

    /**
     * Valor del atributo `accept` del input de archivo.
     */
    public static function accept(): string
    {
        return '.'.implode(',.', self::EXTENSIONES);
    }

    /**
     * Nombre del .zip de una carpeta, a partir de su título (RF11). Saneado: sin acentos, espacios ni rutas.
     */
    public static function nombreZip(string $titulo): string
    {
        $base = Str::limit(Str::slug($titulo), 80, '');

        return ($base === '' ? 'carpeta' : $base).'.zip';
    }

    /**
     * Enlace embebible de YouTube o Drive para el reproductor integrado (RF12); null si no se reconoce
     * el patrón y hay que mostrarlo como enlace externo.
     */
    public static function embebido(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }
        if (! in_array(mb_strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return null;
        }

        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        $ruta = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $parametros);

        if ($host === 'youtu.be' || preg_match('~(?:^|\.)(?:youtube\.com|youtube-nocookie\.com)$~', $host) === 1) {
            $id = is_string($parametros['v'] ?? null) ? $parametros['v'] : null;
            if ($id === null && preg_match('~^/(?:embed|shorts|live|v)/([\w-]+)~', $ruta, $m) === 1) {
                $id = $m[1];
            }
            if ($id === null && $host === 'youtu.be' && preg_match('~^/([\w-]+)~', $ruta, $m) === 1) {
                $id = $m[1];
            }

            return $id !== null && preg_match('/^[\w-]{11}$/', $id) === 1 ? 'https://www.youtube-nocookie.com/embed/'.$id : null;
        }

        if ($host === 'drive.google.com') {
            $id = is_string($parametros['id'] ?? null) ? $parametros['id'] : null;
            if ($id === null && preg_match('~^/file/d/([\w-]+)~', $ruta, $m) === 1) {
                $id = $m[1];
            }

            return $id !== null && preg_match('/^[\w-]+$/', $id) === 1 ? 'https://drive.google.com/file/d/'.$id.'/preview' : null;
        }

        return null;
    }

    public function puedeVer(User $usuario, int $seccionId): bool
    {
        return $this->alcance->puedeEnSeccion($usuario, 'contenidos.ver', $seccionId);
    }

    public function puedeGestionar(User $usuario, int $seccionId): bool
    {
        return $this->alcance->puedeEnSeccion($usuario, 'contenidos.gestionar', $seccionId);
    }

    /**
     * Unidades con sus recursos. Para el estudiante, marca los recursos que ya completó.
     * `$buscar` busca en el título o en la etiqueta del tipo en español; `$tipo` filtra por `resource_type`.
     *
     * @return Collection<int, object>
     */
    public function unidades(User $usuario, int $seccionId, string $buscar = '', string $tipo = ''): Collection
    {
        $this->exigirEnSeccion($usuario, 'contenidos.ver', $seccionId);
        $matricula = $this->matricula($usuario, $seccionId);
        $buscar = mb_strtolower(trim($buscar));
        $tipo = array_key_exists($tipo, self::TIPOS) ? $tipo : '';
        $tiposBuscados = $this->tiposPorEtiqueta($buscar);

        $recursos = DB::table('resources')->join('units', 'units.id', '=', 'resources.unit_id')
            ->where('units.section_id', $seccionId)->whereNull('resources.deleted_at')->whereNull('units.deleted_at')
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->whereRaw('lower(resources.title) like ?', ["%{$buscar}%"])
                ->when($tiposBuscados !== [], fn ($q) => $q->orWhereIn('resources.resource_type', $tiposBuscados))))
            ->when($tipo !== '', fn ($q) => $q->where('resources.resource_type', $tipo))
            ->orderBy('resources.position')
            ->get(['resources.id', 'resources.ulid', 'resources.unit_id', 'resources.resource_type', 'resources.title', 'resources.description', 'resources.completion_mode', 'resources.pdf_mode']);
        $completados = $matricula ? DB::table('resource_progress')->where('enrollment_id', $matricula)->pluck('resource_id')->flip() : collect();

        return DB::table('units')->where('section_id', $seccionId)->whereNull('deleted_at')->orderBy('position')->get(['id', 'title', 'position'])
            ->map(function ($u) use ($recursos, $completados) {
                $u->recursos = $recursos->where('unit_id', $u->id)->values()->each(function ($r) use ($completados): void {
                    $r->completado = $completados->has($r->id);
                });

                return $u;
            })
            ->when($buscar !== '' || $tipo !== '', fn ($c) => $c->filter(fn ($u) => $u->recursos->isNotEmpty())->values());
    }

    public function crearUnidad(User $usuario, int $seccionId, string $titulo): void
    {
        $this->exigirEnSeccion($usuario, 'contenidos.gestionar', $seccionId);
        $posicion = (int) DB::table('units')->where('section_id', $seccionId)->max('position') + 1;
        DbContext::run(fn () => DB::table('units')->insert(['section_id' => $seccionId, 'title' => $titulo, 'position' => $posicion, 'created_at' => now(), 'updated_at' => now()]), 'unit.created');
    }

    /**
     * @param  array{tipo: string, titulo: string, descripcion?: string|null, url?: string|null, texto?: string|null, pdf?: string|null, archivo?: UploadedFile|null, archivos?: array<int, UploadedFile>, rutas?: array<int, string>, completion?: string|null, regla?: array<string, mixed>|null}  $datos
     *
     * @throws ValidationException
     */
    public function agregarRecurso(User $usuario, int $unidadId, array $datos): void
    {
        $seccionId = (int) (DB::table('units')->where('id', $unidadId)->value('section_id') ?? abort(404));
        $this->exigirEnSeccion($usuario, 'contenidos.gestionar', $seccionId);

        // Cómo se completa el recurso (RF18, ADR-067): sin elección, la página se completa al abrirla y el resto, a mano.
        // Se valida antes de subir nada: una regla inválida no debe dejar archivos huérfanos en el disco.
        $modo = ($datos['completion'] ?? ($datos['tipo'] === 'page' ? 'automatic' : 'manual')) === 'automatic' ? 'automatic' : 'manual';
        $regla = $modo === 'automatic' ? $this->progreso->validarRegla($seccionId, $datos['regla'] ?? ['type' => 'opened']) : null;

        $ulid = (string) Str::ulid();
        $ruta = null;
        $carpeta = [];
        if ($datos['tipo'] === 'file') {
            $archivo = $datos['archivo'] ?? null;
            if (! $archivo instanceof UploadedFile) {
                throw ValidationException::withMessages(['archivo' => __('Adjunte el archivo.')]);
            }
            $this->exigirArchivoAdmitido($archivo);
            $ruta = $archivo->store("contenidos/{$seccionId}");
        }
        if ($datos['tipo'] === 'folder') {
            // Se valida todo antes de escribir: una ruta inválida no deja ni el recurso ni archivos a medias.
            $carpeta = $this->carpetaAdmitida($datos['archivos'] ?? [], $datos['rutas'] ?? []);
            $ruta = "contenidos/{$seccionId}/carpetas/{$ulid}";
            foreach ($carpeta as $indice => $entrada) {
                $carpeta[$indice]['bytes'] = (int) $entrada['archivo']->getSize();
                $entrada['archivo']->storeAs($ruta, $entrada['ruta']);
            }
        }
        if (in_array($datos['tipo'], ['link', 'video'], true)) {
            $this->exigirUrlAdmitida((string) ($datos['url'] ?? ''));
        }

        $recursoId = DbContext::run(function () use ($ulid, $unidadId, $datos, $ruta, $carpeta, $modo, $regla): int {
            $recursoId = DB::table('resources')->insertGetId([
                'ulid' => $ulid, 'unit_id' => $unidadId, 'resource_type' => $datos['tipo'], 'title' => $datos['titulo'],
                'description' => $datos['descripcion'] ?? null, 'file_path' => $ruta, 'url' => $datos['url'] ?? null,
                'html_content' => $datos['tipo'] === 'page' ? $this->saneado((string) ($datos['texto'] ?? '')) : null,
                'pdf_mode' => $datos['tipo'] === 'file' ? ($datos['pdf'] ?? 'viewer') : null,
                'completion_mode' => $modo, 'completion_rule' => $regla === null ? null : json_encode($regla, JSON_UNESCAPED_UNICODE),
                'position' => (int) DB::table('resources')->where('unit_id', $unidadId)->max('position') + 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ($carpeta as $entrada) {
                DB::table('resource_files')->insert([
                    'resource_id' => $recursoId, 'path' => $entrada['ruta'], 'name' => basename($entrada['ruta']),
                    'bytes' => $entrada['bytes'] ?? 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $recursoId;
        }, 'resource.created');

        // Quien ya cumple la regla queda al día desde el primer momento (RF18): no se le pide repetir lo hecho.
        if ($regla !== null) {
            $this->progreso->evaluarRecurso($recursoId);
        }
    }

    /**
     * Cómo se completa un recurso ya publicado (RF18, ADR-067): a mano o con una regla, que se evalúa en el momento
     * para quienes ya la cumplen. Lo completado antes no se desmarca.
     *
     * @param  array<string, mixed>|null  $regla
     *
     * @throws ValidationException
     */
    public function cambiarRegla(User $usuario, int $seccionId, int $recursoId, string $modo, ?array $regla): void
    {
        $this->exigirEnSeccion($usuario, 'contenidos.gestionar', $seccionId);
        $this->recursoDeSeccion($seccionId, $recursoId)->whereNull('deleted_at')->first(['id']) ?? abort(404);
        if (! in_array($modo, ['manual', 'automatic'], true)) {
            throw ValidationException::withMessages(['completion' => __('Elija cómo se completa el recurso.')]);
        }
        $normalizada = $modo === 'automatic' ? $this->progreso->validarRegla($seccionId, $regla ?? []) : null;

        DbContext::run(fn () => $this->recursoDeSeccion($seccionId, $recursoId)->update([
            'completion_mode' => $modo,
            'completion_rule' => $normalizada === null ? null : json_encode($normalizada, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]), 'resource.completion_updated');

        if ($normalizada !== null) {
            $this->progreso->evaluarRecurso($recursoId);
        }
    }

    /**
     * Contenido de una página ya publicada (RF11). El HTML se sanea igual que al crearla: lo que llega
     * del editor nunca se guarda tal cual.
     *
     * @throws ValidationException
     */
    public function editarPagina(User $usuario, int $seccionId, int $recursoId, string $texto): void
    {
        $this->exigirEnSeccion($usuario, 'contenidos.gestionar', $seccionId);
        $recurso = $this->recursoDeSeccion($seccionId, $recursoId)->whereNull('deleted_at')->first(['id', 'resource_type']) ?? abort(404);
        if ($recurso->resource_type !== 'page') {
            throw ValidationException::withMessages(['texto' => __('Solo las páginas tienen contenido para editar.')]);
        }

        $limpio = $this->saneado($texto);
        if (trim(strip_tags($limpio)) === '') {
            throw ValidationException::withMessages(['texto' => __('Escriba el contenido de la página.')]);
        }

        DbContext::run(fn () => $this->recursoDeSeccion($seccionId, $recursoId)
            ->update(['html_content' => $limpio, 'updated_at' => now()]), 'resource.page_updated');
    }

    /**
     * Carpeta y sus archivos para armar el .zip (RF11). Exige el permiso con su alcance sobre la sección;
     * no registra apertura: eso es trabajo de abrir() y de RF13.
     *
     * @return array{recurso: object, archivos: Collection<int, object>}
     *
     * @throws AuthorizationException
     */
    public function carpeta(User $usuario, int $seccionId, string $ulid): array
    {
        $this->exigirEnSeccion($usuario, 'contenidos.ver', $seccionId);
        $recurso = $this->recursosDe($seccionId)->where('resources.ulid', $ulid)->where('resources.resource_type', 'folder')
            ->whereNull('resources.deleted_at')->whereNull('units.deleted_at')
            ->first(['resources.id', 'resources.ulid', 'resources.title', 'resources.file_path']) ?? abort(404);

        return [
            'recurso' => $recurso,
            'archivos' => DB::table('resource_files')->where('resource_id', $recurso->id)->orderBy('path')->get(['id', 'path', 'name', 'bytes']),
        ];
    }

    /**
     * Recurso con sus archivos, sin registrar nada: la apertura se registra una sola vez al entrar a la página
     * (registrarApertura), no en cada petición.
     *
     * @return array{recurso: \stdClass, archivos: Collection<int, \stdClass>, completado: bool}
     */
    public function recurso(User $usuario, int $seccionId, string $ulid): array
    {
        $this->exigirEnSeccion($usuario, 'contenidos.ver', $seccionId);
        $recurso = DB::table('resources')->join('units', 'units.id', '=', 'resources.unit_id')
            ->where('units.section_id', $seccionId)->where('resources.ulid', $ulid)->whereNull('resources.deleted_at')
            ->first(['resources.*', 'units.title as unidad']) ?? abort(404);
        $matricula = $this->matricula($usuario, $seccionId);

        return [
            'recurso' => $recurso,
            'archivos' => DB::table('resource_files')->where('resource_id', $recurso->id)->orderBy('path')->get(['id', 'path', 'name', 'bytes']),
            'completado' => $matricula && DB::table('resource_progress')->where('enrollment_id', $matricula)->where('resource_id', $recurso->id)->exists(),
        ];
    }

    /**
     * Registra en cola la apertura del recurso por un estudiante matriculado (RF13) y, si el recurso se completa al
     * abrirlo, lo completa (RF18). A quien no está matriculado (el docente, por ejemplo) no se le registra nada.
     */
    public function registrarApertura(User $usuario, int $seccionId, \stdClass $recurso): void
    {
        $matricula = $this->matricula($usuario, $seccionId);
        if ($matricula === null) {
            return;
        }
        RegistrarAccesoRecurso::dispatch((int) $recurso->id, $matricula, 'open', now()->format('Y-m-d H:i:s.uP'));
        // Solo la regla «al abrirlo» se cumple aquí (RF18): las demás las resuelven los listeners con su evento.
        if ((ProgresoService::regla($recurso)['type'] ?? null) === 'opened') {
            $this->progreso->completar($matricula, (int) $recurso->id, 'rule');
        }
    }

    /**
     * Registra en cola la descarga de un archivo o de una carpeta en .zip (RF13).
     */
    public function registrarDescarga(User $usuario, int $seccionId, int $recursoId): void
    {
        $matricula = $this->matricula($usuario, $seccionId);
        if ($matricula !== null) {
            RegistrarAccesoRecurso::dispatch($recursoId, $matricula, 'download', now()->format('Y-m-d H:i:s.uP'));
        }
    }

    /**
     * Suma el tiempo que el estudiante tuvo el recurso a la vista (RF13, CP-RF13-02). La página lo envía cada
     * LATIDO_SEGUNDOS y al ocultar la pestaña; el servidor acepta como mucho TOPE_LATIDO por envío.
     *
     * @throws ValidationException
     */
    public function registrarTiempo(User $usuario, int $seccionId, string $ulid, int $segundos): void
    {
        if ($segundos < 1) {
            throw ValidationException::withMessages(['segundos' => __('El tiempo debe ser de al menos un segundo.')]);
        }
        $recurso = $this->recurso($usuario, $seccionId, $ulid)['recurso'];
        $matricula = $this->matricula($usuario, $seccionId);
        if ($matricula !== null) {
            RegistrarAccesoRecurso::dispatch((int) $recurso->id, $matricula, 'time', now()->format('Y-m-d H:i:s.uP'), min($segundos, self::TOPE_LATIDO));
        }
    }

    /**
     * «Ya lo leí» (RF18, CP-RF18-01). Solo vale para un recurso **manual** y vivo de esa sección: el id lo manda el
     * navegador, así que un recurso con regla automática no se puede completar por aquí sin cumplirla (ADR-067).
     *
     * @throws AuthorizationException|ValidationException
     */
    public function marcarLeido(User $usuario, int $seccionId, int $recursoId): void
    {
        $matricula = $this->matricula($usuario, $seccionId)
            ?? throw new AuthorizationException(__('Solo puede marcar materiales de las asignaturas en las que está matriculado.'));
        $recurso = $this->recursoDeSeccion($seccionId, $recursoId)->whereNull('deleted_at')->first(['id', 'completion_mode']) ?? abort(404);
        if ($recurso->completion_mode !== 'manual') {
            throw ValidationException::withMessages(['recurso' => __('Este material se completa solo, al cumplirse su regla.')]);
        }
        $this->progreso->completar($matricula, $recursoId, 'manual');
    }

    /**
     * Modo de un PDF ya publicado: incrustado en la plataforma o solo descarga (RF11).
     *
     * @throws ValidationException
     */
    public function cambiarModoPdf(User $usuario, int $seccionId, int $recursoId, string $modo): void
    {
        $this->exigirEnSeccion($usuario, 'contenidos.gestionar', $seccionId);
        if (! array_key_exists($modo, self::MODOS_PDF)) {
            throw ValidationException::withMessages(['pdf' => __('Elija si el archivo se ve en la plataforma o solo se descarga.')]);
        }
        $recurso = $this->recursoDeSeccion($seccionId, $recursoId)->first(['id', 'resource_type']) ?? abort(404);
        if ($recurso->resource_type !== 'file') {
            throw ValidationException::withMessages(['pdf' => __('Solo los archivos tienen modo de visualización.')]);
        }

        DbContext::run(fn () => $this->recursoDeSeccion($seccionId, $recursoId)->update(['pdf_mode' => $modo, 'updated_at' => now()]), 'resource.pdf_mode_changed');
    }

    public function eliminar(User $usuario, int $seccionId, int $recursoId): void
    {
        $this->exigirEnSeccion($usuario, 'contenidos.gestionar', $seccionId);
        DbContext::run(fn () => $this->recursoDeSeccion($seccionId, $recursoId)->whereNull('deleted_at')->update(['deleted_at' => now(), 'updated_at' => now()]), 'resource.trashed');
    }

    /**
     * Manda la unidad a la papelera (RF11). Sus recursos quedan ocultos con ella y vuelven al restaurarla.
     */
    public function eliminarUnidad(User $usuario, int $seccionId, int $unidadId): void
    {
        $this->exigirEnSeccion($usuario, 'contenidos.gestionar', $seccionId);
        DbContext::run(fn () => $this->unidadDeSeccion($seccionId, $unidadId)->whereNull('deleted_at')->update(['deleted_at' => now(), 'updated_at' => now()]), 'unit.trashed');
    }

    /**
     * Papelera de la sección: unidades y recursos eliminados que todavía se pueden restaurar.
     *
     * @return Collection<int, object>
     */
    public function papelera(User $usuario, int $seccionId): Collection
    {
        $this->exigirEnSeccion($usuario, 'contenidos.gestionar', $seccionId);
        $limite = now()->subDays(self::DIAS_PAPELERA);

        $unidades = DB::table('units')->where('section_id', $seccionId)->whereNotNull('deleted_at')->where('deleted_at', '>=', $limite)
            ->get(['id', 'title', 'deleted_at'])
            ->map(fn ($u) => (object) ['tipo' => 'unidad', 'id' => (int) $u->id, 'title' => $u->title, 'unidad' => null, 'resource_type' => null, 'deleted_at' => $u->deleted_at]);

        $recursos = $this->recursosDe($seccionId)->whereNotNull('resources.deleted_at')->where('resources.deleted_at', '>=', $limite)
            ->get(['resources.id', 'resources.title', 'resources.resource_type', 'resources.deleted_at', 'units.title as unidad'])
            ->map(fn ($r) => (object) ['tipo' => 'recurso', 'id' => (int) $r->id, 'title' => $r->title, 'unidad' => $r->unidad, 'resource_type' => $r->resource_type, 'deleted_at' => $r->deleted_at]);

        return $unidades->concat($recursos)->sortByDesc('deleted_at')->values();
    }

    /**
     * Restaura un recurso de la papelera. Si su unidad también está eliminada la restaura con él, en la
     * misma transacción: un recurso sin unidad visible quedaría huérfano e invisible.
     *
     * @throws ValidationException
     */
    public function restaurar(User $usuario, int $seccionId, int $recursoId): void
    {
        $this->exigirEnSeccion($usuario, 'contenidos.gestionar', $seccionId);
        $recurso = $this->recursoDeSeccion($seccionId, $recursoId)->first(['id', 'unit_id', 'deleted_at']) ?? abort(404);
        $this->exigirRestaurable($recurso->deleted_at);

        DbContext::run(function () use ($seccionId, $recursoId, $recurso): void {
            $this->recursoDeSeccion($seccionId, $recursoId)->update(['deleted_at' => null, 'updated_at' => now()]);
            DB::table('units')->where('id', $recurso->unit_id)->whereNotNull('deleted_at')->update(['deleted_at' => null, 'updated_at' => now()]);
        }, 'resource.restored');
    }

    /**
     * Restaura una unidad de la papelera con los recursos que seguían dentro.
     *
     * @throws ValidationException
     */
    public function restaurarUnidad(User $usuario, int $seccionId, int $unidadId): void
    {
        $this->exigirEnSeccion($usuario, 'contenidos.gestionar', $seccionId);
        $unidad = $this->unidadDeSeccion($seccionId, $unidadId)->first(['id', 'deleted_at']) ?? abort(404);
        $this->exigirRestaurable($unidad->deleted_at);

        DbContext::run(fn () => $this->unidadDeSeccion($seccionId, $unidadId)->update(['deleted_at' => null, 'updated_at' => now()]), 'unit.restored');
    }

    /**
     * Purga lo que lleva más de 30 días en la papelera (RF11): borra del disco los archivos y vacía el contenido,
     * pero conserva las filas. El ER exige que los logs de acceso nunca se borren (FK RESTRICT), y borrar el
     * recurso obligaría a borrarlos; así desaparece el material y se conserva la analítica de RF13.
     * Lo usa el job PurgarPapelera. Idempotente: solo toca lo que pasó el plazo y aún conserva contenido.
     *
     * @return int Recursos purgados
     */
    public function purgarPapelera(): int
    {
        $limite = now()->subDays(self::DIAS_PAPELERA);
        $unidades = DB::table('units')->whereNotNull('deleted_at')->where('deleted_at', '<', $limite)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $recursos = DB::table('resources')
            ->where(fn ($q) => $q->where(fn ($q) => $q->whereNotNull('deleted_at')->where('deleted_at', '<', $limite))->orWhereIn('unit_id', $unidades))
            // Ya purgado: sin archivo, sin enlace y sin texto. No se vuelve a tocar.
            ->where(fn ($q) => $q->whereNotNull('file_path')->orWhereNotNull('url')->orWhereNotNull('html_content'))
            ->get(['id', 'file_path', 'resource_type']);

        if ($recursos->isEmpty()) {
            return 0;
        }

        $ids = $recursos->pluck('id')->map(fn ($id) => (int) $id)->all();
        $sueltos = $recursos->where('resource_type', '!=', 'folder');
        // En una carpeta, resource_files.path es la ruta dentro de la carpeta: lo que se borra es el directorio.
        $directorios = $recursos->where('resource_type', 'folder')->pluck('file_path')
            ->filter(fn ($ruta) => is_string($ruta) && $ruta !== '')->unique()->values()->all();
        $rutas = $sueltos->pluck('file_path')
            ->concat(DB::table('resource_files')->whereIn('resource_id', $sueltos->pluck('id'))->pluck('path'))
            ->filter(fn ($ruta) => is_string($ruta) && $ruta !== '')->unique()->values()->all();

        $purgados = DbContext::system(function () use ($ids): int {
            DB::table('resource_files')->whereIn('resource_id', $ids)->delete();

            return DB::table('resources')->whereIn('id', $ids)->update([
                'file_path' => null, 'url' => null, 'html_content' => null, 'pdf_mode' => null, 'updated_at' => now(),
            ]);
        }, 'content.purged');

        // Los archivos se borran después de confirmar la transacción: si esta falla, nada se pierde del disco.
        Storage::delete($rutas);
        foreach ($directorios as $directorio) {
            Storage::deleteDirectory($directorio);
        }

        return $purgados;
    }

    /**
     * Mapa de calor (RF13): recursos × estudiantes con aperturas y minutos.
     *
     * @return array{recursos: Collection<int, object>, estudiantes: Collection<int, object>, celdas: array<string, object>}
     */
    public function analitica(User $usuario, int $seccionId): array
    {
        $this->exigirEnSeccion($usuario, 'contenidos.analitica.ver', $seccionId);
        $recursos = $this->recursosDe($seccionId)->whereNull('resources.deleted_at')->orderBy('units.position')->orderBy('resources.position')
            ->get(['resources.id', 'resources.title', 'units.title as unidad']);
        $estudiantes = DB::table('enrollments')->join('users', 'users.id', '=', 'enrollments.student_id')
            ->where('section_id', $seccionId)->where('enrollments.status', 'active')->orderBy('users.last_name')
            ->get(['enrollments.id', 'users.first_name', 'users.last_name']);
        $celdas = DB::table('resource_access_logs')->whereIn('enrollment_id', $estudiantes->pluck('id'))->whereIn('resource_id', $recursos->pluck('id'))
            ->groupBy('enrollment_id', 'resource_id')
            ->get(['enrollment_id', 'resource_id', DB::raw('count(*) as aperturas'), DB::raw('coalesce(sum(duration_seconds), 0) as segundos')])
            ->keyBy(fn ($c) => $c->enrollment_id.'-'.$c->resource_id)->all();

        return compact('recursos', 'estudiantes', 'celdas');
    }

    /**
     * Secciones de un período anterior (RF14) que el docente puede copiar: de la misma asignatura o que él impartió.
     * `importada` indica si ya la copió a esta sección: no se copia dos veces.
     *
     * @return Collection<int, \stdClass>
     */
    public function origenesParaImportar(User $usuario, int $seccionId): Collection
    {
        $this->exigirEnSeccion($usuario, 'contenidos.reutilizar', $seccionId);
        $destino = DB::table('sections')->join('academic_periods', 'academic_periods.id', '=', 'sections.period_id')->where('sections.id', $seccionId)
            ->first(['sections.subject_id', 'academic_periods.start_date']);

        return DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')->join('academic_periods', 'academic_periods.id', '=', 'sections.period_id')
            ->where('academic_periods.start_date', '<', $destino->start_date)
            ->where(fn ($q) => $q->where('sections.subject_id', $destino->subject_id)
                ->orWhereIn('sections.id', DB::table('teaching_assignments')->where('teacher_id', $usuario->id)->select('section_id')))
            ->where(fn ($q) => $q->whereExists(fn ($q) => $q->from('units')->whereColumn('units.section_id', 'sections.id')->whereNull('units.deleted_at'))
                ->orWhereExists(fn ($q) => $q->from('assessments')->whereColumn('assessments.section_id', 'sections.id')))
            ->orderByDesc('academic_periods.start_date')
            ->get(['sections.id', 'sections.code as grupo', 'subjects.code', 'subjects.name', 'academic_periods.name as periodo',
                DB::raw('(select count(*) from units u where u.section_id = sections.id and u.deleted_at is null) as unidades'),
                DB::raw('(select count(*) from assessments a where a.section_id = sections.id) as evaluaciones'),
                DB::raw("exists (select 1 from content_copies c where c.source_section_id = sections.id and c.target_section_id = {$seccionId}) as importada")]);
    }

    /**
     * Copia de un período anterior las unidades con sus recursos y las evaluaciones (RF14). Cada recurso con archivo
     * recibe su propia copia física: borrar el original no deja sin archivo a la sección nueva. Las evaluaciones
     * llegan sin programar (ReutilizacionService, ADR-066). No se copian entregas, notas ni asistencia.
     *
     * @return array{recursos: int, evaluaciones: int}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function importar(User $usuario, int $origenId, int $destinoId): array
    {
        $origen = $this->origenesParaImportar($usuario, $destinoId)->firstWhere('id', $origenId);
        if ($origen === null) {
            throw new AuthorizationException(__('No puede copiar el contenido de esa sección.'));
        }
        if ((bool) $origen->importada) {
            throw ValidationException::withMessages(['origen' => __('Ya importó el contenido de esa sección: no se copia dos veces.')]);
        }

        // Los archivos se copian dentro de la transacción; si algo falla, se borran las copias para no dejar huérfanos.
        $copias = [];
        try {
            return DbContext::run(function () use ($usuario, $origenId, $destinoId, &$copias) {
                $recursos = 0;
                $base = (int) DB::table('units')->where('section_id', $destinoId)->max('position');
                foreach (DB::table('units')->where('section_id', $origenId)->whereNull('deleted_at')->orderBy('position')->get() as $i => $unidad) {
                    $nueva = DB::table('units')->insertGetId(['section_id' => $destinoId, 'title' => $unidad->title, 'position' => $base + $i + 1, 'created_at' => now(), 'updated_at' => now()]);
                    foreach (DB::table('resources')->where('unit_id', $unidad->id)->whereNull('deleted_at')->orderBy('position')->get() as $r) {
                        $ulid = (string) Str::ulid();
                        $ruta = $this->copiaDelArchivo($r, $destinoId, $ulid, $copias);
                        $id = DB::table('resources')->insertGetId(['ulid' => $ulid, 'unit_id' => $nueva, 'resource_type' => $r->resource_type, 'title' => $r->title,
                            'description' => $r->description, 'file_path' => $ruta, 'url' => $r->url, 'html_content' => $r->html_content, 'pdf_mode' => $r->pdf_mode,
                            'completion_mode' => $this->modoCopiado($r), 'completion_rule' => $this->reglaCopiada($r), 'source_resource_id' => $r->id, 'position' => $r->position,
                            'created_at' => now(), 'updated_at' => now()]);
                        foreach (DB::table('resource_files')->where('resource_id', $r->id)->get() as $f) {
                            DB::table('resource_files')->insert(['resource_id' => $id, 'path' => $f->path, 'name' => $f->name, 'bytes' => $f->bytes, 'created_at' => now(), 'updated_at' => now()]);
                        }
                        $recursos++;
                    }
                }
                $evaluaciones = $this->reutilizacion->copiar($usuario, $origenId, $destinoId);
                DB::table('content_copies')->insert(['source_section_id' => $origenId, 'target_section_id' => $destinoId, 'copied_by' => $usuario->id, 'created_at' => now(), 'updated_at' => now()]);

                return ['recursos' => $recursos, 'evaluaciones' => $evaluaciones];
            }, 'content.copied');
        } catch (\Throwable $error) {
            Storage::delete(array_filter($copias, fn (string $ruta) => ! str_ends_with($ruta, '/')));
            foreach (array_filter($copias, fn (string $ruta) => str_ends_with($ruta, '/')) as $directorio) {
                Storage::deleteDirectory(rtrim($directorio, '/'));
            }
            throw $error;
        }
    }

    /**
     * Copia física del archivo de un recurso para la sección de destino; devuelve la ruta nueva. Una carpeta copia su
     * directorio completo (resource_files guarda las rutas relativas). Si el original ya no está en el disco, se deja la
     * ruta tal cual: no hay archivo que compartir.
     *
     * @param  array<int, string>  $copias  Rutas creadas (los directorios terminan en «/»), para deshacerlas si algo falla
     */
    private function copiaDelArchivo(\stdClass $recurso, int $destinoId, string $ulid, array &$copias): ?string
    {
        $origen = $recurso->file_path;
        if (! is_string($origen) || $origen === '') {
            return null;
        }
        if ($recurso->resource_type === 'folder') {
            $directorio = "contenidos/{$destinoId}/carpetas/{$ulid}";
            $copias[] = $directorio.'/';
            foreach (DB::table('resource_files')->where('resource_id', $recurso->id)->pluck('path') as $relativa) {
                if (Storage::exists("{$origen}/{$relativa}")) {
                    Storage::copy("{$origen}/{$relativa}", "{$directorio}/{$relativa}");
                }
            }

            return $directorio;
        }
        if (! Storage::exists($origen)) {
            return $origen;
        }
        $extension = pathinfo($origen, PATHINFO_EXTENSION);
        $nueva = "contenidos/{$destinoId}/".Str::lower($ulid).($extension !== '' ? ".{$extension}" : '');
        Storage::copy($origen, $nueva);
        $copias[] = $nueva;

        return $nueva;
    }

    /**
     * Exige el permiso con su alcance sobre la sección (ADR-026). Sin permiso, 403.
     *
     * @throws AuthorizationException
     */
    private function exigirEnSeccion(User $usuario, string $permiso, int $seccionId): void
    {
        if (! $this->alcance->puedeEnSeccion($usuario, $permiso, $seccionId)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
    }

    /**
     * Tipos cuya etiqueta en español empieza por el texto buscado («vid» → video).
     *
     * @return array<int, string>
     */
    private function tiposPorEtiqueta(string $buscar): array
    {
        if ($buscar === '') {
            return [];
        }

        return array_keys(array_filter(self::TIPOS, fn (string $etiqueta) => str_starts_with(mb_strtolower(__($etiqueta)), $buscar)));
    }

    /**
     * HTML del editor de páginas saneado en el servidor (RF11, RNF01), con el perfil `contenidos` de
     * config/purifier.php: solo texto con formato, listas, títulos y enlaces http o https.
     *
     * HTMLPurifier escribe el rel de los enlaces externos como «noreferrer noopener» (ese es el orden en
     * que agrega sus transformaciones); se normaliza al orden convencional para que el HTML guardado sea
     * siempre el mismo.
     */
    private function saneado(string $texto): string
    {
        return str_replace('rel="noreferrer noopener"', 'rel="noopener noreferrer"', (string) clean($texto, 'contenidos'));
    }

    /**
     * Archivos de una carpeta con su ruta relativa ya normalizada (RF11). Valida tipo y tamaño de cada
     * archivo, el número de archivos, el tamaño total y que ninguna ruta se salga de la carpeta.
     *
     * @param  array<int, UploadedFile>  $archivos
     * @param  array<int, string>  $rutas  Ruta relativa de cada archivo, en el mismo orden
     * @return array<int, array{archivo: UploadedFile, ruta: string}>
     *
     * @throws ValidationException
     */
    private function carpetaAdmitida(array $archivos, array $rutas): array
    {
        $archivos = array_values($archivos);
        $rutas = array_values($rutas);

        if ($archivos === []) {
            throw ValidationException::withMessages(['archivos' => __('Elija una carpeta con al menos un archivo.')]);
        }
        if (count($archivos) > self::MAXIMO_ARCHIVOS) {
            throw ValidationException::withMessages(['archivos' => __('La carpeta no puede tener más de :n archivos.', ['n' => self::MAXIMO_ARCHIVOS])]);
        }
        if (count($rutas) !== count($archivos)) {
            throw ValidationException::withMessages(['archivos' => __('No se pudo leer la estructura de la carpeta. Vuelva a elegirla.')]);
        }

        Validator::make(['archivos' => $archivos], ['archivos.*' => self::reglaDeArchivo()], [
            'archivos.*.mimes' => __('Solo puede subir archivos :tipos.', ['tipos' => implode(', ', self::EXTENSIONES)]),
            'archivos.*.extensions' => __('Solo puede subir archivos :tipos.', ['tipos' => implode(', ', self::EXTENSIONES)]),
            'archivos.*.max' => __('El archivo no puede pasar de :mb MB.', ['mb' => intdiv(self::MAXIMO_KB, 1024)]),
        ])->validate();

        $total = array_sum(array_map(fn (UploadedFile $archivo) => (int) $archivo->getSize(), $archivos));
        if ($total > self::MAXIMO_TOTAL_KB * 1024) {
            throw ValidationException::withMessages(['archivos' => __('La carpeta completa no puede pasar de :mb MB.', ['mb' => intdiv(self::MAXIMO_TOTAL_KB, 1024)])]);
        }

        $entradas = [];
        $vistas = [];
        foreach ($archivos as $indice => $archivo) {
            $ruta = $this->rutaDeCarpetaSegura((string) $rutas[$indice]);
            if (in_array(mb_strtolower($ruta), $vistas, true)) {
                throw ValidationException::withMessages(['archivos' => __('La carpeta trae dos archivos con la misma ruta: :ruta.', ['ruta' => $ruta])]);
            }
            $vistas[] = mb_strtolower($ruta);
            $entradas[] = ['archivo' => $archivo, 'ruta' => $ruta];
        }

        return $entradas;
    }

    /**
     * Ruta relativa de un archivo dentro de la carpeta, normalizada (RF11). Rechaza lo que podría escapar
     * del directorio del recurso o del .zip («zip slip»): rutas absolutas, unidades de Windows, `.`, `..`,
     * separadores invertidos, segmentos vacíos y caracteres de control.
     *
     * @throws ValidationException
     */
    private function rutaDeCarpetaSegura(string $ruta): string
    {
        $ruta = trim($ruta);
        $invalida = fn (): never => throw ValidationException::withMessages([
            'archivos' => __('La ruta «:ruta» no es válida dentro de la carpeta.', ['ruta' => $ruta]),
        ]);

        if ($ruta === '' || mb_strlen($ruta) > 400 || str_contains($ruta, '\\') || preg_match('/[\x00-\x1F\x7F]/', $ruta) === 1) {
            $invalida();
        }
        if (str_starts_with($ruta, '/') || preg_match('~^[a-zA-Z]:~', $ruta) === 1) {
            $invalida();
        }

        $segmentos = explode('/', $ruta);
        if (count($segmentos) > 10) {
            $invalida();
        }
        foreach ($segmentos as $segmento) {
            if ($segmento === '' || $segmento === '.' || $segmento === '..' || mb_strlen($segmento) > 120) {
                $invalida();
            }
        }

        return $ruta;
    }

    /**
     * Tipo y tamaño del archivo (RF11). El componente valida lo mismo; esta es la última defensa.
     *
     * @throws ValidationException
     */
    private function exigirArchivoAdmitido(UploadedFile $archivo): void
    {
        Validator::make(['archivo' => $archivo], ['archivo' => self::reglaDeArchivo()], [
            'archivo.mimes' => __('Solo puede subir archivos :tipos.', ['tipos' => implode(', ', self::EXTENSIONES)]),
            'archivo.extensions' => __('Solo puede subir archivos :tipos.', ['tipos' => implode(', ', self::EXTENSIONES)]),
            'archivo.max' => __('El archivo no puede pasar de :mb MB.', ['mb' => intdiv(self::MAXIMO_KB, 1024)]),
        ])->validate();
    }

    /**
     * Dirección de un enlace o un video: solo http y https.
     *
     * @throws ValidationException
     */
    private function exigirUrlAdmitida(string $url): void
    {
        $esquema = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array($esquema, ['http', 'https'], true)) {
            throw ValidationException::withMessages(['url' => __('Escriba una dirección válida que empiece por https://.')]);
        }
    }

    /**
     * Lo que lleva más de 30 días en la papelera ya no se restaura: el job PurgarPapelera lo borra (RF11).
     *
     * @throws ValidationException
     */
    private function exigirRestaurable(mixed $eliminadoEn): void
    {
        if (! is_string($eliminadoEn) && ! $eliminadoEn instanceof DateTimeInterface) {
            throw ValidationException::withMessages(['papelera' => __('Este elemento no está en la papelera.')]);
        }
        if (Carbon::parse($eliminadoEn)->lt(now()->subDays(self::DIAS_PAPELERA))) {
            throw ValidationException::withMessages(['papelera' => __('Pasaron más de :dias días desde que se eliminó, así que ya no se puede restaurar.', ['dias' => self::DIAS_PAPELERA])]);
        }
    }

    private function recursosDe(int $seccionId): Builder
    {
        return DB::table('resources')->join('units', 'units.id', '=', 'resources.unit_id')->where('units.section_id', $seccionId);
    }

    private function recursoDeSeccion(int $seccionId, int $recursoId): Builder
    {
        return DB::table('resources')->where('id', $recursoId)->whereIn('unit_id', DB::table('units')->where('section_id', $seccionId)->select('id'));
    }

    private function unidadDeSeccion(int $seccionId, int $unidadId): Builder
    {
        return DB::table('units')->where('id', $unidadId)->where('section_id', $seccionId);
    }

    /**
     * Modo de finalización de la copia (ADR-067): una regla que apunta a una evaluación o a un hilo de la sección de
     * origen no existe en la de destino, así que el recurso se copia manual y el docente vuelve a fijarla.
     */
    private function modoCopiado(\stdClass $recurso): string
    {
        return $this->reglaCopiada($recurso) === null ? 'manual' : 'automatic';
    }

    private function reglaCopiada(\stdClass $recurso): ?string
    {
        $regla = ProgresoService::regla($recurso);

        return ($regla['type'] ?? null) === 'opened' ? json_encode(['type' => 'opened']) : null;
    }

    private function matricula(User $usuario, int $seccionId): ?int
    {
        $id = DB::table('enrollments')->where('student_id', $usuario->id)->where('section_id', $seccionId)->where('status', 'active')->value('id');

        return $id === null ? null : (int) $id;
    }
}
