import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',
            // Host-mapped Vite port from compose (default 5174 -> container 5173)
            port: Number(process.env.VITE_HMR_PORT || 5174),
            clientPort: Number(process.env.VITE_HMR_PORT || 5174),
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
