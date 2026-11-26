<?php

use App\Modules\Academica\Services\CargaAcademicaService;
use App\Modules\Identidad\Services\UsuarioService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Los buscadores ignoran tildes, diéresis y la eñe en los dos sentidos (Bitácora 21/11/2026).
 */
it('encuentra personas por nombre sin importar tildes ni eñe, en los dos sentidos', function () {
    $e = escenario();
    DbContext::system(function () use ($e) {
        // Los demás nombres del escenario son aleatorios (Faker) y a veces traían «José» o «Peña»: se fijan neutros.
        // También el correo, que el buscador compara.
        foreach (DB::table('users')->whereNotIn('id', [$e['docente']->id, $e['estudiante']->id])->pluck('id') as $id) {
            DB::table('users')->where('id', $id)->update(['first_name' => 'Persona', 'last_name' => 'De Prueba', 'institutional_email' => "persona{$id}@ues.edu.sv"]);
        }
        foreach ([$e['docente']->id, $e['estudiante']->id] as $id) {
            DB::table('users')->where('id', $id)->update(['institutional_email' => "cuenta{$id}@ues.edu.sv"]);
        }
        DB::table('users')->where('id', $e['docente']->id)->update(['first_name' => 'José', 'last_name' => 'Núñez Peña']);
        DB::table('users')->where('id', $e['estudiante']->id)->update(['first_name' => 'Ramon', 'last_name' => 'Munoz']);
    });
    $buscar = fn (string $texto) => app(UsuarioService::class)->listar($e['administrador'], ['buscar' => $texto])->pluck('id')->all();

    expect($buscar('nunez'))->toBe([$e['docente']->id])
        ->and($buscar('NÚÑEZ pena'))->toBe([$e['docente']->id])
        ->and($buscar('jose'))->toBe([$e['docente']->id])
        ->and($buscar('Ramón Muñoz'))->toBe([$e['estudiante']->id]);
});

it('encuentra asignaturas por nombre sin tildes y no deja que % amplíe la búsqueda', function () {
    $e = escenario();
    $carga = app(CargaAcademicaService::class);

    expect($carga->secciones($e['administrador'], $e['periodo'], ['buscar' => 'programacion'])->pluck('code')->all())->toBe(['PRN115'])
        ->and($carga->secciones($e['administrador'], $e['periodo'], ['buscar' => 'MATEMÁTICA'])->pluck('code')->all())->toBe(['MAT115'])
        ->and($carga->secciones($e['administrador'], $e['periodo'], ['buscar' => '%'])->all())->toBe([]);
});
