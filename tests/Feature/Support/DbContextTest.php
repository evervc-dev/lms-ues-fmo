<?php

use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * DbContext anidado (ADR-027): al salir del de adentro se restaura el contexto del de afuera. Antes se vaciaba, y la
 * siguiente escritura del de afuera en una tabla auditada fallaba con AC900 («Escritura en users sin actor»): pasó
 * en staging al sembrar la superadmin dentro de DatabaseSeeder (21/11/2026). En SQLite no hay triggers ni contexto.
 */
$soloPostgres = fn () => DB::connection()->getDriverName() !== 'pgsql';

it('restaura el contexto del DbContext de afuera al salir del de adentro', function () {
    $valor = fn (string $clave) => DB::selectOne('select current_setting(?, true) as v', [$clave])->v;

    DbContext::system(function () use ($valor) {
        DbContext::system(fn () => DB::table('users')->insert(['institutional_email' => 'adentro@ues.edu.sv', 'first_name' => 'Cuenta', 'last_name' => 'Adentro',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now()]), 'interno');

        expect($valor('app.system_job'))->toBe('on')->and($valor('app.event_type'))->toBe('externo');

        // La escritura del de afuera, después del de adentro, tiene actor (antes: AC900).
        DB::table('users')->insert(['institutional_email' => 'afuera@ues.edu.sv', 'first_name' => 'Cuenta', 'last_name' => 'Afuera',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }, 'externo');

    expect(DB::table('users')->whereIn('institutional_email', ['adentro@ues.edu.sv', 'afuera@ues.edu.sv'])->count())->toBe(2);
})->skip($soloPostgres, 'El contexto y los triggers de auditoría solo existen en PostgreSQL.');

it('un DbContext::run dentro de uno de sistema no le quita el modo sistema al de afuera', function () {
    DbContext::system(function () {
        DbContext::run(fn () => null, 'interno');

        DB::table('users')->insert(['institutional_email' => 'tras-run@ues.edu.sv', 'first_name' => 'Cuenta', 'last_name' => 'Tras Run',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }, 'externo');

    expect(DB::table('users')->where('institutional_email', 'tras-run@ues.edu.sv')->exists())->toBeTrue();
})->skip($soloPostgres, 'El contexto y los triggers de auditoría solo existen en PostgreSQL.');
