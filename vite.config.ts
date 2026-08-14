import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import path from 'node:path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, 'resources/js'),
        },
    },
    build: {
        // Long-lived vendor chunks so an application deploy does not invalidate
        // the framework bundle in the user's browser cache.
        rollupOptions: {
            output: {
                manualChunks: {
                    react: ['react', 'react-dom', '@inertiajs/react'],
                    charts: ['recharts'],
                    table: ['@tanstack/react-table'],
                    query: ['@tanstack/react-query'],
                },
            },
        },
    },
    server: {
        host: '127.0.0.1',
        watch: {
            ignored: ['**/storage/framework/views/**', '**/vendor/**'],
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js/tests/setup.ts'],
        include: ['resources/js/**/*.test.{ts,tsx}'],
        css: false,
    },
});
