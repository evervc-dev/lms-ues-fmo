/**
 * Editor de páginas de materiales (RF11, CP-RF11-07). Monta Quill sobre un contenedor marcado con
 * `wire:ignore` y vuelca su HTML en una propiedad del componente Livewire.
 *
 * El HTML que sale de aquí **no es de fiar**: el servidor lo sanea con HTMLPurifier (perfil `contenidos`
 * de config/purifier.php) antes de guardarlo. Esto solo sirve para que el docente escriba cómodo.
 *
 * <div wire:ignore x-data="editorPaginas('texto')"><div x-ref="editor"></div></div>
 */
import 'quill/dist/quill.snow.css';
// Después del tema: ajusta el editor y da estilo al HTML ya publicado (resources/css/paginas.css).
import '../css/paginas.css';

/** Barra: encabezados, negrita, cursiva, subrayado, listas, alineación y enlace. */
const BARRA = [
    [{ header: [2, 3, false] }],
    ['bold', 'italic', 'underline'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    [{ align: '' }, { align: 'center' }, { align: 'right' }, { align: 'justify' }],
    ['link'],
];

/** Solo estos formatos: lo demás (imágenes, colores, tablas) no entra ni al pegar. */
const FORMATOS = ['header', 'bold', 'italic', 'underline', 'strike', 'list', 'align', 'link', 'blockquote'];

/** Nombre en español de cada botón, que Quill dibuja solo con su icono. */
const NOMBRES = {
    'ql-bold': 'Negrita',
    'ql-italic': 'Cursiva',
    'ql-underline': 'Subrayado',
    'ql-link': 'Enlace',
    'ql-list[value="ordered"]': 'Lista numerada',
    'ql-list[value="bullet"]': 'Lista con puntos',
    'ql-align[value=""]': 'Alinear a la izquierda',
    'ql-align[value="center"]': 'Centrar',
    'ql-align[value="right"]': 'Alinear a la derecha',
    'ql-align[value="justify"]': 'Justificar',
};

export default (propiedad = 'texto', textos = {}) => ({
    editor: null,

    async init() {
        const { default: Quill } = await import('quill');

        this.editor = new Quill(this.$refs.editor, {
            theme: 'snow',
            formats: FORMATOS,
            modules: { toolbar: BARRA },
            placeholder: textos.marcador ?? '',
        });

        // Lo que ya está guardado viene saneado del servidor: se carga tal cual para seguir editándolo.
        const inicial = this.$wire.$get(propiedad) ?? '';
        if (inicial !== '') {
            this.editor.clipboard.dangerouslyPasteHTML(inicial, 'silent');
        }

        this.nombrarBotones();
        this.editor.on('text-change', () => this.$wire.$set(propiedad, this.html(), false));
    },

    /**
     * HTML del editor listo para enviar. Se toma del DOM (y no de getSemanticHTML, que convierte cada
     * espacio en &nbsp; y rompe el ajuste de línea) y se arreglan dos cosas de Quill 2: las listas con
     * puntos, que dibuja como <ol> con data-list, y el <span class="ql-ui"> que agrega a cada <li>.
     */
    html() {
        if (this.editor.getText().trim() === '') {
            return '';
        }

        const contenedor = document.createElement('div');
        contenedor.innerHTML = this.editor.root.innerHTML;
        contenedor.querySelectorAll('.ql-ui').forEach((adorno) => adorno.remove());
        contenedor.querySelectorAll('ol').forEach((lista) => {
            if (lista.querySelector('li[data-list="bullet"]') === null) {
                return;
            }
            const puntos = document.createElement('ul');
            puntos.innerHTML = lista.innerHTML;
            lista.replaceWith(puntos);
        });
        contenedor.querySelectorAll('li[data-list]').forEach((punto) => punto.removeAttribute('data-list'));

        return contenedor.innerHTML.trim();
    },

    nombrarBotones() {
        Object.entries(NOMBRES).forEach(([selector, nombre]) => {
            const boton = this.$root.querySelector(`button.${selector}`);
            if (boton) {
                boton.setAttribute('aria-label', nombre);
                boton.setAttribute('title', nombre);
            }
        });
    },

    destroy() {
        this.editor = null;
    },
});
