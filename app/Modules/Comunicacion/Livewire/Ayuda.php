<?php

namespace App\Modules\Comunicacion\Livewire;

use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Ayuda en la app (RNF05, maqueta 49): manuales de resources/ayuda (php artisan ayuda:sincronizar).
 * Cada usuario ve la guía de bienvenida y los manuales de sus roles.
 */
#[Layout('layouts::app')]
class Ayuda extends Component
{
    use PaginaLivewire;

    private const POR_ROL = ['estudiante' => 'estudiante', 'docente' => 'docente', 'coordinador' => 'coordinador', 'jefe_departamento' => 'jefe-de-departamento',
        'administrador' => 'administrador', 'soporte_tecnico' => 'soporte-tecnico'];

    #[Locked]
    public ?string $manual = null;

    public function mount(?string $manual = null): void
    {
        $this->manual = $manual;
        abort_if($manual !== null && ! in_array($manual, $this->disponibles(), true), 404);
    }

    /**
     * @return array<int, string>
     */
    private function disponibles(): array
    {
        $propios = collect(self::POR_ROL)->filter(fn ($slug, $rol) => auth()->user()->hasRole($rol))->values();

        return $propios->prepend('bienvenida')->filter(fn ($slug) => File::exists(resource_path("ayuda/{$slug}.md")))->values()->all();
    }

    /**
     * Prefijo de cada tipo de aviso, en palabras: sin emojis, que la QA del rol Jefe pidió evitar (31/10/2026)
     * y que los lectores de pantalla leen de forma desigual.
     */
    private const AVISOS = ['warning' => 'Atención:', 'danger' => 'Importante:', 'tip' => 'Consejo:', 'info' => 'Nota:'];

    /**
     * Deja el manual en Markdown que la página sabe dibujar. El vault se escribe para Obsidian, que
     * entiende diagramas de Mermaid y avisos `> [!tipo]`; aquí no se carga Mermaid (son 2 MB de JavaScript
     * para dos diagramas) y esos bloques salían como código crudo en medio del texto
     * (revisión de diseño del 25/10/2026). Se traducen a una lista de pasos y a una cita con título.
     */
    private function enTexto(string $markdown): string
    {
        $markdown = preg_replace_callback('/^```mermaid\R(.*?)^```$/ms', fn (array $m) => $this->diagrama($m[1]), $markdown);

        return preg_replace_callback('/^> \[!(\w+)\]\s*(.*)$/m',
            fn (array $m) => '> **'.(isset(self::AVISOS[$m[1]]) ? __(self::AVISOS[$m[1]]).' ' : '').trim($m[2]).'**', $markdown);
    }

    /**
     * Un diagrama de Mermaid como lista numerada: los pasos de un `flowchart` o los mensajes de un
     * `sequenceDiagram`. Si no es ninguno de los dos, el bloque se omite antes que salir como código.
     */
    private function diagrama(string $cuerpo): string
    {
        $limpiar = fn (string $t) => trim(preg_replace(['/<br\s*\/?>/i', '/[0-9]\x{FE0F}?\x{20E3}/u', '/\s+/'], [' ', '', ' '], $t));

        if (str_contains($cuerpo, 'sequenceDiagram')) {
            preg_match_all('/^\s*(?:actor|participant)\s+(\w+)\s+as\s+(.+)$/m', $cuerpo, $actores, PREG_SET_ORDER);
            $nombres = collect($actores)->mapWithKeys(fn (array $a) => [$a[1] => trim($a[2])])->all();
            preg_match_all('/^\s*(\w+)\s*--?>>\s*(\w+)\s*:\s*(.+)$/m', $cuerpo, $mensajes, PREG_SET_ORDER);
            $pasos = collect($mensajes)->map(fn (array $m) => '**'.($nombres[$m[1]] ?? $m[1]).' → '.($nombres[$m[2]] ?? $m[2]).':** '.$limpiar($m[3]));
        } else {
            preg_match_all('/\[\s*"([^"]+)"\s*\]/', $cuerpo, $etiquetas);
            $pasos = collect($etiquetas[1])->map($limpiar)->unique()->values();
        }

        return $pasos->isEmpty() ? '' : $pasos->map(fn (string $p, int $i) => ($i + 1).'. '.$p)->implode("\n")."\n";
    }

    public function render(): View
    {
        if ($this->manual === null) {
            $manuales = collect($this->disponibles())->map(fn ($slug) => ['slug' => $slug, 'titulo' => Str::of(File::get(resource_path("ayuda/{$slug}.md")))->match('/^# (.+)$/m')->value() ?: $slug]);

            return $this->pagina('modules.comunicacion.ayuda', ['manuales' => $manuales, 'html' => null, 'indice' => []]);
        }

        $html = Str::markdown($this->enTexto(File::get(resource_path("ayuda/{$this->manual}.md"))), ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $indice = [];
        $html = preg_replace_callback('/<h2>(.*?)<\/h2>/', function ($m) use (&$indice) {
            $id = Str::slug(strip_tags($m[1]));
            $indice[] = ['id' => $id, 'texto' => strip_tags($m[1])];

            return "<h2 id=\"{$id}\">{$m[1]}</h2>";
        }, $html);

        $tituloManual = Str::of(strip_tags($html))->before("\n")->value();

        return $this->pagina('modules.comunicacion.ayuda', ['manuales' => collect(), 'html' => $html, 'indice' => $indice],
            [['etiqueta' => __('Ayuda'), 'url' => route('ayuda')]], titulo: $tituloManual, miga: $tituloManual);
    }
}
