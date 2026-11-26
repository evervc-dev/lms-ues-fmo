/**
 * Visor de PDF de una lección (RF38, ADR-091). Dibuja cada página con PDF.js dentro de un contenedor con scroll y
 * avisa una sola vez, con `alFinal()`, cuando la última página entra en pantalla. El visor del navegador (iframe) no
 * informa la página en que va el lector; por eso la lección usa este en vez del de RF11.
 *
 * PDF.js se carga solo al abrir una lección con PDF: no engorda el resto de la aplicación.
 */
export default (url, alFinal) => ({
    paginas: 0,
    cargando: true,
    error: false,
    avisado: false,
    observador: null,

    async init() {
        try {
            const pdfjs = await import('pdfjs-dist');
            pdfjs.GlobalWorkerOptions.workerSrc = (await import('pdfjs-dist/build/pdf.worker.min.mjs?url')).default;
            const documento = await pdfjs.getDocument({ url, withCredentials: true }).promise;
            this.paginas = documento.numPages;
            const ancho = this.$refs.paginas.clientWidth || 800;

            for (let numero = 1; numero <= documento.numPages; numero++) {
                const pagina = await documento.getPage(numero);
                const escala = ancho / pagina.getViewport({ scale: 1 }).width;
                const vista = pagina.getViewport({ scale: escala * (window.devicePixelRatio || 1) });
                const lienzo = document.createElement('canvas');
                lienzo.width = vista.width;
                lienzo.height = vista.height;
                lienzo.style.width = '100%';
                lienzo.className = 'mb-3 rounded bg-white shadow-sm';
                lienzo.setAttribute('aria-label', `Página ${numero} de ${documento.numPages}`);
                this.$refs.paginas.appendChild(lienzo);
                await pagina.render({ canvasContext: lienzo.getContext('2d'), viewport: vista }).promise;
            }
            this.cargando = false;
            this.observarUltima();
        } catch (e) {
            this.cargando = false;
            this.error = true;
        }
    },

    observarUltima() {
        const ultima = this.$refs.paginas.lastElementChild;
        if (!ultima) {
            return;
        }
        this.observador = new IntersectionObserver((entradas) => {
            if (!this.avisado && entradas.some((e) => e.isIntersecting)) {
                this.avisado = true;
                this.observador.disconnect();
                alFinal();
            }
        }, { root: this.$refs.paginas, threshold: 0.6 });
        this.observador.observe(ultima);
    },

    destroy() {
        this.observador?.disconnect();
    },
});
