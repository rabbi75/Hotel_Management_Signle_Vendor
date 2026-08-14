import { initialiseTheme } from '@/hooks/use-theme';
import { createInertiaApp } from '@inertiajs/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Toaster } from './components/ui/toast';

const appName = import.meta.env.VITE_APP_NAME || 'Hotel Management';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            // Inertia already delivers fresh page props on navigation; TanStack
            // Query only backs widgets that poll, so a short stale window is
            // enough and avoids a refetch storm on every window focus.
            staleTime: 30_000,
            retry: 1,
            refetchOnWindowFocus: false,
        },
    },
});

void createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        createRoot(el).render(
            <QueryClientProvider client={queryClient}>
                <App {...props} />
                <Toaster />
            </QueryClientProvider>,
        );
    },
    progress: {
        color: 'var(--primary)',
        delay: 150,
    },
});

initialiseTheme();
