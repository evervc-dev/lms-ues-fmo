<?php

use App\Models\User;
use App\Modules\Asistencia\Services\ReporteMeetService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Flujo crítico 5 en navegador (Estrategia de pruebas, RF21, ADR-003): con el reporte de Meet de una clase
 * finalizada ya leído, el docente abre la vista previa, asigna a mano la fila sin correo, descarta la de un invitado
 * ajeno a la sección y confirma. Las confirmaciones (`wire:confirm`) se aceptan sustituyendo `window.confirm`, porque
 * el navegador de las pruebas las rechaza solo.
 */
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-23 10:00'));
    Storage::fake('local');
});

it('CP-RF21-04 en la vista previa del reporte de Meet asigna y descarta las filas sin asociar y confirma la asistencia', function () {
    $e = escenario();
    DbContext::system(fn () => $e['estudiante']->forceFill(['institutional_email' => 'gs21004@ues.edu.sv', 'first_name' => 'Ana', 'last_name' => 'Gómez'])->save());
    $mario = DbContext::system(function () use ($e) {
        $estudiante = User::factory()->create(['institutional_email' => 'mr21011@ues.edu.sv', 'first_name' => 'Mario', 'last_name' => 'Rivas'])->assignRole('estudiante');

        return DB::table('enrollments')->insertGetId(['student_id' => $estudiante->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
            'status' => 'active', 'enrolled_via' => 'key', 'enrolled_by' => $estudiante->id, 'created_at' => now(), 'updated_at' => now()]);
    });
    $clase = DbContext::system(fn () => DB::table('video_sessions')->insertGetId(['section_id' => $e['seccion'], 'topic' => 'Recursividad', 'starts_at' => '2026-10-23 07:00:00',
        'duration_minutes' => 100, 'status' => 'finished', 'meet_url' => 'https://meet.google.com/abc-defg-hij', 'created_at' => now(), 'updated_at' => now()]));

    $csv = implode("\n", [
        'Nombre,Correo electrónico,Duración,Hora en que se unió',
        'Ana Gómez,gs21004@ues.edu.sv,95 min,2026-10-23 07:02:00',
        'Teléfono de Mario,,80 min,2026-10-23 07:05:00',
        'Invitado externo,invitado@gmail.com,40 min,2026-10-23 07:10:00',
    ]);

    // Pest Browser 4.3 no pasa archivos multipart a la app (LaravelHttpServer: «@TODO files»), así que el reporte se
    // lee con el servicio, como lo haría «Leer reporte»; la lectura del archivo ya la cubre CP-RF21-02.
    $this->actingAs($e['docente']);
    app(ReporteMeetService::class)->subir($e['docente'], $e['seccion'], $clase, UploadedFile::fake()->createWithContent('reporte.csv', $csv));

    $pagina = visit(route('secciones.asistencia', ['seccion' => $e['ulid']]));
    $pagina->script('window.confirm = () => true');

    $pagina->click('Vista previa')
        ->waitForText('Vista previa del reporte')
        ->assertSee('Sin asociar (2)')
        ->assertSee('Teléfono de Mario')
        ->assertSee('invitado@gmail.com');

    $pagina->select('tr:has-text("Teléfono de Mario") select', (string) $mario)
        ->click('tr:has-text("Teléfono de Mario") button:has-text("Asignar")')
        ->waitForText('Sin asociar (1)')
        ->click('Descartar')
        ->waitForText('Sin asociar (0)')
        ->assertSee('Descartadas: Invitado externo')
        ->click('Confirmar asistencia')
        ->waitForText('Asistencia registrada con el reporte de Meet.')
        ->assertDontSee('undefined');

    expect(DB::table('attendance_records')->where('enrollment_id', $e['matricula'])->value('status'))->toBe('present')
        ->and(DB::table('attendance_records')->where('enrollment_id', $mario)->value('status'))->toBe('present');
});
