import './bootstrap';
import editorPaginas from './editor-paginas';
import graficoReporte from './grafico-reporte';
import lectorAsistencia from './lector-asistencia';
import mapaArea from './mapa-area';
import selectorCarpeta from './selector-carpeta';

// Alpine lo arranca Livewire en DOMContentLoaded, después de este módulo.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('lectorAsistencia', lectorAsistencia);
    window.Alpine.data('editorPaginas', editorPaginas);
    window.Alpine.data('selectorCarpeta', selectorCarpeta);
    window.Alpine.data('mapaArea', mapaArea);
    window.Alpine.data('graficoReporte', graficoReporte);
});
