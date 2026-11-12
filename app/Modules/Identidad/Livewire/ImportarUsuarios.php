<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Services\ImportacionService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Importar usuarios (RF04): archivo → vista previa → confirmar → informe del lote.
 */
#[Layout('layouts::app')]
class ImportarUsuarios extends Component
{
    use PaginaLivewire;
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $archivo = null;

    /** @var array<string, mixed>|null */
    public ?array $previa = null;

    public function updatedArchivo(ImportacionService $importacion): void
    {
        $this->validate(['archivo' => 'required|file|mimes:csv,txt,xlsx|max:5120'], [], ['archivo' => 'archivo']);
        $resultado = $importacion->vistaPrevia(auth()->user(), $this->archivo->getRealPath(), $this->extension());
        $this->previa = ['validas' => $resultado['validas']->count(), 'duplicados' => $resultado['duplicados'], 'errores' => $resultado['errores']->take(50)->all(),
            'muestra' => $resultado['validas']->take(10)->map(fn ($f) => [$f['_correo'], trim($f['nombres'].' '.$f['apellidos']), $f['_rol']])->all()];
    }

    /**
     * El archivo subido se guarda con un nombre temporal sin extensión útil: el formato lo da el nombre
     * original que puso el navegador.
     */
    private function extension(): string
    {
        return pathinfo((string) $this->archivo->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'csv';
    }

    /**
     * Plantilla del archivo, en el mismo CSV que pide la pantalla: encabezados y una fila de ejemplo con una
     * carrera y un plan reales. Con BOM, para que Excel la abra con los acentos bien y sin el asistente de
     * importación de texto.
     */
    public function plantilla(ImportacionService $importacion): StreamedResponse
    {
        $plantilla = $importacion->plantilla(auth()->user());

        return response()->streamDownload(function () use ($plantilla) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $plantilla['columnas']);
            foreach ($plantilla['filas'] as $fila) {
                fputcsv($salida, $fila);
            }
            fclose($salida);
        }, 'plantilla-importacion-estudiantes.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importar(ImportacionService $importacion): void
    {
        $this->validate(['archivo' => 'required|file|mimes:csv,txt,xlsx|max:5120'], [], ['archivo' => 'archivo']);
        $lote = $importacion->importar(auth()->user(), $this->archivo->getRealPath(), $this->archivo->getClientOriginalName());
        $this->redirectRoute('usuarios.lote', ['lote' => $lote], navigate: true);
    }

    public function render(ImportacionService $importacion): View
    {
        return $this->pagina('modules.identidad.importar', ['lotes' => $importacion->lotes(auth()->user()), 'columnas' => ImportacionService::COLUMNAS]);
    }
}
