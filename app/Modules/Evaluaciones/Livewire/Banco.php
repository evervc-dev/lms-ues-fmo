<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Modules\Evaluaciones\Services\ImportacionPreguntasService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Banco de preguntas (RF16, maqueta 32): preguntas de 4 tipos, carga desde Excel y compartir con colegas.
 */
#[Layout('layouts::app')]
class Banco extends Component
{
    use PaginaLivewire;
    use WithFileUploads;

    #[Locked]
    public int $bancoId;

    public ?int $colega = null;

    /** @var TemporaryUploadedFile|null */
    public $archivo = null;

    /**
     * Resumen del archivo subido antes de importarlo.
     *
     * @var array{total: int, porTipo: array<string, int>, errores: list<string>, muestra: list<string>}|null
     */
    public ?array $previa = null;

    public function abrirImportacion(): void
    {
        $this->reset('archivo', 'previa');
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'importar-preguntas');
    }

    public function updatedArchivo(ImportacionPreguntasService $importacion): void
    {
        $this->previa = null;
        $this->validate(['archivo' => 'required|file|mimes:xlsx,csv,txt|max:5120'], [], ['archivo' => __('archivo')]);
        $analisis = $importacion->analizar($this->archivo->getRealPath(), $this->extension());
        $this->previa = [
            'total' => count($analisis['preguntas']),
            'porTipo' => collect($analisis['preguntas'])->countBy('tipo')->all(),
            'errores' => array_slice($analisis['errores'], 0, 50),
            'muestra' => collect($analisis['preguntas'])->take(5)->map(fn (array $p) => $p['enunciado'])->all(),
        ];
    }

    public function importar(ImportacionPreguntasService $importacion): void
    {
        $this->validate(['archivo' => 'required|file|mimes:xlsx,csv,txt|max:5120'], [], ['archivo' => __('archivo')]);
        $total = $importacion->importar(auth()->user(), $this->bancoId, $this->archivo->getRealPath(), $this->extension());
        $this->reset('archivo', 'previa');
        $this->dispatch('cerrar-modal', 'importar-preguntas');
        $this->dispatch('aviso', tipo: 'exito', mensaje: trans_choice('{1} Se importó 1 pregunta.|[2,*] Se importaron :count preguntas.', $total));
    }

    /**
     * El archivo temporal no conserva la extensión: la da el nombre original.
     */
    private function extension(): string
    {
        return pathinfo((string) $this->archivo->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'xlsx';
    }

    public function mount(int $banco): void
    {
        $this->bancoId = $banco;
    }

    public function compartir(CuestionarioService $cuestionarios): void
    {
        $this->validate(['colega' => 'required|integer'], [], ['colega' => 'docente']);
        $cuestionarios->compartir(auth()->user(), $this->bancoId, (int) $this->colega);
        $this->reset('colega');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Banco compartido.'));
    }

    public function render(CuestionarioService $cuestionarios): View
    {
        $datos = $cuestionarios->banco(auth()->user(), $this->bancoId);

        return $this->pagina('modules.evaluaciones.banco', $datos + [
            'tipos' => CuestionarioService::TIPOS,
            'colegas' => DB::table('teacher_profiles')->join('users', 'users.id', '=', 'teacher_profiles.user_id')->where('users.id', '!=', auth()->id())
                ->orderBy('users.last_name')->get(['users.id', 'users.first_name', 'users.last_name']),
        ], titulo: $datos['banco']->name);
    }
}
