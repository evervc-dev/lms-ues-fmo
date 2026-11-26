/**
 * Soporte global de autoenfoque (autofocus) en errores de validación de formularios.
 *
 * Cuando un formulario (Livewire o estándar) falla en su validación:
 * 1. Localiza el primer campo con error de validación en el orden del DOM.
 * 2. Aplica el foco (.focus()) en dicho control para que el usuario pueda corregirlo de inmediato.
 * 3. Desplaza la vista con suavidad (scrollIntoView) centrándolo en pantalla junto a su mensaje de error.
 */

/**
 * Busca y enfoca el primer control con error dentro de un contenedor.
 *
 * @param {Element|Document} contenedor
 * @param {string[]} errorKeys Lista opcional de claves de atributos que fallaron la validación.
 * @returns {boolean} True si se encontró y enfocó un elemento inválido.
 */
export function enfocarPrimerError(contenedor, errorKeys = []) {
    if (!contenedor || !(contenedor instanceof Element || contenedor instanceof Document)) {
        return false;
    }

    let objetivo = null;

    // 1. Si tenemos las claves de error del backend, buscar el primer campo que coincida en el DOM
    if (Array.isArray(errorKeys) && errorKeys.length > 0) {
        for (const clave of errorKeys) {
            if (!clave || typeof clave !== 'string') {
                continue;
            }

            const claveLimpia = clave.trim();
            const claveEscapada = CSS.escape(claveLimpia);
            const claveValor = claveLimpia.replace(/"/g, '\\"');

            const selectores = [
                // Coincidencia exacta por nombre o id
                `[name="${claveEscapada}"]`,
                `#${claveEscapada}`,
                // Coincidencia por wire:model (Livewire)
                `[wire\\:model="${claveValor}"]`,
                `[wire\\:model\\.blur="${claveValor}"]`,
                `[wire\\:model\\.live="${claveValor}"]`,
                `[wire\\:model\\.defer="${claveValor}"]`,
                `[wire\\:model\\.change="${claveValor}"]`,
                // Coincidencia por id con puntos reemplazados por guion o guion bajo (anidados)
                `#${CSS.escape(claveLimpia.replace(/\./g, '-'))}`,
                `#${CSS.escape(claveLimpia.replace(/\./g, '_'))}`,
            ];

            const encontrado = contenedor.querySelector(selectores.join(', '));
            if (encontrado) {
                objetivo = encontrado;
                break;
            }

            // Buscar si existe el párrafo de error asociado (<x-ui.field para="campo" error="campo">)
            const mensajeError = contenedor.querySelector(`#${claveEscapada}-error`)
                || contenedor.querySelector(`[id$="${claveEscapada}-error"]`);
            if (mensajeError) {
                const contenedorCampo = mensajeError.closest('.flex-col, [class*="flex-col"]') || mensajeError.parentElement;
                if (contenedorCampo) {
                    const control = contenedorCampo.querySelector('input:not([type="hidden"]), select, textarea, [contenteditable="true"]');
                    if (control) {
                        objetivo = control;
                        break;
                    }
                }
            }
        }
    }

    // 2. Si no se encontró por clave directa, buscar el primer elemento marcado con aria-invalid="true"
    // Los componentes <x-ui.input>, <x-ui.select> y <x-ui.textarea> lo fijan automáticamente.
    if (!objetivo) {
        objetivo = contenedor.querySelector('[aria-invalid="true"]:not([type="hidden"]):not([disabled])');
    }

    // 3. Fallback: buscar inputs o controles resaltados con anillo o borde rojo de error
    if (!objetivo) {
        objetivo = contenedor.querySelector('input.ring-red-400:not([type="hidden"]):not([disabled]), select.ring-red-400:not([disabled]), textarea.ring-red-400:not([disabled])');
    }

    // 4. Si el contenedor no contenía el elemento (por ejemplo, formulario en un modal flotante)
    if (!objetivo) {
        const modal = document.querySelector('[role="dialog"]:not([aria-hidden="true"]):not([style*="display: none"])');
        if (modal && modal !== contenedor) {
            objetivo = modal.querySelector('[aria-invalid="true"]:not([type="hidden"]):not([disabled])')
                || modal.querySelector('input.ring-red-400:not([type="hidden"]):not([disabled]), select.ring-red-400:not([disabled]), textarea.ring-red-400:not([disabled])');
        }
    }

    if (!objetivo) {
        return false;
    }

    // Si el control está oculto con sr-only (ej. botones de radio estilizados), enfocar y desplazar la etiqueta
    if (objetivo.classList.contains('sr-only') || objetivo.type === 'hidden') {
        const etiqueta = objetivo.closest('label') || objetivo.parentElement;
        if (etiqueta) {
            if (typeof objetivo.focus === 'function') {
                try { objetivo.focus({ preventScroll: true }); } catch { objetivo.focus(); }
            }
            if (typeof etiqueta.scrollIntoView === 'function') {
                etiqueta.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return true;
        }
    }

    // Enfocar el elemento y centrarlo suavemente en la vista
    if (typeof objetivo.focus === 'function') {
        try {
            objetivo.focus({ preventScroll: true });
        } catch {
            objetivo.focus();
        }

        if (typeof objetivo.scrollIntoView === 'function') {
            objetivo.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
        }

        return true;
    }

    return false;
}

/**
 * Registra los hooks globales para Livewire y el navegador.
 */
export default function registrarAutofocusValidacion() {
    const registrarEnLivewire = () => {
        if (!window.Livewire || window.__autofocusValidacionRegistrado) {
            return;
        }
        window.__autofocusValidacionRegistrado = true;

        let errorPendiente = null;

        // Hook de commit en Livewire: detecta cuando la respuesta del servidor incluye errores de validación
        window.Livewire.hook('commit', ({ component, commit, succeed }) => {
            const teniaLlamadas = Array.isArray(commit?.calls) && commit.calls.length > 0;

            succeed(({ snapshot }) => {
                const errores = snapshot?.memo?.errors ?? component?.snapshot?.memo?.errors;
                const claves = errores ? Object.keys(errores) : [];

                if (claves.length > 0) {
                    errorPendiente = {
                        component,
                        claves,
                        teniaLlamadas,
                    };

                    // Si no hay morph posterior (ej. el DOM no cambió de estructura), ejecutar tras un pequeño retardo
                    setTimeout(() => {
                        if (errorPendiente && errorPendiente.component === component) {
                            ejecutarEnfoque(errorPendiente);
                            errorPendiente = null;
                        }
                    }, 50);
                }
            });
        });

        // Hook morphed: ocurre inmediatamente tras el morphing del DOM por Livewire
        window.Livewire.hook('morphed', ({ el, component }) => {
            if (errorPendiente && errorPendiente.component === component) {
                const pendiente = errorPendiente;
                errorPendiente = null;
                requestAnimationFrame(() => {
                    ejecutarEnfoque(pendiente, el);
                });
            }
        });

        function ejecutarEnfoque(pendiente, elemento = null) {
            const { component, claves, teniaLlamadas } = pendiente;
            const contenedor = elemento || component?.el || document;

            // Si el usuario está escribiendo en otro control sin haber enviado el formulario, no quitarle el foco
            const activo = document.activeElement;
            const escribiendo = activo
                && (activo.tagName === 'INPUT' || activo.tagName === 'TEXTAREA')
                && !['submit', 'button', 'reset'].includes(activo.type)
                && !claves.includes(activo.getAttribute('name') || '')
                && !claves.includes(activo.id || '');

            if (teniaLlamadas || !escribiendo) {
                enfocarPrimerError(contenedor, claves);
            }
        }
    };

    if (window.Livewire) {
        registrarEnLivewire();
    } else {
        document.addEventListener('livewire:init', registrarEnLivewire);
    }

    // Soporte para disparar enfoque manual desde Livewire ($this->dispatch('enfocar-error', campo: '…')) o Alpine
    window.addEventListener('enfocar-error', (evento) => {
        const campo = evento?.detail?.campo || evento?.detail;
        const claves = campo ? [campo] : [];
        enfocarPrimerError(document, claves);
    });

    // Validación HTML5 nativa en el navegador
    document.addEventListener('invalid', (evento) => {
        if (evento.target && typeof evento.target.scrollIntoView === 'function') {
            evento.target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, true);
}
