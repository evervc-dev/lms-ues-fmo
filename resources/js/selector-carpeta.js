/**
 * Selector de carpeta (RF11, CP-RF11-06). El docente elige una carpeta de su equipo y el navegador
 * entrega cada archivo con su ruta relativa (`webkitRelativePath`): esa lista de rutas se envía a
 * Livewire en el mismo orden que los archivos, y el Service la normaliza, la valida y la guarda.
 *
 * <div x-data="selectorCarpeta()"><input type="file" multiple webkitdirectory x-on:change="elegir($event)"></div>
 */
export default (propiedadArchivos = 'archivos', propiedadRutas = 'rutas') => ({
    subiendo: false,

    async elegir(evento) {
        const archivos = Array.from(evento.target.files ?? []);
        if (archivos.length === 0) {
            return;
        }

        this.subiendo = true;
        try {
            // Se empieza de cero: así las rutas y los archivos del servidor siempre van en el mismo orden.
            await this.$wire.quitarCarpeta();
            this.$wire.$set(
                propiedadRutas,
                archivos.map((archivo) => archivo.webkitRelativePath || archivo.name),
                false,
            );
            await this.$wire.$upload(propiedadArchivos, archivos);
        } finally {
            this.subiendo = false;
            evento.target.value = '';
        }
    },
});
