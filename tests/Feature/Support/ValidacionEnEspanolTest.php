<?php

use App\Modules\Evaluaciones\Livewire\NuevaTarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * La interfaz está en español (APP_LOCALE y APP_FALLBACK_LOCALE son «es»). Sin lang/es/validation.php los
 * mensajes propios de Laravel salían con su clave sin traducir, como «validation.required».
 */
it('da los mensajes del validador en español', function () {
    $errores = Validator::make(
        ['correo' => 'no-es-un-correo', 'ciclo' => 'x'],
        ['nombres' => 'required', 'correo' => 'email', 'ciclo' => 'integer'],
        [],
        ['nombres' => 'nombres', 'correo' => 'correo', 'ciclo' => 'ciclo'],
    )->errors();

    expect($errores->first('nombres'))->toBe('El campo nombres es obligatorio.')
        ->and($errores->first('correo'))->toBe('El campo correo debe ser un correo electrónico válido.')
        ->and($errores->first('ciclo'))->toBe('El campo ciclo debe ser un número entero.');
});

it('no deja ninguna regla del validador sin traducir', function () {
    $mensajes = trans('validation');

    expect($mensajes)->toBeArray();

    $sinTraducir = collect($mensajes)
        ->except(['custom', 'attributes'])
        ->flatMap(fn ($valor, $clave) => is_array($valor)
            ? collect($valor)->map(fn ($v, $k) => ["validation.{$clave}.{$k}" => $v])->values()->all()
            : [["validation.{$clave}" => $valor]])
        ->collapse()
        ->filter(fn ($mensaje, $clave) => $mensaje === $clave || str_starts_with((string) $mensaje, 'validation.'))
        ->keys();

    expect($sinTraducir)->toBeEmpty('Reglas sin traducir: '.$sinTraducir->implode(', '));
});

it('lleva el mensaje traducido hasta la pantalla', function () {
    $e = escenario();
    actingAs($e['docente']);

    Livewire::test(NuevaTarea::class, ['seccion' => $e['ulid']])
        ->call('guardar')
        ->assertSee('El campo título es obligatorio.');
});
