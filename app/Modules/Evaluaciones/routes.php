<?php

use App\Modules\Academica\Services\SeccionService;
use App\Modules\Evaluaciones\Livewire\Actividades;
use App\Modules\Evaluaciones\Livewire\Banco;
use App\Modules\Evaluaciones\Livewire\Bancos;
use App\Modules\Evaluaciones\Livewire\Cuestionario;
use App\Modules\Evaluaciones\Livewire\Entregas;
use App\Modules\Evaluaciones\Livewire\Intento;
use App\Modules\Evaluaciones\Livewire\NuevaPregunta;
use App\Modules\Evaluaciones\Livewire\NuevaTarea;
use App\Modules\Evaluaciones\Livewire\NuevoCuestionario;
use App\Modules\Evaluaciones\Livewire\Pendientes;
use App\Modules\Evaluaciones\Livewire\RevisionIntentoDocente;
use App\Modules\Evaluaciones\Livewire\Tarea;
use App\Modules\Evaluaciones\Services\ActividadService;
use App\Modules\Evaluaciones\Services\ImportacionPreguntasService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Vistas del registro (config/vistas.php): mismo nombre y URI. «crear» va antes de {tarea}/{cuestionario}.
Route::middleware('auth')->group(function (): void {
    Route::livewire('/secciones/{seccion}/actividades', Actividades::class)->middleware('permission:contenidos.ver')->name('secciones.actividades');
    Route::livewire('/secciones/{seccion}/tareas/crear', NuevaTarea::class)->middleware('permission:tareas.gestionar')->name('secciones.tareas.crear');
    Route::livewire('/secciones/{seccion}/tareas/{tarea}', Tarea::class)->middleware('permission:tareas.entregar|tareas.gestionar')->name('secciones.tareas.show');
    Route::livewire('/secciones/{seccion}/tareas/{tarea}/entregas', Entregas::class)->middleware('permission:calificaciones.gestionar')->name('secciones.tareas.entregas');

    // RF15: el archivo de una entrega. El PDF y las imágenes se ven dentro de la página; lo demás se descarga.
    // El permiso y el alcance los exige el Service: el docente de la sección o el estudiante dueño, nadie más.
    Route::get('/secciones/{seccion}/entregas/{entrega}/archivo', function (string $seccion, string $entrega, SeccionService $secciones, ActividadService $actividades) {
        $archivo = $actividades->archivoDeEntrega(auth()->user(), $secciones->idPorUlid($seccion), $entrega);
        abort_unless(Storage::exists($archivo['ruta']), 404, __('El archivo no está disponible.'));

        $extension = strtolower(pathinfo($archivo['nombre'], PATHINFO_EXTENSION));

        return in_array($extension, ['pdf', 'png', 'jpg', 'jpeg', 'webp'], true)
            ? Storage::response($archivo['ruta'], $archivo['nombre'])
            : Storage::download($archivo['ruta'], $archivo['nombre']);
    })->middleware('permission:calificaciones.gestionar|tareas.entregar')->name('secciones.entregas.archivo');
    Route::livewire('/secciones/{seccion}/cuestionarios/crear', NuevoCuestionario::class)->middleware('permission:cuestionarios.gestionar')->name('secciones.cuestionarios.crear');
    Route::livewire('/secciones/{seccion}/cuestionarios/{cuestionario}/editar', NuevoCuestionario::class)->middleware('permission:cuestionarios.gestionar')->name('secciones.cuestionarios.editar');
    Route::livewire('/secciones/{seccion}/cuestionarios/{cuestionario}/intentos/{intento}', RevisionIntentoDocente::class)->middleware('permission:cuestionarios.gestionar')->name('secciones.cuestionarios.intentos.show');
    Route::livewire('/secciones/{seccion}/cuestionarios/{cuestionario}', Cuestionario::class)->middleware('permission:cuestionarios.resolver|cuestionarios.gestionar')->name('secciones.cuestionarios.show');
    Route::livewire('/intentos/{intento}', Intento::class)->middleware('permission:cuestionarios.resolver')->name('intentos.show');
    Route::livewire('/pendientes-de-calificar', Pendientes::class)->middleware('permission:calificaciones.gestionar')->name('pendientes-de-calificar');
    Route::livewire('/bancos-de-preguntas', Bancos::class)->middleware('permission:banco-preguntas.gestionar')->name('bancos-de-preguntas');
    // Formato .xlsx para cargar preguntas a un banco (RF16): encabezados, instrucciones y un ejemplo de cada tipo.
    Route::get('/bancos-de-preguntas/formato', function (ImportacionPreguntasService $importacion) {
        $ruta = tempnam(sys_get_temp_dir(), 'formato-banco');
        $importacion->escribir($ruta);

        return response()->download($ruta, 'formato-banco-de-preguntas.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    })->middleware('permission:banco-preguntas.gestionar')->name('bancos-de-preguntas.formato');
    Route::livewire('/bancos-de-preguntas/{banco}/preguntas/crear', NuevaPregunta::class)->middleware('permission:banco-preguntas.gestionar')->whereNumber('banco')->name('bancos-de-preguntas.pregunta');
    Route::livewire('/bancos-de-preguntas/{banco}', Banco::class)->middleware('permission:banco-preguntas.gestionar')->whereNumber('banco')->name('bancos-de-preguntas.show');
});
