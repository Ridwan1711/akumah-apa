import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

type EchoInstance = Echo<'reverb'>;

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

let instance: EchoInstance | null = null;

function readCsrfToken(): string {
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    return token?.trim() ?? '';
}

function readMeta(name: string): string {
    const value = document
        .querySelector(`meta[name="${name}"]`)
        ?.getAttribute('content');

    return value?.trim() ?? '';
}

export function getEcho(): EchoInstance {
    if (instance) return instance;

    window.Pusher = Pusher;

    const appKey = import.meta.env.VITE_REVERB_APP_KEY || readMeta('reverb-app-key');
    const host = import.meta.env.VITE_REVERB_HOST || readMeta('reverb-host') || window.location.hostname;
    const port = Number(import.meta.env.VITE_REVERB_PORT || readMeta('reverb-port') || 8080);
    const scheme = (import.meta.env.VITE_REVERB_SCHEME || readMeta('reverb-scheme') || 'http') as
        | 'http'
        | 'https';
    const forceTLS = scheme === 'https';
    const csrf = readCsrfToken();

    if (!appKey) {
        throw new Error(
            'Realtime misconfigured: REVERB_APP_KEY tidak tersedia (cek env + restart build/container).',
        );
    }

    instance = new Echo({
        broadcaster: 'reverb',
        key: appKey,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS,
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                fetch('/broadcasting/auth', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: channel.name,
                    }),
                })
                    .then(async (response) => {
                        if (!response.ok) {
                            const text = await response.text();
                            throw new Error(text || `Auth failed (${response.status})`);
                        }
                        return response.json();
                    })
                    .then((data) => callback(null, data))
                    .catch((error) => callback(error as Error, null));
            },
        }),
    });

    return instance;
}

export function disconnectEcho(): void {
    if (!instance) return;
    instance.disconnect();
    instance = null;
}

