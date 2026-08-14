import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';
import type { RouteName } from 'ziggy-js';
import { route } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Hotel Management';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => (title ? `${title} — ${appName}` : appName),
        resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
        setup: ({ App, props }) => {
            /* eslint-disable @typescript-eslint/no-explicit-any */
            // Ziggy has no server-side singleton, so the helper is installed on
            // globalThis from the location shipped in the page props.
            (globalThis as any).route = (name: RouteName, params?: any, absolute?: boolean) =>
                route(name, params, absolute, {
                    ...(page.props.ziggy as any),
                    location: new URL((page.props.ziggy as any).location),
                });
            /* eslint-enable @typescript-eslint/no-explicit-any */

            return <App {...props} />;
        },
    }),
);
