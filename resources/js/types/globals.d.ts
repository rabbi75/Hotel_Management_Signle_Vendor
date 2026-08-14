import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import type { AxiosInstance } from 'axios';
import type Echo from 'laravel-echo';
import type { route as ziggyRoute } from 'ziggy-js';
import type { SharedProps } from './index';

declare global {
    interface Window {
        axios: AxiosInstance;
        Echo: Echo<'pusher'>;
    }

    /** Provided by Ziggy's @routes directive in the root Blade view. */
    const route: typeof ziggyRoute;
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps, SharedProps {}
}

export {};
