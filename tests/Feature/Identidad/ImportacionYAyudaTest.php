<?php

use App\Models\User;
use App\Modules\Comunicacion\Livewire\Ayuda;
use App\Modules\Identidad\Livewire\ImportarUsuarios;
use App\Modules\Identidad\Services\ImportacionService;
use App\Modules\Reportes\Livewire\Reportes;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\HojaXlsx;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function csvDeImportacion(array $filas): UploadedFile
{
    $contenido = "carnet,nombres,apellidos,correo,rol,carrera,plan,ciclo\n".implode("\n", array_map(fn ($f) => implode(',', $f), $filas));

    return UploadedFile::fake()->createWithContent('nuevo_ingreso.csv', $contenido);
}

it('CP-RF04-01 rechaza al Coordinador y deja al Administrador y a Soporte importar de cualquier carrera', function () {
    $e = escenario();
    DbContext::system(function () use ($e) {
        $carrera = DB::table('careers')->insertGetId(['department_id' => $e['dcnm'], 'code' => 'LMA', 'name' => 'Licenciatura en Matemática', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('study_plans')->insert(['career_id' => $carrera, 'name' => 'Plan 20', 'created_at' => now(), 'updated_at' => now()]);
    });
    $filas = [
        ['AB26001', 'Ana', 'Ruiz', 'ab26001@ues.edu.sv', 'estudiante', 'ISI', 'Plan 25', '1'],
        ['RD26002', 'Rosa', 'Díaz', 'rd26002@ues.edu.sv', 'estudiante', 'LMA', 'Plan 20', '1'],
    ];

    actingAs($e['coordinador']);
    expect(fn () => app(ImportacionService::class)->lotes($e['coordinador']))->toThrow(AuthorizationException::class);
    actingAs($e['coordinador'])->get(route('usuarios.importar'))->assertForbidden();

    actingAs($e['administrador']);
    $delAdministrador = Livewire::test(ImportarUsuarios::class)->set('archivo', csvDeImportacion($filas))->get('previa');
    actingAs($e['soporte']);
    $deSoporte = Livewire::test(ImportarUsuarios::class)->set('archivo', csvDeImportacion($filas))->get('previa');

    expect($delAdministrador['validas'])->toBe(2)
        ->and($delAdministrador['errores'])->toBe([])
        ->and($deSoporte['validas'])->toBe(2)
        ->and($deSoporte['errores'])->toBe([]);
});

it('CP-RF04-02 importa las filas válidas y resume creados, duplicados y rechazados por fila', function () {
    $e = escenario();
    actingAs($e['soporte']);
    $archivo = csvDeImportacion([
        ['AB26001', 'Ana', 'Ruiz', 'ab26001@ues.edu.sv', 'estudiante', 'ISI', 'Plan 25', '1'],
        ['', 'Mario', 'Pérez', 'mario@gmail.com', 'estudiante', 'ISI', 'Plan 25', '1'],
        ['', 'Luis', 'Mena', $e['estudiante']->institutional_email, 'estudiante', 'ISI', 'Plan 25', '1'],
        ['', 'Rosa', 'Díaz', 'rd26002@ues.edu.sv', 'docente', 'ISI', 'Plan 25', '1'],
    ]);

    $c = Livewire::test(ImportarUsuarios::class)->set('archivo', $archivo);
    expect($c->get('previa')['validas'])->toBe(1)->and($c->get('previa')['duplicados'])->toBe(1)->and(count($c->get('previa')['errores']))->toBe(3);

    $c->call('importar')->assertRedirect();
    $nueva = User::query()->where('institutional_email', 'ab26001@ues.edu.sv')->first();
    expect($nueva->hasRole('estudiante'))->toBeTrue()
        ->and(DB::table('student_careers')->where('student_id', $nueva->id)->value('career_id'))->toBe($e['isi'])
        ->and(DB::table('import_batch_errors')->count())->toBe(3);
});

it('CP-RF04-02 toma el carné del correo al importar y rechaza el carnet que no coincide o ya existe', function () {
    $e = escenario();
    actingAs($e['soporte']);
    $archivo = csvDeImportacion([
        ['', 'Laura', 'López', 'll22017@ues.edu.sv', 'estudiante', 'ISI', 'Plan 25', '1'],
        ['LL22018', 'Lucía', 'Lara', 'll22018@ues.edu.sv', 'estudiante', 'ISI', 'Plan 25', '1'],
        ['XX99999', 'Pedro', 'Paz', 'pp22019@ues.edu.sv', 'estudiante', 'ISI', 'Plan 25', '1'],
        ['', 'Gabriel', 'Soto', 'GS21004@ues.edu.sv', 'estudiante', 'ISI', 'Plan 25', '1'],
    ]);

    $c = Livewire::test(ImportarUsuarios::class)->set('archivo', $archivo);
    expect($c->get('previa')['validas'])->toBe(2)
        ->and(collect($c->get('previa')['errores'])->pluck('campo')->all())->toBe(['carnet', 'carnet'])
        ->and($c->get('previa')['duplicados'])->toBe(1);

    $c->call('importar')->assertRedirect();
    expect(DB::table('student_profiles')->join('users', 'users.id', '=', 'student_profiles.user_id')
        ->whereIn('users.institutional_email', ['ll22017@ues.edu.sv', 'll22018@ues.edu.sv'])->orderBy('student_code')->pluck('student_code')->all())
        ->toBe(['ll22017', 'll22018']);
});

it('shows each user only the help manuals of their roles', function () {
    $this->artisan('ayuda:sincronizar')->assertSuccessful();
    $e = escenario();
    actingAs($e['estudiante']);

    Livewire::test(Ayuda::class)->assertSee('Manual del Estudiante')->assertDontSee('Manual del Docente');
    actingAs($e['estudiante'])->get(route('ayuda', ['manual' => 'estudiante']))->assertOk()->assertSee('id="3-inscribirse-en-una-materia-con-clave"', false);
    actingAs($e['estudiante'])->get(route('ayuda', ['manual' => 'administrador']))->assertNotFound();
});

it('CP-RF04-21 importa el libro de Excel tal como se guarda, sin pasarlo antes a CSV', function () {
    $e = escenario();
    actingAs($e['soporte']);

    $hoja = [ImportacionService::COLUMNAS];
    $hoja[] = ['', 'Luisa', 'Linares', 'll22017@ues.edu.sv', 'estudiante', 'ISI', 'Plan 25', '1'];
    $hoja[] = ['', 'Marcos', 'Mena', 'mm22018@ues.edu.sv', 'estudiante', 'ISI', 'Plan 25', '2'];
    $ruta = HojaXlsx::crear([$hoja]);
    $archivo = UploadedFile::fake()->createWithContent('nuevo_ingreso.xlsx', (string) file_get_contents($ruta));
    unlink($ruta);

    $c = Livewire::test(ImportarUsuarios::class)->set('archivo', $archivo)->assertHasNoErrors();
    expect($c->get('previa')['validas'])->toBe(2)->and($c->get('previa')['errores'])->toBeEmpty();

    $c->call('importar')->assertRedirect();
    expect(DB::table('users')->whereIn('institutional_email', ['ll22017@ues.edu.sv', 'mm22018@ues.edu.sv'])->count())->toBe(2)
        ->and(DB::table('import_batches')->latest('id')->value('file_name'))->toBe('nuevo_ingreso.xlsx');
});

it('CP-RF04-20 ofrece la plantilla con los encabezados y un ejemplo que pasa la validación', function () {
    $e = escenario();
    actingAs($e['soporte']);

    Livewire::test(ImportarUsuarios::class)->call('plantilla')->assertFileDownloaded('plantilla-importacion-estudiantes.csv');

    $plantilla = app(ImportacionService::class)->plantilla($e['soporte']);
    expect($plantilla['columnas'])->toBe(ImportacionService::COLUMNAS)
        ->and($plantilla['filas'])->toHaveCount(1)
        ->and($plantilla['filas'][0])->toHaveCount(count(ImportacionService::COLUMNAS));

    $filas = $plantilla['filas'];

    // El ejemplo se importa tal cual: la carrera y el plan salen del alcance de quien la descarga.
    $ruta = tempnam(sys_get_temp_dir(), 'plantilla').'.csv';
    $manejador = fopen($ruta, 'w');
    fputcsv($manejador, $plantilla['columnas']);
    fputcsv($manejador, $filas[0]);
    fclose($manejador);

    $previa = app(ImportacionService::class)->vistaPrevia($e['soporte'], $ruta);
    unlink($ruta);

    expect($previa['errores'])->toBeEmpty()->and($previa['validas'])->toHaveCount(1);
});

it('turns the vault mermaid diagrams and callouts into readable steps', function () {
    $this->artisan('ayuda:sincronizar')->assertSuccessful();
    $e = escenario();

    $html = actingAs($e['estudiante'])->get(route('ayuda', ['manual' => 'bienvenida']))->assertOk()->getContent();

    expect($html)->not->toContain('flowchart LR')
        ->and($html)->not->toContain('sequenceDiagram')
        ->and($html)->not->toContain('[!warning]')
        ->and($html)->toContain('Registre su teléfono')
        ->and($html)->toContain('Docente → Plataforma:');
});

it('shows the integrations in use from the environment without revealing secrets or offering to save', function () {
    $e = escenario();
    config(['services.google.client_id' => 'abc.apps.googleusercontent.com', 'services.google.client_secret' => 'super-secreto', 'mail.mailers.smtp.host' => '']);

    actingAs($e['administrador'])->get(route('integraciones'))->assertOk()
        ->assertSeeText(['abc.apps.googleusercontent.com', 'GOOGLE_CLIENT_SECRET', 'Definido (no se muestra)', 'MAIL_HOST', 'Pendiente'])
        ->assertDontSee('super-secreto')
        ->assertDontSeeText('Guardar');
    actingAs($e['docente'])->get(route('integraciones'))->assertForbidden();
});

it('CP-RF25-01 limits a teacher report to their own sections and exports it as CSV', function () {
    $e = escenario();
    actingAs($e['docente']);

    Livewire::test(Reportes::class)->assertSee('PRN115')->assertDontSee('MAT115')->call('exportar', 'csv')->assertFileDownloaded('reporte-asistencia-'.now()->format('Y-m-d').'.csv');
});

it('CP-RF33-01 shows the department head the teacher performance of their department', function () {
    $e = escenario();

    actingAs($e['jefe'])->get(route('desempeno-docente'))->assertOk()->assertSeeText([$e['docente']->last_name, 'Retraso', 'A tiempo']);
});

it('CP-RF04-23 la carga masiva rechaza la fila con un nombre sin espacios y normaliza las demás', function () {
    $e = escenario();
    actingAs($e['soporte']);
    $archivo = csvDeImportacion([
        ['AB26001', 'ana maría', 'RUIZ de león', 'ab26001@ues.edu.sv', 'estudiante', 'ISI', 'Plan 25', '1'],
        ['RD26002', 'Rosa', str_repeat('m', 80), 'rd26002@ues.edu.sv', 'estudiante', 'ISI', 'Plan 25', '1'],
    ]);

    $c = Livewire::test(ImportarUsuarios::class)->set('archivo', $archivo);
    expect($c->get('previa')['validas'])->toBe(1)
        ->and(collect($c->get('previa')['errores'])->pluck('campo')->all())->toBe(['nombres']);

    $c->call('importar')->assertRedirect();
    expect(User::query()->where('institutional_email', 'ab26001@ues.edu.sv')->first()->only('first_name', 'last_name'))
        ->toBe(['first_name' => 'Ana María', 'last_name' => 'Ruiz de León']);
});
