<?php

namespace App\Modules\Calificaciones\Livewire;

use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Calificaciones\Services\ComputoService;
use App\Support\Alcance;
use App\Support\Exportacion;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vista de sección › Notas (RF17, RF18, maqueta 20): libro de calificaciones del docente, lectura y
 * corrección del Coordinador (período cerrado) y boleta del estudiante por cómputo y por unidad.
 */
#[Layout('layouts::app')]
class NotasSeccion extends Component
{
    use PaginaDeSeccion;

    public ?int $notaId = null;

    public ?float $nuevaNota = null;

    public string $motivo = '';

    public function publicar(int $item, CalificacionService $calificaciones): void
    {
        try {
            $n = $calificaciones->publicar(auth()->user(), $item);
            $this->dispatch('aviso', tipo: 'exito', mensaje: trans_choice('{0} Actividad publicada.|{1} Se publicó 1 nota.|[2,*] Se publicaron :count notas.', $n));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    /**
     * El libro a Excel, PDF o CSV (RF17, ADR-049). El permiso y el alcance los exige `libroExportable()`:
     * el estudiante, con alcance P, recibe 403 igual que en la pantalla.
     */
    public function exportar(string $formato, CalificacionService $calificaciones): StreamedResponse
    {
        $libro = $calificaciones->libroExportable(auth()->user(), $this->seccionId());
        $nombre = 'notas-'.Exportacion::nombre($libro['titulo']);

        return match ($formato) {
            'xlsx' => Exportacion::xlsx($nombre.'.xlsx', $libro['columnas'], $libro['filas'], titulo: __('Libro de calificaciones'), subtitulo: $libro['titulo']),
            'pdf' => Exportacion::pdf($nombre.'.pdf', 'exportaciones.tabla', [
                'titulo' => __('Libro de calificaciones'),
                'subtitulo' => $libro['titulo'],
                'columnas' => $libro['columnas'],
                'filas' => $libro['filas'],
                'desdeColumna' => 2,
            ], orientacion: 'landscape'),
            default => Exportacion::csv($nombre.'.csv', $libro['columnas'], $libro['filas']),
        };
    }

    public function abrirCorreccion(int $nota): void
    {
        $this->notaId = $nota;
        $this->reset('nuevaNota', 'motivo');
        $this->dispatch('abrir-modal', 'corregir-nota');
    }

    public function corregir(CalificacionService $calificaciones): void
    {
        $this->validate(['nuevaNota' => 'required|numeric|min:0|max:10', 'motivo' => 'required|string|min:5|max:300'], [], ['nuevaNota' => 'nota', 'motivo' => 'motivo']);
        try {
            $calificaciones->corregir(auth()->user(), (int) $this->notaId, (float) $this->nuevaNota, $this->motivo);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo === 'motivo' ? 'motivo' : 'nuevaNota', $mensajes[0]);
            }

            return;
        }
        $this->dispatch('cerrar-modal', 'corregir-nota');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Nota corregida. El cambio quedó en la auditoría.'));
    }

    public function render(CalificacionService $calificaciones, ComputoService $computos, Alcance $alcance): View
    {
        $usuario = auth()->user();
        $seccion = $this->seccionId();
        $esEstudiante = $alcance->de($usuario, 'calificaciones.ver') === 'P';
        $gestiona = ! $esEstudiante && $alcance->puedeEnSeccion($usuario, 'calificaciones.gestionar', $seccion);

        return $this->paginaDeSeccion('modules.calificaciones.notas', [
            'esEstudiante' => $esEstudiante,
            'boleta' => $esEstudiante ? $calificaciones->boleta($usuario, $seccion) : null,
            'libro' => $esEstudiante ? null : $calificaciones->libro($usuario, $seccion),
            'gestiona' => $gestiona,
            'problemasPesos' => $gestiona ? $computos->problemas($seccion) : [],
            'corrige' => ! $esEstudiante && $usuario->hasRole('coordinador') && $alcance->puedeEnSeccion($usuario, 'calificaciones.corregir', $seccion),
            'puedeMensajear' => ! $esEstudiante && $alcance->puedeEnSeccion($usuario, 'mensajes.condicionales.enviar', $seccion),
            'servicio' => $calificaciones,
            'minima' => CalificacionService::notaMinima(),
        ]);
    }
}
