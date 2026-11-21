<?php

namespace App\Modules\Identidad\Jobs;

use App\Models\User;
use App\Modules\Identidad\Services\ImportacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Procesa un lote de carga masiva de usuarios en la cola `importaciones` (RF04, Flujo Carga masiva de usuarios).
 * Idempotente: un lote ya terminado no se vuelve a procesar, y una fila ya creada aparece como correo existente.
 */
class ProcesarImportacionUsuarios implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $loteId, public int $actorId, public string $ruta, public string $extension)
    {
        $this->onQueue('importaciones');
    }

    public function handle(ImportacionService $importacion): void
    {
        $importacion->procesar($this->loteId, User::query()->findOrFail($this->actorId), $this->ruta, $this->extension);
    }

    public function failed(?Throwable $error): void
    {
        DB::table('import_batches')->where('id', $this->loteId)->where('status', '!=', 'finished')->update(['status' => 'failed', 'updated_at' => now()]);
    }
}
