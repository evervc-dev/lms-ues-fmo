/**
 * Vista de las áreas de asistencia (RF26, QA admin 21/11): un mapa de solo lectura con todas las áreas dibujadas, para
 * comprobar que cubren el campus. Las activas en rojo UES; las inactivas en gris. «Ver en el mapa» acerca una.
 * Leaflet se carga con import() dinámico, como en el formulario (mapa-area.js).
 *
 * <div wire:ignore x-data="mapaAreas(@js($areas))" x-init="iniciar()"><div x-ref="vista"></div></div>
 */
const ACTIVA = '#ae152d';
const INACTIVA = '#71717a';

export default (areas) => ({
    mapa: null,
    figuras: {},

    async iniciar() {
        const [modulo] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);
        const L = modulo.default ?? modulo;
        // Primero una vista: sin ella Leaflet no puede calcular los límites de un círculo (layerPointToLatLng).
        const primera = areas[0];
        this.mapa = L.map(this.$refs.vista, { scrollWheelZoom: false }).setView(primera ? [primera.lat, primera.lng] : [13.4393, -88.1597], 16);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(this.mapa);

        const grupo = L.featureGroup().addTo(this.mapa);
        areas.forEach((a) => {
            const estilo = { color: a.activa ? ACTIVA : INACTIVA, weight: 2, fillOpacity: a.activa ? 0.15 : 0.05, dashArray: a.activa ? null : '4 4' };
            const figura = a.tipo === 'polygon' && a.vertices.length >= 3
                ? L.polygon(a.vertices, estilo)
                : L.circle([a.lat, a.lng], { ...estilo, radius: a.radio });
            figura.bindTooltip(a.nombre).addTo(grupo);
            this.figuras[a.id] = figura;
        });

        if (areas.length) {
            this.mapa.fitBounds(grupo.getBounds(), { padding: [20, 20] });
        }
    },

    enfocar(id) {
        const figura = this.figuras[id];
        if (!figura || !this.mapa) {
            return;
        }
        this.mapa.fitBounds(figura.getBounds(), { padding: [20, 20] });
        figura.openTooltip();
        this.$refs.vista.scrollIntoView({ behavior: 'smooth', block: 'center' });
    },
});
