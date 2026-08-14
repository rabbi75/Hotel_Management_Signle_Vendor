import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echo: Echo<'pusher'> | null = null;

/**
 * Lazily connect to Pusher.
 *
 * The kit ships with no credentials, so realtime features must degrade to a
 * no-op rather than throwing: callers get `null` and simply never receive
 * events until a key is configured.
 */
export function getEcho(): Echo<'pusher'> | null {
    if (echo) {
        return echo;
    }

    const key = import.meta.env.VITE_PUSHER_APP_KEY;

    if (!key) {
        return null;
    }

    window.Pusher = Pusher;

    echo = new Echo({
        broadcaster: 'pusher',
        key,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
        wsHost: import.meta.env.VITE_PUSHER_HOST || undefined,
        wsPort: Number(import.meta.env.VITE_PUSHER_PORT ?? 443),
        wssPort: Number(import.meta.env.VITE_PUSHER_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo = echo;

    return echo;
}

export function disconnectEcho(): void {
    echo?.disconnect();
    echo = null;
}
