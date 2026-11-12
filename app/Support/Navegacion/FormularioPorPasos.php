<?php

namespace App\Support\Navegacion;

use Illuminate\Validation\ValidationException;

/**
 * Formulario por pasos (docs/13 Layout/Layout base.md): cada paso agrupa una categoría de campos y valida
 * lo suyo antes de dejar avanzar; el último muestra el resumen para revisar y confirmar. La vista lo dibuja
 * con <x-ui.formulario> y <x-ui.formulario.paso>.
 *
 * El componente que lo usa declara sus pasos en pasos(): título, descripción, reglas y atributos. Las reglas
 * son solo de **formato**; las de negocio siguen en el Service, que vuelve a validar al guardar.
 */
trait FormularioPorPasos
{
    public int $paso = 1;

    /**
     * Pasos del formulario, en orden. El último suele ser el resumen y no lleva reglas.
     *
     * @return array<int, array{titulo: string, descripcion?: string, reglas?: array<string, mixed>, atributos?: array<string, string>}>
     */
    abstract public function pasos(): array;

    public function siguiente(): void
    {
        $definicion = $this->pasos()[$this->paso - 1] ?? [];
        if (($definicion['reglas'] ?? []) !== []) {
            $this->validate($definicion['reglas'], [], $definicion['atributos'] ?? []);
        }
        $this->validarPaso($this->paso);

        $this->paso = max(1, min($this->paso + 1, count($this->pasos())));
    }

    /**
     * Comprobación propia de un paso que no cabe en una regla de formato (por ejemplo, «debe quedar al menos un
     * destinatario»). Lanza ValidationException para impedir el avance; por omisión no hace nada.
     */
    protected function validarPaso(int $paso): void {}

    public function anterior(): void
    {
        $this->resetValidation();
        $this->paso = max(1, $this->paso - 1);
    }

    /**
     * Vuelve a un paso ya recorrido desde el indicador. Para avanzar hay que pasar por «Siguiente», que valida.
     */
    public function irAlPaso(int $paso): void
    {
        if ($paso < $this->paso) {
            $this->resetValidation();
            $this->paso = max(1, $paso);
        }
    }

    /**
     * Reglas de todos los pasos juntas, para volver a validar al enviar: el paso final no debe ser la única
     * defensa, porque el navegador puede enviar el formulario sin pasar por «Siguiente».
     *
     * @return array<string, mixed>
     */
    protected function reglasDeTodosLosPasos(): array
    {
        return array_merge(...array_column($this->pasos(), 'reglas') ?: [[]]);
    }

    /**
     * @return array<string, string>
     */
    protected function atributosDeTodosLosPasos(): array
    {
        return array_merge(...array_column($this->pasos(), 'atributos') ?: [[]]);
    }

    /**
     * Valida el formulario entero con las reglas de sus pasos y, si algo falla, lleva al paso del primer error.
     */
    protected function validarTodosLosPasos(): void
    {
        try {
            $this->validate($this->reglasDeTodosLosPasos(), [], $this->atributosDeTodosLosPasos());
        } catch (ValidationException $e) {
            $this->paso = $this->pasoDelCampo((string) array_key_first($e->errors())) ?? $this->paso;

            throw $e;
        }
    }

    /**
     * Guarda con el Service y, si este rechaza un dato (ValidationException), lleva al paso del campo con el error.
     * Si el campo no pertenece a ningún paso, lo avisa con un mensaje: el rechazo nunca queda oculto detrás del
     * resumen (QA sesión 4: «Ya existe un usuario con ese correo» no se veía desde el paso final).
     *
     * @template T
     *
     * @param  callable(): T  $guardar
     * @return T|null
     */
    protected function guardarPorPasos(callable $guardar): mixed
    {
        try {
            return $guardar();
        } catch (ValidationException $e) {
            $paso = $this->pasoDelCampo((string) array_key_first($e->errors()));
            if ($paso === null) {
                $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());

                return null;
            }
            $this->paso = $paso;

            throw $e;
        }
    }

    /**
     * Paso al que pertenece un campo con error, para no dejar al usuario en el resumen sin ver qué falló.
     */
    private function pasoDelCampo(string $campo): ?int
    {
        foreach ($this->pasos() as $indice => $definicion) {
            foreach (array_keys($definicion['reglas'] ?? []) as $regla) {
                if ($regla === $campo || str_starts_with($campo, rtrim((string) strtok($regla, '*'), '.').'.')) {
                    return $indice + 1;
                }
            }
        }

        return null;
    }
}
