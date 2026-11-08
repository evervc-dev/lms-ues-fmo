import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Sin VITE_REVERB_HOST (imagen Docker) se usa el mismo dominio de la página: nginx pasa /app/ a Reverb.
const tls = (import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '')) === 'https';
const puerto = import.meta.env.VITE_REVERB_PORT || window.location.port || (tls ? 443 : 80);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: puerto,
    wssPort: puerto,
    forceTLS: tls,
    enabledTransports: ['ws', 'wss'],
});
