<?php

use App\Modules\Academica\Livewire\CalendarioEvaluaciones;
use App\Modules\Academica\Livewire\CargaAcademica;
use App\Modules\Academica\Livewire\CarreraDetalle;
use App\Modules\Academica\Livewire\Carreras;
use App\Modules\Academica\Livewire\Catalogo;
use App\Modules\Academica\Livewire\ClavesMatricula;
use App\Modules\Academica\Livewire\Estructura;
use App\Modules\Academica\Livewire\EstudiantesSeccion;
use App\Modules\Academica\Livewire\Inscripcion;
use App\Modules\Academica\Livewire\InscripcionDetalle;
use App\Modules\Academica\Livewire\Matricula;
use App\Modules\Academica\Livewire\MisMaterias;
use App\Modules\Academica\Livewire\Oferta;
use App\Modules\Academica\Livewire\ParticipantesSeccion;
use App\Modules\Academica\Livewire\PeriodoDetalle;
use App\Modules\Academica\Livewire\Periodos;
use App\Modules\Academica\Livewire\PlazosMatricula;
use App\Modules\Academica\Livewire\Secciones;
use App\Modules\Academica\Livewire\SolicitudesCambio;
use Illuminate\Support\Facades\Route;

// Vistas del registro (config/vistas.php): mismo nombre y URI.
Route::middleware('auth')->group(function (): void {
    Route::livewire('/periodos', Periodos::class)->middleware('permission:periodos.gestionar')->name('periodos');
    Route::livewire('/periodos/{periodo}', PeriodoDetalle::class)->middleware('permission:periodos.gestionar')->whereNumber('periodo')->name('periodos.show');
    // RF06 (ADR-068): el Administrador, el Jefe y el Coordinador fijan las fechas de su ámbito. Sin período en la
    // ruta se usa el vigente, porque el Jefe y el Coordinador no entran a la lista de períodos.
    Route::livewire('/calendario-evaluaciones/{periodo?}', CalendarioEvaluaciones::class)->middleware('permission:periodos.calendario.gestionar')->whereNumber('periodo')->name('periodos.calendario');
    Route::livewire('/catalogo', Catalogo::class)->middleware('permission:asignaturas.gestionar')->name('catalogo');
    Route::livewire('/catalogo/estructura', Estructura::class)->middleware('permission:asignaturas.gestionar')->name('catalogo.estructura');
    // Jefe (D) y Coordinador (C): las carreras de su ámbito, con sus docentes, materias, grupos y horarios.
    Route::livewire('/carreras', Carreras::class)->middleware('permission:carreras.ver')->name('carreras');
    Route::livewire('/carreras/{carrera}', CarreraDetalle::class)->middleware('permission:carreras.ver')->whereNumber('carrera')->name('carreras.show');
    // Docente (contenidos.gestionar), Coordinador (contenidos.analitica.ver) y Jefe (cierre.autorizar): los roles con un ámbito de secciones.
    Route::livewire('/secciones', Secciones::class)->middleware('permission:contenidos.gestionar|contenidos.analitica.ver|cierre.autorizar')->name('secciones');
    Route::livewire('/carga-academica', CargaAcademica::class)->middleware('permission:secciones.gestionar|secciones.asignar-docente|secciones.cupo')->name('carga-academica');
    Route::livewire('/matricula', Matricula::class)->middleware('permission:matricula.gestionar')->name('matricula');
    Route::livewire('/matricula/claves', ClavesMatricula::class)->middleware('permission:matricula.claves.gestionar')->name('matricula.claves');
    Route::livewire('/matricula/solicitudes', SolicitudesCambio::class)->middleware('permission:matricula.gestionar')->name('matricula.solicitudes');
    // RF29 (ADR-051): fecha límite por departamento (Administrador G, Jefe D, Coordinador C) y prórrogas (Jefe D, Coordinador C).
    Route::livewire('/matricula/plazos', PlazosMatricula::class)->middleware('permission:matricula.plazo.gestionar|matricula.prorroga.otorgar')->name('matricula.plazos');
    Route::livewire('/materias', MisMaterias::class)->middleware('permission:matricula.automatricular')->name('materias');
    // Estudiante (RF29, ADR-050): materias de su carrera y ciclo, y su detalle con «Inscribirme con clave».
    Route::livewire('/inscripcion', Inscripcion::class)->middleware('permission:matricula.automatricular')->name('inscripcion');
    Route::livewire('/inscripcion/{asignatura}', InscripcionDetalle::class)->middleware('permission:matricula.automatricular')->whereNumber('asignatura')->name('inscripcion.show');
    Route::livewire('/secciones/{seccion}/estudiantes', EstudiantesSeccion::class)->middleware('permission:matricula.gestionar')->name('secciones.estudiantes');
    // RF36 (ADR-056): participantes de la sección; quién entra lo decide SeccionService con el alcance de perfiles.ver.
    Route::livewire('/secciones/{seccion}/participantes', ParticipantesSeccion::class)->middleware('permission:perfiles.ver')->name('secciones.participantes');
    Route::livewire('/oferta', Oferta::class)->middleware('permission:oferta.estadisticas.ver')->name('oferta');
});
