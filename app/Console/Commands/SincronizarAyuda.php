<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Copia los manuales del vault (docs/11 Manuales con `ayuda_ruta`) a resources/ayuda para /ayuda
 * (docs/11 Manuales/Ayuda en la app.md): quita las propiedades, las líneas de requisitos y los avisos
 * de captura pendiente, y convierte los enlaces de Obsidian en texto.
 */
class SincronizarAyuda extends Command
{
    protected $signature = 'ayuda:sincronizar';

    protected $description = 'Copia los manuales del vault a resources/ayuda';

    public function handle(): int
    {
        $origen = base_path('docs/11 Manuales');
        if (! is_dir($origen)) {
            $this->error('No se encontró docs/11 Manuales.');

            return self::FAILURE;
        }
        File::ensureDirectoryExists(resource_path('ayuda'));

        $copiados = 0;
        foreach (File::files($origen) as $archivo) {
            $texto = File::get($archivo->getPathname());
            if (! preg_match('/^ayuda_ruta:\s*\/ayuda\/([\w-]+)/m', $texto, $ruta)) {
                continue;
            }
            File::put(resource_path("ayuda/{$ruta[1]}.md"), $this->convertir($texto));
            $copiados++;
        }
        $this->info("Manuales copiados: {$copiados}.");

        return self::SUCCESS;
    }

    private function convertir(string $texto): string
    {
        $texto = preg_replace('/\A---\R.*?\R---\R/s', '', $texto);
        $texto = preg_replace('/^\*Requisitos:.*\*\R?/m', '', $texto);
        $texto = preg_replace('/^> \[!example\][^\n]*\R(?:>[^\n]*\R?)*/m', '', $texto);
        $texto = preg_replace('/\[\[[^\]|]+\|([^\]]+)\]\]/', '$1', $texto);
        $texto = preg_replace('/\[\[([^\]]+)\]\]/', '$1', $texto);

        return trim(preg_replace("/\n{3,}/", "\n\n", $texto))."\n";
    }
}
