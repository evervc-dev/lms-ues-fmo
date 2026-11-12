/**
 * Mapa del formulario de áreas de asistencia (RF26, ADR-055): Leaflet con mapas de OpenStreetMap.
 *
 * - Círculo: un clic pone el centro; el radio sale del campo «Radio».
 * - Polígono: cada clic agrega un vértice; «Deshacer» quita el último y «Limpiar» los borra todos.
 *
 * El mapa solo ayuda a dibujar: lo que vale es lo que llega al componente (latitud, longitud, radio y vértices),
 * que el servidor valida igual que si se hubiera escrito a mano. Leaflet se carga al abrir el formulario, con
 * import() dinámico, para no pesar en las demás pantallas.
 *
 * <div wire:ignore x-data="mapaArea(@js($centro))" x-on:abrir-modal.window="abrir($event.detail)"><div x-ref="mapa"></div></div>
 */

/** Rojo UES (marca-600) para la figura y los vértices. */
const COLOR = '#ae152d';

export default (centroPorDefecto) => ({
    mapa: null,
    capa: null,
    L: null,

    async preparar() {
        if (this.mapa) {
            return;
        }
        const [modulo] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);
        this.L = modulo.default ?? modulo;
        this.mapa = this.L.map(this.$refs.mapa).setView(centroPorDefecto, 17);
        this.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(this.mapa);
        this.capa = this.L.layerGroup().addTo(this.mapa);
        this.mapa.on('click', (e) => this.clic(e.latlng));

        // El formulario cambia el radio o las coordenadas a mano, o con «Usar mi ubicación»: el dibujo lo sigue.
        ['tipo', 'latitud', 'longitud', 'radio'].forEach((campo) => this.$wire.$watch(campo, () => this.dibujar()));
    },

    /** Al abrir el modal del área: el mapa estaba oculto y no conoce su tamaño. */
    async abrir(detalle) {
        if ((Array.isArray(detalle) ? detalle[0] : detalle) !== 'area') {
            return;
        }
        await this.preparar();
        // Después de la transición del modal, cuando el contenedor ya mide lo que mide.
        setTimeout(() => {
            this.mapa.invalidateSize();
            this.dibujar(true);
        }, 200);
    },

    clic({ lat, lng }) {
        const fijo = (n) => Number(n.toFixed(7));
        if (this.$wire.tipo === 'polygon') {
            this.$wire.vertices = [...(this.$wire.vertices ?? []), [fijo(lat), fijo(lng)]];
        } else {
            this.$wire.latitud = String(fijo(lat));
            this.$wire.longitud = String(fijo(lng));
        }
        this.dibujar();
    },

    deshacer() {
        this.$wire.vertices = (this.$wire.vertices ?? []).slice(0, -1);
        this.dibujar();
    },

    limpiar() {
        this.$wire.vertices = [];
        this.dibujar();
    },

    /** Vuelve a pintar la figura; con `encuadrar`, además ajusta la vista a ella. */
    dibujar(encuadrar = false) {
        if (!this.mapa) {
            return;
        }
        this.capa.clearLayers();
        let figura = null;

        if (this.$wire.tipo === 'polygon') {
            const vertices = this.$wire.vertices ?? [];
            vertices.forEach((v) => this.L.circleMarker(v, { radius: 5, color: COLOR, fillOpacity: 1 }).addTo(this.capa));
            if (vertices.length >= 2) {
                figura = this.L.polygon(vertices, { color: COLOR, weight: 2, fillOpacity: 0.15 }).addTo(this.capa);
            }
        } else {
            const lat = parseFloat(this.$wire.latitud);
            const lng = parseFloat(this.$wire.longitud);
            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                this.L.circleMarker([lat, lng], { radius: 5, color: COLOR, fillOpacity: 1 }).addTo(this.capa);
                figura = this.L.circle([lat, lng], { radius: Number(this.$wire.radio) || 0, color: COLOR, weight: 2, fillOpacity: 0.15 }).addTo(this.capa);
            }
        }

        if (encuadrar) {
            figura ? this.mapa.fitBounds(figura.getBounds(), { maxZoom: 18, padding: [20, 20] }) : this.mapa.setView(centroPorDefecto, 17);
        }
    },
});
