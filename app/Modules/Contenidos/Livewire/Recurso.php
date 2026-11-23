<?php

namespace App\Modules\Contenidos\Livewire;

use App\Modules\Contenidos\Services\ContenidoService;
use App\Modules\Contenidos\Services\ProgresoService;
use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Ver recurso (RF11, RF12): PDF, página, enlace o video integrado. Registra la apertura una vez al entrar y, con un
 * latido desde la página, el tiempo con la pestaña visible (RF13). Muestra al estudiante cómo se completa y deja al
 * docente cambiar la regla (RF18, ADR-067). Si es una **lección** (RF38, ADR-091), debajo del material va su
 * cuestionario, que se intenta después de completar la lectura.
 */
#[Layout('layouts::app')]
class Recurso extends Component
{
    use PaginaDeSeccion;

    #[Locked]
    public string $recursoUlid = '';

    /** @var array<string, mixed> */
    private array $datos = [];

    public function mount(string $recurso, ContenidoService $contenidos): void
    {
        $this->recursoUlid = $recurso;
        $this->datos = $contenidos->recurso(auth()->user(), $this->seccionId(), $recurso);
        $contenidos->registrarApertura(auth()->user(), $this->seccionId(), $this->datos['recurso']);
        $this->pdf = (string) ($this->datos['recurso']->pdf_mode ?? 'viewer');
        $this->cargarRegla();
        // Solo quien edita carga el HTML en el estado del componente: al estudiante no le hace falta viajar con él.
        $this->texto = $contenidos->puedeGestionar(auth()->user(), $this->seccionId())
            ? (string) ($this->datos['recurso']->html_content ?? '')
            : '';
    }

    public string $pdf = '';

    /** Cómo se completa el recurso (RF18): «manual» o «automatic». Lo edita el docente. */
    public string $completion = 'manual';

    /** Tipo de regla automática: opened, min_score, submission o forum_posts (ADR-067). */
    public string $reglaTipo = 'opened';

    public ?int $reglaEvaluacion = null;

    public string $reglaMinimo = '6.0';

    public ?int $reglaHilo = null;

    public int $reglaMensajes = 2;

    /** Minutos mínimos de la lectura comprobada (RF38). */
    public int $lecturaMinutos = 5;

    /** Si la lectura comprobada exige llegar al final del material. */
    public bool $lecturaFinal = true;

    /** Contenido de la página en el editor del docente (RF11). Lo sanea el Service al guardarlo. */
    public string $texto = '';

    /**
     * Segundos con la pestaña visible desde el último envío (RF13, CP-RF13-02). Sin aviso: corre en segundo plano.
     */
    public function latido(int $segundos, ContenidoService $contenidos): void
    {
        $contenidos->registrarTiempo(auth()->user(), $this->seccionId(), $this->recursoUlid, $segundos);
        $this->skipRender();
    }

    public function marcarLeido(ContenidoService $contenidos): void
    {
        try {
            $contenidos->marcarLeido(auth()->user(), $this->seccionId(), (int) $this->datos()['recurso']->id);
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());

            return;
        }
        $this->datos = [];
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Recurso marcado como completado.'));
    }

    /**
     * El estudiante llegó al final del material de la lección (RF38): la última página del PDF o el final de la página.
     */
    public function llegoAlFinal(ContenidoService $contenidos): void
    {
        $contenidos->registrarFinal(auth()->user(), $this->seccionId(), $this->recursoUlid);
        $this->datos = [];
    }

    /**
     * «Intentar cuestionario» de la lección, ya confirmado en el modal: abre el intento en el layout de examen (RF16).
     * Que la lectura esté completa lo comprueba CuestionarioService::iniciar().
     */
    public function comenzarCuestionario(CuestionarioService $cuestionarios): void
    {
        $leccion = ContenidoService::cuestionarioDeLeccion((int) $this->datos()['recurso']->id) ?? abort(404);
        try {
            $intento = $cuestionarios->iniciar(auth()->user(), $this->seccionId(), (string) $leccion->ulid);
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());

            return;
        }
        $this->redirectRoute('intentos.show', ['intento' => $intento]);
    }

    /**
     * El docente cambia cómo se completa el recurso ya publicado (RF18). El formato se valida aquí; la regla
     * contra la sección, en el Service, que además completa a quienes ya la cumplen.
     */
    public function cambiarRegla(ContenidoService $contenidos): void
    {
        $this->validate([
            'completion' => 'required|in:manual,automatic',
            'reglaTipo' => 'required|in:'.implode(',', array_keys(ProgresoService::REGLAS)),
            'reglaEvaluacion' => 'nullable|integer',
            'reglaMinimo' => 'nullable|numeric|between:0,10',
            'reglaHilo' => 'nullable|integer',
            'reglaMensajes' => 'nullable|integer|between:1,20',
            'lecturaMinutos' => 'nullable|integer|between:1,'.ProgresoService::MAXIMO_MINUTOS_LECTURA,
            'lecturaFinal' => 'boolean',
        ], [], ['completion' => __('forma de completarlo'), 'reglaTipo' => __('regla'), 'reglaMinimo' => __('nota mínima'), 'reglaMensajes' => __('cantidad de mensajes'), 'lecturaMinutos' => __('minutos de lectura')]);

        $contenidos->cambiarRegla(auth()->user(), $this->seccionId(), (int) $this->datos()['recurso']->id, $this->completion, $this->regla());
        $this->datos = [];
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Se actualizó cómo se completa el recurso.'));
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
     * El docente cambia, ya publicado el archivo, si el PDF se ve incrustado o solo se descarga (RF11).
     */
    public function cambiarModoPdf(ContenidoService $contenidos): void
    {
        $this->validate(['pdf' => 'required|in:'.implode(',', array_keys(ContenidoService::MODOS_PDF))], [], ['pdf' => __('modo')]);

        $contenidos->cambiarModoPdf(auth()->user(), $this->seccionId(), (int) $this->datos()['recurso']->id, $this->pdf);
        $this->datos = [];
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Se actualizó cómo se abre el archivo.'));
    }

    /**
     * El docente reescribe el contenido de una página ya publicada (RF11). El formato se valida aquí; el
     * saneado del HTML y el permiso, en el Service.
     */
    public function editarPagina(ContenidoService $contenidos): void
    {
        $this->validate(['texto' => 'required|string|max:60000'], [], ['texto' => __('contenido')]);

        $contenidos->editarPagina(auth()->user(), $this->seccionId(), (int) $this->datos()['recurso']->id, $this->texto);
        $this->datos = [];
        $this->texto = (string) ($this->datos()['recurso']->html_content ?? '');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Se actualizó el contenido de la página.'));
    }

    /**
     * Lleva al formulario la regla guardada del recurso (RF18).
     */
    private function cargarRegla(): void
    {
        $regla = ProgresoService::regla($this->datos['recurso']);
        $this->completion = $regla === null ? 'manual' : 'automatic';
        $this->reglaTipo = (string) ($regla['type'] ?? 'opened');
        $this->reglaEvaluacion = isset($regla['assessment_id']) ? (int) $regla['assessment_id'] : null;
        $this->reglaMinimo = isset($regla['min']) ? number_format((float) $regla['min'], 1, '.', '') : '6.0';
        $this->reglaHilo = isset($regla['thread_id']) ? (int) $regla['thread_id'] : null;
        $this->reglaMensajes = (int) ($regla['count'] ?? 2);
        $this->lecturaMinutos = max(1, intdiv((int) ($regla['min_seconds'] ?? 300), 60));
        $this->lecturaFinal = (bool) ($regla['to_end'] ?? true);
    }

    /**
     * Archivos de una carpeta agrupados por subcarpeta, para mostrar su estructura (RF11).
     *
     * @param  Collection<int, object>  $archivos
     * @return array<string, array<int, object>>
     */
    public static function arbol(Collection $archivos): array
    {
        $grupos = [];
        foreach ($archivos as $archivo) {
            $directorio = dirname((string) $archivo->path);
            $grupos[$directorio === '.' ? '' : $directorio][] = $archivo;
        }
        ksort($grupos);

        return $grupos;
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(): array
    {
        if ($this->datos === []) {
            $this->datos = app(ContenidoService::class)->recurso(auth()->user(), $this->seccionId(), $this->recursoUlid);
        }

        return $this->datos;
    }

    /**
     * Enlace embebible de YouTube o Drive, o null (RF12). La lógica vive en el Service; aquí solo se delega.
     */
    public static function embebido(?string $url): ?string
    {
        return ContenidoService::embebido($url);
    }

    public function render(ContenidoService $contenidos, ProgresoService $progreso, CuestionarioService $cuestionarios): View
    {
        $datos = $this->datos();
        $gestiona = $contenidos->puedeGestionar(auth()->user(), $this->seccionId());
        $leccion = ContenidoService::cuestionarioDeLeccion((int) $datos['recurso']->id);

        return $this->paginaDeSeccion('modules.contenidos.recurso', $datos + ($gestiona ? $progreso->opciones($this->seccionId()) : ['cuestionarios' => collect(), 'tareas' => collect(), 'hilos' => collect()]) + [
            'video' => $datos['recurso']->resource_type === 'video' ? ContenidoService::embebido($datos['recurso']->url) : null,
            'esEstudiante' => auth()->user()->hasRole('estudiante'),
            'latido' => ContenidoService::LATIDO_SEGUNDOS,
            'gestiona' => $gestiona,
            'reglas' => ProgresoService::REGLAS,
            'reglaEnPalabras' => $progreso->describir(ProgresoService::regla($datos['recurso'])),
            'modosPdf' => ContenidoService::MODOS_PDF,
            'arbol' => self::arbol($datos['archivos']),
            'lectura' => $contenidos->lectura(auth()->user(), $this->seccionId(), $datos['recurso']),
            'esLectura' => (ProgresoService::regla($datos['recurso'])['type'] ?? null) === 'read',
            'leccion' => $leccion === null ? null : $cuestionarios->cuestionario(auth()->user(), $this->seccionId(), (string) $leccion->ulid),
        ]);
    }
}
