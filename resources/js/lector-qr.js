/**
 * Lector de QR con la cámara (RF20, ADR-046). Usa el BarcodeDetector nativo cuando el navegador lo trae y
 * lee códigos QR; si no (iOS Safari, Firefox), el de `barcode-detector`, que corre ZXing en WebAssembly.
 * El .wasm se sirve desde la propia app, no desde un CDN.
 */
import { BarcodeDetector as BarcodeDetectorPonyfill, prepareZXingModule } from 'barcode-detector/ponyfill';
import wasmUrl from 'zxing-wasm/reader/zxing_reader.wasm?url';

prepareZXingModule({
    overrides: {
        locateFile: (ruta, prefijo) => (ruta.endsWith('.wasm') ? wasmUrl : prefijo + ruta),
    },
});

/**
 * @returns {Promise<{detector: object, motor: string}>}
 */
async function crearDetector() {
    if ('BarcodeDetector' in window) {
        try {
            if ((await window.BarcodeDetector.getSupportedFormats()).includes('qr_code')) {
                return { detector: new window.BarcodeDetector({ formats: ['qr_code'] }), motor: 'nativo' };
            }
        } catch {
            // Sigue con el ponyfill.
        }
    }

    return { detector: new BarcodeDetectorPonyfill({ formats: ['qr_code'] }), motor: 'zxing' };
}

/**
 * Reproduce la cámara en `video`. Si el navegador bloquea la reproducción automática (pasa cuando ya no hay un
 * toque reciente del usuario, p. ej. tras esperar el GPS), avisa con `alPedirToque` y espera a que toquen el video.
 */
async function reproducir(video, alPedirToque) {
    try {
        await video.play();
    } catch (error) {
        if (error?.name !== 'NotAllowedError') {
            throw error;
        }
        alPedirToque?.();
        await new Promise((resolver, rechazar) => {
            video.addEventListener('click', () => video.play().then(resolver, rechazar), { once: true });
        });
    }
}

/**
 * Abre la cámara trasera en `video` y llama a `alLeer(texto)` con el primer QR que encuentre. Antes avisa con
 * `alDetectar(esquinas, anchoVideo, altoVideo)` para dibujar su contorno, y espera un instante para que se vea.
 * Si algo falla después de abrir la cámara, la apaga antes de lanzar el error.
 *
 * @returns {Promise<{detener: Function, motor: string, resolucion: string}>}
 */
export async function leerQr(video, { alLeer, alPedirToque, alDetectar } = {}) {
    const { detector, motor } = await crearDetector();
    const flujo = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
    let activo = true;

    const detener = () => {
        activo = false;
        flujo.getTracks().forEach((pista) => pista.stop());
        video.srcObject = null;
    };

    try {
        // Como propiedades, no solo atributos: sin `muted` el navegador no reproduce sin un toque del usuario.
        video.muted = true;
        video.playsInline = true;
        video.setAttribute('playsinline', '');
        video.srcObject = flujo;
        await reproducir(video, alPedirToque);
    } catch (error) {
        detener();
        throw error;
    }

    const buscar = async () => {
        if (!activo) {
            return;
        }
        try {
            const [codigo] = await detector.detect(video);
            if (codigo?.rawValue) {
                activo = false;
                alDetectar?.(codigo.cornerPoints ?? [], video.videoWidth, video.videoHeight);
                setTimeout(() => {
                    detener();
                    alLeer?.(codigo.rawValue);
                }, 400);

                return;
            }
        } catch {
            // Un fotograma que no se pudo leer: se intenta con el siguiente.
        }
        setTimeout(buscar, 250);
    };
    buscar();

    return { detener, motor, resolucion: `${video.videoWidth}×${video.videoHeight}` };
}
