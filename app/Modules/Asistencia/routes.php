<?php

use App\Modules\Asistencia\Livewire\AreasAsistencia;
use App\Modules\Asistencia\Livewire\AsistenciaSeccion;
use App\Modules\Asistencia\Livewire\ClasesCanceladas;
use App\Modules\Asistencia\Livewire\DiagnosticoAsistencia;
use App\Modules\Asistencia\Livewire\EscanearQr;
use App\Modules\Asistencia\Livewire\GoogleCalendar;
use App\Modules\Asistencia\Livewire\MiAsistencia;
use App\Modules\Asistencia\Livewire\ProyectarQr;
use App\Modules\Asistencia\Livewire\SesionAsistencia;
use App\Modules\Asistencia\Services\CalendarConexionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Vistas del registro (config/vistas.php): mismo nombre y URI.
Route::middleware('auth')->group(function (): void {
    Route::livewire('/asistencia/diagnostico', DiagnosticoAsistencia::class)->middleware('permission:asistencia.diagnostico.ver')->name('asistencia.diagnostico');
    Route::livewire('/asistencia/escanear', EscanearQr::class)->middleware('permission:asistencia.marcar')->name('asistencia.escanear');
    Route::livewire('/mi-asistencia', MiAsistencia::class)->middleware('permission:asistencia.marcar')->name('mi-asistencia');
    Route::livewire('/secciones/{seccion}/asistencia', AsistenciaSeccion::class)->middleware('permission:asistencia.ver')->name('secciones.asistencia');
    Route::livewire('/secciones/{seccion}/asistencia/{sesion}', SesionAsistencia::class)->middleware('permission:asistencia.sesion.abrir')->name('secciones.asistencia.sesion');
    Route::livewire('/secciones/{seccion}/asistencia/{sesion}/proyectar', ProyectarQr::class)->middleware('permission:asistencia.sesion.abrir')->name('secciones.asistencia.proyectar');
    Route::livewire('/clases-canceladas', ClasesCanceladas::class)->middleware('permission:clases.cancelar')->name('clases-canceladas');
    Route::livewire('/areas-asistencia', AreasAsistencia::class)->middleware('permission:areas-asistencia.gestionar')->name('areas-asistencia');

    // RF21 y RF06 (ADR-018): conexión del docente o del Administrador con Google Calendar, solo con su cuenta @ues.edu.sv. El `state` va en la
    // sesión contra CSRF; la validación del dominio y del correo está en CalendarConexionService.
    Route::livewire('/perfil/google-calendar', GoogleCalendar::class)->middleware('permission:'.CalendarConexionService::PERMISOS)->name('perfil.google-calendar');
    Route::middleware('permission:'.CalendarConexionService::PERMISOS)->group(function (): void {
        Route::get('/perfil/google-calendar/conectar', function (Request $request, CalendarConexionService $conexion) {
            if (! $conexion->habilitada()) {
                return redirect()->route('perfil.google-calendar');
            }
            $state = Str::random(40);
            $request->session()->put('google_calendar_state', $state);

            return redirect()->away($conexion->urlAutorizacion($request->user(), $state));
        })->name('perfil.google-calendar.conectar');

        Route::get('/perfil/google-calendar/callback', function (Request $request, CalendarConexionService $conexion) {
            $esperado = (string) $request->session()->pull('google_calendar_state', '');
            $volver = redirect()->route('perfil.google-calendar');
            if ($request->filled('error')) {
                return $volver->withErrors(['google' => __('Se canceló la conexión con Google Calendar.')]);
            }
            if (! $conexion->habilitada() || $esperado === '' || ! hash_equals($esperado, (string) $request->query('state')) || ! $request->filled('code')) {
                return $volver->withErrors(['google' => __('La solicitud de conexión expiró; intente de nuevo.')]);
            }

            try {
                $conexion->conectar($request->user(), (string) $request->query('code'));
            } catch (ValidationException $excepcion) {
                return $volver->withErrors($excepcion->errors());
            }

            return $volver->with('aviso', ['tipo' => 'exito', 'mensaje' => __('Google Calendar quedó conectado. Sus clases virtuales nuevas crearán el evento con enlace de Meet.')]);
        })->name('perfil.google-calendar.callback');
    });
});
