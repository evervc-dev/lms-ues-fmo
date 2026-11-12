<?php

use App\Modules\Auditoria\Livewire\Auditoria;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::livewire('/auditoria', Auditoria::class)->middleware('permission:auditoria.ver')->name('auditoria');
});
