/**
 * Componente Alpine de «Marcar asistencia» (RF20, ADR-045, ADR-046). Primero pide la ubicación, después abre la
 * cámara y envía al servidor el QR, la ubicación y la copia del token de dispositivo de localStorage; la cookie
 * la lee el servidor. Sin ubicación también envía el QR: el servidor registra el intento como «sin ubicación».
 *
 * Muestra cada paso (ubicación, cámara, lectura, envío y respuesta) para que el estudiante y Soporte sepan dónde
 * se detuvo; con `debug` (APP_DEBUG) agrega los detalles técnicos.
 *
 * <div x-data="lectorAsistencia(qrDeLaUrl, textos, debug)">…</div>
 */
function ubicacion() {
    return new Promise((resolver) => {
        if (!('geolocation' in navigator)) {
            resolver({ error: 'sin geolocation' });

            return;
        }
        navigator.geolocation.getCurrentPosition(
            (p) => resolver({ lat: p.coords.latitude, lng: p.coords.longitude, precision: p.coords.accuracy }),
            (e) => resolver({ error: ['', 'PERMISSION_DENIED', 'POSITION_UNAVAILABLE', 'TIMEOUT'][e.code] ?? e.message }),
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    });
}

function tokenLocal() {
    try {
        return localStorage.getItem('device_token');
    } catch {
        return null;
    }
}

/** El token del QR y el del dispositivo no se muestran completos ni en modo debug. */
const ocultar = (texto) => String(texto ?? '').replace(/[0-9a-f]{64}/gi, (t) => `${t.slice(0, 6)}…`);

const conLimite = (promesa, ms) => Promise.race([promesa, new Promise((_, rechazar) => setTimeout(() => rechazar(new Error('TIMEOUT')), ms))]);

export default (qrInicial, textos, debug = false) => {
    // $wire y el video se toman en init(), desde la raíz: el botón que llama a empezar() está en un x-if y deja
    // de existir apenas cambia el estado; desde él, $wire y $refs ya no encuentran el componente (fetch(null)).
    let wire = null;
    let visor = null;

    return {
        estado: 'inicio',
        error: null,
        tocar: false,
        ubicacion: null,
        detener: null,
        pasos: [],
        contorno: '',
        debug,

        init() {
            wire = this.$wire;
            visor = this.$refs.video ?? this.$root.querySelector('#lector-qr-video');
        },

        paso(id, texto) {
            this.pasos.push({ id, texto, estado: 'curso', detalle: '' });
        },

        cerrarPaso(id, estado, detalle = '') {
            const paso = this.pasos.findLast((p) => p.id === id);
            if (paso) {
                paso.estado = estado;
                paso.detalle = detalle;
            }
        },

        async empezar() {
            this.error = null;
            this.tocar = false;
            this.contorno = '';
            this.pasos = [];
            this.estado = 'ubicando';

            this.paso('ubicacion', textos.ubicacion);
            const inicio = performance.now();
            const u = await ubicacion();
            this.ubicacion = u.error ? null : u;
            this.cerrarPaso('ubicacion', u.error ? 'aviso' : 'ok', u.error
                ? textos.ubicacionFallo + (this.debug ? ` (${u.error})` : '')
                : `${textos.coordenadas.replace(':lat', u.lat.toFixed(5)).replace(':lng', u.lng.toFixed(5))} · ${textos.precision.replace(':n', Math.round(u.precision))}` + (this.debug ? ` · ${Math.round(performance.now() - inicio)} ms` : ''));

            if (qrInicial) {
                const qr = qrInicial;
                qrInicial = null;

                return this.enviar(qr);
            }

            this.estado = 'leyendo';
            this.paso('camara', textos.camara);
            try {
                const { leerQr } = await import('./lector-qr');
                const video = visor ?? document.getElementById('lector-qr-video');
                if (!video) {
                    throw new Error('No se encontró el visor de la cámara.');
                }
                const lector = await leerQr(video, {
                    alPedirToque: () => {
                        this.tocar = true;
                        this.cerrarPaso('camara', 'aviso', textos.tocar);
                    },
                    alDetectar: (esquinas, ancho, alto) => this.dibujarContorno(video, esquinas, ancho, alto),
                    alLeer: (texto) => {
                        this.cerrarPaso('lectura', 'ok', this.debug ? ocultar(texto) : '');
                        this.enviar(texto);
                    },
                });
                this.tocar = false;
                this.cerrarPaso('camara', 'ok', this.debug ? `${lector.motor} · ${lector.resolucion}` : '');
                // Si canceló mientras la cámara arrancaba, se apaga ahora.
                if (this.estado !== 'leyendo') {
                    lector.detener();

                    return;
                }
                this.detener = lector.detener;
                this.paso('lectura', textos.lectura);
            } catch (error) {
                this.estado = 'inicio';
                this.tocar = false;
                const tecnico = `${error?.name ?? 'Error'}${error?.message ? `: ${error.message}` : ''}`;
                this.cerrarPaso('camara', 'error', tecnico);
                this.error = `${textos.camaraFallo} (${tecnico})`;
            }
        },

        /**
         * Contorno del QR detectado sobre el video, que se muestra con object-cover: hay que escalar y centrar.
         */
        dibujarContorno(video, esquinas, ancho, alto) {
            if (!esquinas.length || !ancho || !alto) {
                return;
            }
            const w = video.clientWidth;
            const h = video.clientHeight;
            const escala = Math.max(w / ancho, h / alto);
            const dx = (w - ancho * escala) / 2;
            const dy = (h - alto * escala) / 2;
            this.contorno = esquinas.map((p) => `${Math.round(p.x * escala + dx)},${Math.round(p.y * escala + dy)}`).join(' ');
        },

        async enviar(qr) {
            this.detener = null;
            this.estado = 'enviando';
            this.paso('envio', textos.envio);
            const inicio = performance.now();
            const u = this.ubicacion;
            try {
                const resultado = await conLimite(wire.marcar(qr, u?.lat ?? null, u?.lng ?? null, u?.precision ?? null, tokenLocal()), 20000);
                const ms = this.debug ? ` · ${Math.round(performance.now() - inicio)} ms` : '';
                if (resultado?.mensaje) {
                    this.cerrarPaso('envio', resultado.resultado === 'ok' ? 'ok' : 'error',
                        resultado.mensaje + (this.debug ? ` (${resultado.resultado})` : '') + ms);
                } else {
                    this.cerrarPaso('envio', 'error', textos.sinResultado + ms);
                }
            } catch (error) {
                this.cerrarPaso('envio', 'error', error?.message === 'TIMEOUT' ? textos.sinRespuesta : `${textos.envioFallo} (${error?.message ?? error})`);
            }
            this.estado = 'inicio';
            this.contorno = '';
            this.$nextTick(() => document.getElementById('resultado-asistencia')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
        },

        /**
         * Vuelve a escribir la copia del token de dispositivo que faltaba (ADR-045). Va en un método porque Alpine
         * no acepta un `try` suelto en x-on («Unexpected token 'try'»).
         */
        guardarToken(token) {
            try {
                localStorage.setItem('device_token', token);
            } catch {
                // Navegador sin localStorage (modo privado estricto): queda la cookie.
            }
        },

        cancelar() {
            this.detener?.();
            this.detener = null;
            this.tocar = false;
            this.contorno = '';
            this.cerrarPaso('lectura', 'aviso', textos.cancelado);
            this.estado = 'inicio';
        },

        destroy() {
            this.detener?.();
        },
    };
};
