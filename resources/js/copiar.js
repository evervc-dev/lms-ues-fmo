/**
 * Copia un texto al portapapeles y dice si lo logró. Usa la API del portapapeles cuando el navegador la permite y, si no
 * (o si la rechaza), un textarea temporal con execCommand('copy'). Se usa como `$copiar(texto)` desde Alpine.
 */
export default async function copiar(texto) {
    if (typeof texto !== 'string' || texto === '') {
        return false;
    }

    if (window.isSecureContext && navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(texto);

            return true;
        } catch {
            // Sigue con el respaldo.
        }
    }

    const area = document.createElement('textarea');
    area.value = texto;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    // Dentro del modal abierto, para que su trampa de foco no lo impida.
    (document.activeElement?.closest('[role="dialog"]') ?? document.body).appendChild(area);
    area.select();
    let copiado = false;
    try {
        copiado = document.execCommand('copy');
    } catch {
        copiado = false;
    }
    area.remove();

    return copiado;
}
