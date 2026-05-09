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

function normalizeRealtimeHost(host: string): string {
    const raw = host.trim();
    // 0.0.0.0 valid untuk bind server, tapi INVALID untuk host tujuan browser.
    if (!raw || raw === '0.0.0.0' || raw === '::') {
        return window.location.hostname;
    }

    return raw;
}

export function getEcho(): EchoInstance {
    if (instance) return instance;

    window.Pusher = Pusher;

    const appKey = import.meta.env.VITE_REVERB_APP_KEY || readMeta('reverb-app-key');
    const host = normalizeRealtimeHost(
        import.meta.env.VITE_REVERB_HOST || readMeta('reverb-host') || window.location.hostname,
    );
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
                const payload = new URLSearchParams({
                    socket_id: socketId,
                    channel_name: channel.name,
                });

                fetch('/broadcasting/auth', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: payload.toString(),
                })
                    .then(async (response) => {
                        const raw = await response.text();
                        if (!response.ok) {
                            throw new Error(raw || `Auth failed (${response.status})`);
                        }

                        // Laravel biasanya mengembalikan JSON string auth payload.
                        // Jika body kosong / non-JSON, throw agar error terlihat jelas.
                        if (!raw.trim()) {
                            throw new Error('Auth failed: empty response body from /broadcasting/auth');
                        }

                        try {
                            return JSON.parse(raw) as unknown;
                        } catch {
                            throw new Error(`Auth failed: non-JSON response (${raw.slice(0, 120)})`);
                        }
                    })
                    .then((data) => callback(null, data as { auth: string; channel_data?: string }))
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

