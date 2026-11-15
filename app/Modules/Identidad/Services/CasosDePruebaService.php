<?php

namespace App\Modules\Identidad\Services;

use Illuminate\Support\Str;

/**
 * Lee los casos de prueba de la bóveda (docs/10 Calidad/Casos de prueba/CP-RFxx.md).
 *
 * La checklist de un escenario no se escribe a mano: son los casos que el equipo ya redactó en formato
 * Dado/Cuando/Entonces para cada RF. Así la prueba manual y la documentación no se separan, y cuando
 * alguien agrega un caso al archivo del RF, aparece solo en el siguiente escenario que se prepare.
 */
class CasosDePruebaService
{
    /** @var array<string, array<int, array{id: string, enunciado: string}>> */
    private array $cache = [];

    /**
     * Los casos de un RF, en el orden del archivo.
     *
     * @return array<int, array{id: string, enunciado: string}>
     */
    public function delRf(string $rf): array
    {
        $rf = Str::upper(trim($rf));

        if (isset($this->cache[$rf])) {
            return $this->cache[$rf];
        }

        $archivo = $this->ruta($rf);

        if (! is_file($archivo)) {
            return $this->cache[$rf] = [];
        }

        $casos = [];
        foreach (file($archivo, FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
            if (! str_starts_with(trim($linea), '| CP-')) {
                continue;
            }

            $columnas = array_map('trim', explode('|', trim($linea, "| \t")));

            if (count($columnas) < 2 || ! preg_match('/^CP-RF\d{2}-\d{2}$/', $columnas[0])) {
                continue;
            }

            $casos[] = ['id' => $columnas[0], 'enunciado' => $this->limpiar($columnas[1])];
        }

        return $this->cache[$rf] = $casos;
    }

    /**
     * Los casos de varios RF, concatenados y sin repetir.
     *
     * @param  array<int, string>  $rfs
     * @return array<int, array{id: string, enunciado: string, rf: string}>
     */
    public function deVariosRf(array $rfs): array
    {
        $casos = [];
        $vistos = [];

        foreach ($rfs as $rf) {
            foreach ($this->delRf($rf) as $caso) {
                if (isset($vistos[$caso['id']])) {
                    continue;
                }
                $vistos[$caso['id']] = true;
                $casos[] = $caso + ['rf' => Str::upper(trim($rf))];
            }
        }

        return $casos;
    }

    public function nombreDelRf(string $rf): ?string
    {
        $archivo = $this->ruta($rf);

        if (! is_file($archivo)) {
            return null;
        }

        foreach (file($archivo, FILE_IGNORE_NEW_LINES) ?: [] as $linea) {
            if (str_starts_with($linea, 'nombre:')) {
                return trim(Str::after($linea, 'nombre:'));
            }
        }

        return null;
    }

    private function ruta(string $rf): string
    {
        return base_path('docs/10 Calidad/Casos de prueba/CP-'.Str::upper(trim($rf)).'.md');
    }

    /**
     * Quita el marcado de Obsidian: negritas de Dado/Cuando/Entonces y enlaces [[RF24]].
     */
    private function limpiar(string $texto): string
    {
        $texto = preg_replace('/\[\[([^\]|]+)(\|[^\]]+)?\]\]/u', '$1', $texto) ?? $texto;
        $texto = str_replace(['**', '`'], '', $texto);

        return trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
    }
}
