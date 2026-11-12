<?php

use Illuminate\Support\Facades\Route;

// Cada módulo carga sus rutas desde su ServiceProvider (app/Modules/<Modulo>/routes.php).
Route::get('/', fn () => redirect()->route(auth()->check() ? 'inicio' : 'login'));
