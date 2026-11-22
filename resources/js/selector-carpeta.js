/**
 * Selector de carpeta (RF11, CP-RF11-06). El docente elige una carpeta de su equipo y el navegador
 * entrega cada archivo con su ruta relativa (`webkitRelativePath`): esa lista de rutas se envía a
 * Livewire en el mismo orden que los archivos, y el Service la normaliza, la valida y la guarda.
 *
 * <div x-data="selectorCarpeta()"><input type="file" multiple webkitdirectory x-on:change="elegir($event)"></div>
 *
 * Los archivos se suben con `$uploadMultiple`. Antes se usaba `$upload`, que envuelve lo que recibe como UN archivo
 * (`files: [arreglo]`): el servidor no recibía archivos válidos y subir una carpeta fallaba siempre (QA 22/11/2026).
 * `$uploadMultiple` no devuelve una promesa: se espera con sus callbacks de fin y de error.
 */
export default (propiedadArchivos = 'archivos', propiedadRutas = 'rutas') => ({
    subiendo: false,
    error: false,

    async elegir(evento) {
        const archivos = Array.from(evento.target.files ?? []);
        if (archivos.length === 0) {
            return;
        }

        this.subiendo = true;
        this.error = false;
        try {
            // Se empieza de cero: así las rutas y los archivos del servidor siempre van en el mismo orden.
            await this.$wire.quitarCarpeta();
            this.$wire.$set(
                propiedadRutas,
                archivos.map((archivo) => archivo.webkitRelativePath || archivo.name),
                false,
            );
            await new Promise((listo, fallo) => {
                // append = false: reemplaza la carpeta anterior en vez de sumarle archivos.
                this.$wire.$uploadMultiple(propiedadArchivos, archivos, listo, fallo, () => {}, fallo, false);
            });
        } catch (e) {
            this.error = true;
        } finally {
            this.subiendo = false;
            evento.target.value = '';
        }
    },
});
