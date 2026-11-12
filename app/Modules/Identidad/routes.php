<?php

use App\Modules\Identidad\Livewire\CrearUsuario;
use App\Modules\Identidad\Livewire\EntrarComo;
use App\Modules\Identidad\Livewire\ImportarUsuarios;
use App\Modules\Identidad\Livewire\Integraciones;
use App\Modules\Identidad\Livewire\LoteImportacion;
use App\Modules\Identidad\Livewire\Perfil;
use App\Modules\Identidad\Livewire\PerfilPersona;
use App\Modules\Identidad\Livewire\RolesPermisos;
use App\Modules\Identidad\Livewire\RolesUsuario;
use App\Modules\Identidad\Livewire\SolicitudesDispositivo;
use App\Modules\Identidad\Livewire\UsuarioDetalle;
use App\Modules\Identidad\Livewire\Usuarios;
use App\Modules\Identidad\Services\GoogleAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'modules.identidad.login')->name('login');
    Route::livewire('/dev/entrar-como', EntrarComo::class)->name('dev.entrar-como');

    // RF01 (ADR-007): `hd` solo filtra el selector de Google; la validación real está en GoogleAuthService.
    Route::get('/auth/google', function (GoogleAuthService $servicio) {
        if (! $servicio->configurado()) {
            return redirect()->route('login')->withErrors(['google' => __('El inicio de sesión con Google no está configurado; contacte al administrador.')]);
        }

        return Socialite::driver('google')->with(['hd' => $servicio->dominio()])->redirect();
    })->name('auth.google');

    Route::get('/auth/google/callback', function (Request $request, GoogleAuthService $servicio) {
        if ($request->filled('error')) {
            return redirect()->route('login')->withErrors(['google' => __('Se canceló el inicio de sesión con Google.')]);
        }

        try {
            $perfil = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return redirect()->route('login')->withErrors(['google' => __('La solicitud de inicio de sesión expiró; intente de nuevo.')]);
        } catch (Throwable $excepcion) {
            report($excepcion);

            return redirect()->route('login')->withErrors(['google' => __('No se pudo conectar con Google; intente de nuevo.')]);
        }

        $servicio->autenticar($perfil);

        return redirect()->intended(route('inicio'));
    })->name('auth.google.callback');
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

// Vistas del registro (config/vistas.php): mismo nombre y URI.
Route::middleware('auth')->group(function (): void {
    Route::livewire('/usuarios/importar', ImportarUsuarios::class)->middleware('permission:usuarios.importar')->name('usuarios.importar');
    Route::livewire('/usuarios/lotes/{lote}', LoteImportacion::class)->middleware('permission:usuarios.importar')->whereNumber('lote')->name('usuarios.lote');
    Route::livewire('/usuarios/crear', CrearUsuario::class)->middleware('permission:usuarios.gestionar')->name('usuarios.crear');
    Route::livewire('/usuarios', Usuarios::class)->middleware('permission:usuarios.ver')->name('usuarios');
    Route::livewire('/usuarios/{usuario}/roles', RolesUsuario::class)->middleware('permission:usuarios.ver')->whereNumber('usuario')->name('usuarios.roles');
    Route::livewire('/usuarios/{usuario}', UsuarioDetalle::class)->middleware('permission:usuarios.ver')->whereNumber('usuario')->name('usuarios.show');
    // RF36 (ADR-056): perfil de otra persona según el vínculo; el alcance lo aplica PerfilContextualService.
    Route::livewire('/personas/{usuario}', PerfilPersona::class)->middleware('permission:perfiles.ver')->whereNumber('usuario')->name('personas.show');
    Route::livewire('/perfil', Perfil::class)->defaults('pestana', 'datos')->name('perfil');
    Route::livewire('/perfil/dispositivo', Perfil::class)->defaults('pestana', 'dispositivo')->middleware('permission:dispositivo.registrar')->name('perfil.dispositivo');
    Route::livewire('/perfil/notificaciones', Perfil::class)->defaults('pestana', 'notificaciones')->middleware('permission:notificaciones.preferencias')->name('perfil.notificaciones');
    Route::livewire('/perfil/sesiones', Perfil::class)->defaults('pestana', 'sesiones')->middleware('permission:perfil.sesiones.cerrar')->name('perfil.sesiones');
    Route::livewire('/integraciones', Integraciones::class)->middleware('permission:integraciones.gestionar')->name('integraciones');
    Route::livewire('/roles', RolesPermisos::class)->middleware('permission:roles.gestionar')->name('roles');
    Route::livewire('/dispositivos', SolicitudesDispositivo::class)->middleware('permission:dispositivos.gestionar')->name('dispositivos');
});
