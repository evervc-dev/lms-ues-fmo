<?php

namespace App\Modules\Contenidos\Livewire;

use App\Modules\Contenidos\Services\ContenidoService;
use App\Modules\Contenidos\Services\ProgresoService;
use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\Navegacion\FormularioPorPasos;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Agregar un recurso a una unidad (RF11, RF12). Va en página y no en un modal: pasa de 6 campos, el
 * formulario cambia según el tipo y lleva subida de archivo (docs/13 Layout/Layout base.md).
 */
#[Layout('layouts::app')]
class NuevoRecurso extends Component
{
    use FormularioPorPasos;
    use PaginaDeSeccion;
    use WithFileUploads;

    #[Locked]
    public int $unidadId;

    public string $tipo = 'file';

    public string $tituloRecurso = '';

    public string $descripcion = '';

    public string $url = '';

    public string $texto = '';

    public string $pdf = 'viewer';

    /** Cómo se completa el recurso (RF18): «manual» («ya lo leí») o «automatic» (con una regla). */
    public string $completion = 'manual';

    /** Tipo de regla automática: opened, min_score, submission o forum_posts (ADR-067). */
    public string $reglaTipo = 'opened';

    /** Cuestionario de la regla de nota mínima o tarea de la regla de entrega. */
    public ?int $reglaEvaluacion = null;

    /** Nota mínima de la regla, de 0 a 10. */
    public string $reglaMinimo = '6.0';

    /** Hilo del foro de la regla de mensajes. */
    public ?int $reglaHilo = null;

    /** Mensajes exigidos en el hilo, de 1 a 20. */
    public int $reglaMensajes = 2;

    /** Lección con cuestionario (RF38): el material (archivo o página) lleva un cuestionario que se intenta al leerlo. */
    public bool $leccion = false;

    /** Minutos mínimos con la pestaña visible para dar por leída la lección (RF38). */
    public int $lecturaMinutos = 5;

    /** Si además debe llegar al final del material (última página del PDF o final de la página). */
    public bool $lecturaFinal = true;

    /**
     * Cuestionario de la lección, con los mismos datos que «Nuevo cuestionario» (RF16).
     *
     * @var array{abre: string, cierra: string, minutos: int|string, intentos: int|string, navegacion: string, banco: int|string|null, cantidad: int|string, tema: string}
     */
    public array $cuestionario = ['abre' => '', 'cierra' => '', 'minutos' => 20, 'intentos' => 1, 'navegacion' => 'free', 'banco' => null, 'cantidad' => 10, 'tema' => ''];

    /** @var TemporaryUploadedFile|null */
    public $archivo = null;

    /**
     * Archivos de la carpeta elegida en el navegador (RF11).
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $archivos = [];

    /**
     * Ruta relativa de cada archivo dentro de la carpeta, en el mismo orden que `$archivos`. La escribe el
     * navegador (`webkitRelativePath`) desde el componente Alpine; el Service la normaliza y la valida.
     *
     * @var array<int, string>
     */
    public array $rutas = [];

    public function mount(int $unidad, ContenidoService $contenidos): void
    {
        $this->unidadId = $unidad;
        // Comprueba el permiso y el alcance sobre la unidad antes de mostrar el formulario.
        $this->unidad($contenidos);
        $this->cuestionario['abre'] = now()->addDay()->setTime(8, 0)->format('Y-m-d\TH:i');
        $this->cuestionario['cierra'] = now()->addWeek()->setTime(23, 59)->format('Y-m-d\TH:i');
    }

    /**
     * Una lección se completa con la lectura comprobada; al dejar de serlo, vuelve al punto de partida del tipo.
     */
    public function updatedLeccion(bool $valor): void
    {
        if ($valor) {
            $this->completion = 'automatic';
            $this->reglaTipo = 'read';

            return;
        }
        $this->updatedTipo($this->tipo);
    }

    /**
     * Al cambiar de banco, el tema elegido ya no aplica (los temas son del banco).
     */
    public function updatedCuestionario(mixed $valor, string $clave): void
    {
        if ($clave === 'banco') {
            $this->cuestionario['tema'] = '';
        }
    }

    /**
     * La página se completa al abrirla y el resto, a mano: es el punto de partida que el docente puede cambiar (RF18).
     */
    public function updatedTipo(string $valor): void
    {
        if ($this->leccion && in_array($valor, ContenidoService::TIPOS_LECCION, true)) {
            return;
        }
        $this->leccion = false;
        $this->completion = $valor === 'page' ? 'automatic' : 'manual';
        $this->reglaTipo = 'opened';
    }

    /**
     * La regla elegida, tal como la espera el Service (null si el recurso se completa a mano).
     *
     * @return array<string, mixed>|null
     */
    public function regla(): ?array
    {
        return match ($this->completion === 'automatic' ? $this->reglaTipo : null) {
            'opened' => ['type' => 'opened'],
            'min_score' => ['type' => 'min_score', 'assessment_id' => $this->reglaEvaluacion, 'min' => $this->reglaMinimo],
            'submission' => ['type' => 'submission', 'assessment_id' => $this->reglaEvaluacion],
            'forum_posts' => ['type' => 'forum_posts', 'thread_id' => $this->reglaHilo, 'count' => $this->reglaMensajes],
            'read' => ['type' => 'read', 'min_seconds' => $this->lecturaMinutos * 60, 'to_end' => $this->lecturaFinal],
            default => null,
        };
    }

    /**
     * Datos del cuestionario de la lección, tal como los espera CuestionarioService::crearCuestionario(). Es una
     * verificación de lectura: no lleva cómputo ni peso y no suma a la nota (ADR-091).
     *
     * @return array<string, mixed>|null
     */
    public function datosCuestionario(): ?array
    {
        if (! $this->leccion) {
            return null;
        }
        $c = $this->cuestionario;

        return ['verificacion' => true, 'titulo' => $this->tituloRecurso, 'instrucciones' => null, 'abre' => $c['abre'], 'cierra' => $c['cierra'],
            'minutos' => (int) $c['minutos'], 'intentos' => (int) $c['intentos'], 'navegacion' => $c['navegacion'],
            'banco' => (int) $c['banco'], 'cantidad' => (int) $c['cantidad'], 'tema' => $c['tema'] ?: null];
    }

    /**
     * Qué recurso es, su contenido según el tipo y el resumen para confirmar.
     *
     * @return array<int, array{titulo: string, descripcion?: string, reglas?: array<string, mixed>, atributos?: array<string, string>}>
     */
    public function pasos(): array
    {
        $pasos = [
            [
                'titulo' => __('Qué es'),
                'descripcion' => __('Tipo, título y descripción.'),
                'reglas' => ['tipo' => 'required|in:'.implode(',', array_keys(ContenidoService::TIPOS)), 'tituloRecurso' => 'required|string|max:200', 'descripcion' => 'nullable|string|max:500', 'leccion' => 'boolean'],
                'atributos' => ['tituloRecurso' => __('título'), 'tipo' => __('tipo')],
            ],
            [
                'titulo' => __('Contenido'),
                'descripcion' => __('El archivo, la carpeta, el texto o el enlace.'),
                'reglas' => [
                    'archivo' => 'nullable|'.ContenidoService::reglaDeArchivo(),
                    'archivos' => 'nullable|array|max:'.ContenidoService::MAXIMO_ARCHIVOS,
                    'archivos.*' => ContenidoService::reglaDeArchivo(),
                    'rutas' => 'nullable|array|max:'.ContenidoService::MAXIMO_ARCHIVOS,
                    'rutas.*' => 'string|max:400',
                    'texto' => 'nullable|string|max:60000',
                    'url' => 'nullable|url:http,https|max:2000',
                ],
                'atributos' => ['archivo' => __('archivo'), 'archivos' => __('carpeta'), 'texto' => __('contenido'), 'url' => __('enlace')],
            ],
            [
                'titulo' => __('Cómo se completa'),
                'descripcion' => __('A mano o con una regla.'),
                'reglas' => [
                    'completion' => 'required|in:manual,automatic',
                    'reglaTipo' => 'required|in:'.implode(',', array_keys(ProgresoService::REGLAS)),
                    'reglaEvaluacion' => 'nullable|integer',
                    'reglaMinimo' => 'nullable|numeric|between:0,10',
                    'reglaHilo' => 'nullable|integer',
                    'reglaMensajes' => 'nullable|integer|between:1,20',
                    'lecturaMinutos' => 'required|integer|between:1,'.ProgresoService::MAXIMO_MINUTOS_LECTURA,
                    'lecturaFinal' => 'boolean',
                ],
                'atributos' => ['completion' => __('forma de completarlo'), 'reglaTipo' => __('regla'), 'reglaMinimo' => __('nota mínima'), 'reglaMensajes' => __('cantidad de mensajes'), 'lecturaMinutos' => __('minutos de lectura')],
            ],
        ];
        if ($this->leccion) {
            $pasos[] = [
                'titulo' => __('Verificación'),
                'descripcion' => __('Cuestionario corto al terminar la lectura; no suma a la nota.'),
                'reglas' => [
                    'cuestionario.abre' => 'required|date', 'cuestionario.cierra' => 'required|date|after:cuestionario.abre',
                    'cuestionario.minutos' => 'required|integer|min:1|max:600', 'cuestionario.intentos' => 'required|integer|min:1|max:10',
                    'cuestionario.navegacion' => 'required|in:free,linear', 'cuestionario.banco' => 'required|integer',
                    'cuestionario.cantidad' => 'required|integer|min:0|max:200', 'cuestionario.tema' => 'nullable|string|max:100',
                ],
                'atributos' => ['cuestionario.abre' => __('apertura'),
                    'cuestionario.cierra' => __('cierre'), 'cuestionario.minutos' => __('minutos'), 'cuestionario.intentos' => __('intentos'),
                    'cuestionario.navegacion' => __('navegación'), 'cuestionario.banco' => __('banco'), 'cuestionario.cantidad' => __('cantidad')],
            ];
        }
        $pasos[] = [
            'titulo' => __('Revisar y publicar'),
            'descripcion' => __('Confirme antes de publicarlo en la unidad.'),
        ];

        return $pasos;
    }

    /**
     * Mensajes de los límites del archivo, en español y de usted.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $tipos = ['tipos' => implode(', ', ContenidoService::EXTENSIONES)];
        $mb = ['mb' => intdiv(ContenidoService::MAXIMO_KB, 1024)];

        return [
            'archivo.mimes' => __('Solo puede subir archivos :tipos.', $tipos),
            'archivo.extensions' => __('Solo puede subir archivos :tipos.', $tipos),
            'archivo.max' => __('El archivo no puede pasar de :mb MB.', $mb),
            'archivos.max' => __('La carpeta no puede tener más de :n archivos.', ['n' => ContenidoService::MAXIMO_ARCHIVOS]),
            'archivos.*.mimes' => __('Solo puede subir archivos :tipos.', $tipos),
            'archivos.*.extensions' => __('Solo puede subir archivos :tipos.', $tipos),
            'archivos.*.max' => __('El archivo no puede pasar de :mb MB.', $mb),
            'rutas.max' => __('La carpeta no puede tener más de :n archivos.', ['n' => ContenidoService::MAXIMO_ARCHIVOS]),
            'url.url' => __('Escriba una dirección válida que empiece por https://.'),
        ];
    }

    /**
     * Lo que no cabe en una regla de formato: la página necesita texto y la carpeta, archivos.
     */
    protected function validarPaso(int $paso): void
    {
        // La regla se valida contra la sección (el cuestionario, la tarea o el hilo deben ser suyos): lo hace el Service.
        if ($paso === 3 && $this->completion === 'automatic') {
            app(ProgresoService::class)->validarRegla($this->seccionId(), $this->regla() ?? []);

            return;
        }
        if ($paso !== 2) {
            return;
        }
        if ($this->tipo === 'page' && trim(strip_tags($this->texto)) === '') {
            throw ValidationException::withMessages(['texto' => __('Escriba el contenido de la página.')]);
        }
        if ($this->tipo === 'folder' && $this->archivos === []) {
            throw ValidationException::withMessages(['archivos' => __('Elija una carpeta con al menos un archivo.')]);
        }
    }

    /**
     * Quita la carpeta elegida para volver a empezar.
     */
    public function quitarCarpeta(): void
    {
        $this->reset('archivos', 'rutas');
        $this->resetValidation(['archivos', 'rutas']);
    }

    public function agregar(ContenidoService $contenidos): void
    {
        $this->validarTodosLosPasos();

        // guardarPorPasos: si el Service rechaza la regla, el aviso se ve en vez de quedar oculto tras el resumen.
        $publicado = $this->guardarPorPasos(function () use ($contenidos): bool {
            $contenidos->agregarRecurso(auth()->user(), $this->unidadId, [
                'tipo' => $this->tipo, 'titulo' => $this->tituloRecurso, 'descripcion' => $this->descripcion ?: null,
                'url' => $this->url ?: null, 'texto' => $this->texto, 'pdf' => $this->pdf, 'archivo' => $this->archivo,
                'archivos' => $this->archivos, 'rutas' => $this->rutas,
                'completion' => $this->completion, 'regla' => $this->regla(), 'cuestionario' => $this->datosCuestionario(),
            ]);

            return true;
        });
        if ($publicado !== true) {
            return;
        }

        session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => $this->leccion ? __('Lección publicada con su cuestionario.') : __('Recurso publicado.')]);
        $this->redirectRoute('secciones.contenidos', ['seccion' => $this->seccion], navigate: true);
    }

    public function render(ContenidoService $contenidos, ProgresoService $progreso, CuestionarioService $cuestionarios): View
    {
        $banco = $this->leccion && $this->cuestionario['banco'] ? (int) $this->cuestionario['banco'] : null;

        return $this->paginaDeSeccion('modules.contenidos.nuevo-recurso', $progreso->opciones($this->seccionId()) + [
            'tiposLeccion' => ContenidoService::TIPOS_LECCION,
            'bancos' => $this->leccion ? $cuestionarios->bancos(auth()->user()) : collect(),
            'temas' => $banco === null ? collect() : $cuestionarios->temasDelBanco(auth()->user(), $banco),
            'disponibles' => $banco === null ? 0 : DB::table('questions')->where('bank_id', $banco)->when($this->cuestionario['tema'], fn ($q, $t) => $q->where('topic', $t))->count(),
            'unidad' => $this->unidad($contenidos),
            'reglas' => ProgresoService::REGLAS,
            'reglaEnPalabras' => $this->completion === 'automatic' ? $progreso->describir($this->regla()) : null,
            'tipos' => ContenidoService::TIPOS,
            'modosPdf' => ContenidoService::MODOS_PDF,
            'accept' => ContenidoService::accept(),
            'extensiones' => ContenidoService::EXTENSIONES,
            'maximoMb' => intdiv(ContenidoService::MAXIMO_KB, 1024),
            'maximoArchivos' => ContenidoService::MAXIMO_ARCHIVOS,
            'maximoTotalMb' => intdiv(ContenidoService::MAXIMO_TOTAL_KB, 1024),
        ]);
    }

    private function unidad(ContenidoService $contenidos): object
    {
        return $contenidos->unidades(auth()->user(), $this->seccionId())
            ->firstWhere('id', $this->unidadId) ?? abort(404);
    }
}
