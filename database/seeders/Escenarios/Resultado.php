<?php

namespace Database\Seeders\Escenarios;

/**
 * Lo que un escenario deja listo: los enlaces que debe abrir cada rol, los datos sembrados y el
 * resumen que se le muestra a quien lo preparó.
 *
 * Los enlaces se declaran con el **nombre de la vista** del registro (config/vistas.php) y sus
 * parámetros; `EscenarioService` los convierte en URL y les pone el título y el permiso. Así un
 * escenario nunca escribe una URL a mano y, si una ruta cambia de URI, los enlaces siguen bien.
 */
final class Resultado
{
    /** @var array<int, array{rol: string, vista: string, params: array<string, mixed>, nota: string|null}> */
    public array $enlaces = [];

    /** @var array<string, mixed> Lo sembrado (section_id, assessment_id…), para los tests y la página del escenario. */
    public array $datos = [];

    /** @var array<int, string> Líneas de «esto quedó así» para el resumen. */
    public array $resumen = [];

    /**
     * @param  array<string, mixed>  $params
     */
    public function enlace(string $rol, string $vista, array $params = [], ?string $nota = null): self
    {
        $this->enlaces[] = ['rol' => $rol, 'vista' => $vista, 'params' => $params, 'nota' => $nota];

        return $this;
    }

    public function dato(string $clave, mixed $valor): self
    {
        $this->datos[$clave] = $valor;

        return $this;
    }

    public function nota(string $linea): self
    {
        $this->resumen[] = $linea;

        return $this;
    }
}
