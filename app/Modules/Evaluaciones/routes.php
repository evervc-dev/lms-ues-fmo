<?php

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
use App\Modules\Evaluaciones\Livewire\Tarea;
use Illuminate\Support\Facades\Route;

// Vistas del registro (config/vistas.php): mismo nombre y URI. «crear» va antes de {tarea}/{cuestionario}.
Route::middleware('auth')->group(function (): void {
    Route::livewire('/secciones/{seccion}/actividades', Actividades::class)->middleware('permission:contenidos.ver')->name('secciones.actividades');
    Route::livewire('/secciones/{seccion}/tareas/crear', NuevaTarea::class)->middleware('permission:tareas.gestionar')->name('secciones.tareas.crear');
    Route::livewire('/secciones/{seccion}/tareas/{tarea}', Tarea::class)->middleware('permission:tareas.entregar|tareas.gestionar')->name('secciones.tareas.show');
    Route::livewire('/secciones/{seccion}/tareas/{tarea}/entregas', Entregas::class)->middleware('permission:calificaciones.gestionar')->name('secciones.tareas.entregas');
    Route::livewire('/secciones/{seccion}/cuestionarios/crear', NuevoCuestionario::class)->middleware('permission:cuestionarios.gestionar')->name('secciones.cuestionarios.crear');
    Route::livewire('/secciones/{seccion}/cuestionarios/{cuestionario}', Cuestionario::class)->middleware('permission:cuestionarios.resolver|cuestionarios.gestionar')->name('secciones.cuestionarios.show');
    Route::livewire('/intentos/{intento}', Intento::class)->middleware('permission:cuestionarios.resolver')->name('intentos.show');
    Route::livewire('/pendientes-de-calificar', Pendientes::class)->middleware('permission:calificaciones.gestionar')->name('pendientes-de-calificar');
    Route::livewire('/bancos-de-preguntas', Bancos::class)->middleware('permission:banco-preguntas.gestionar')->name('bancos-de-preguntas');
    Route::livewire('/bancos-de-preguntas/{banco}/preguntas/crear', NuevaPregunta::class)->middleware('permission:banco-preguntas.gestionar')->whereNumber('banco')->name('bancos-de-preguntas.pregunta');
    Route::livewire('/bancos-de-preguntas/{banco}', Banco::class)->middleware('permission:banco-preguntas.gestionar')->whereNumber('banco')->name('bancos-de-preguntas.show');
});
