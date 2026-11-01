import { defineConfig } from 'vite-plus';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [vue(), tailwindcss()],
    resolve: {
        alias: [
            {
                find: '@inertiajs/vue3',
                replacement: fileURLToPath(
                    new URL('./inertia.ts', import.meta.url),
                ),
            },
            {
                find: '@',
                replacement: fileURLToPath(
                    new URL('../../resources/js', import.meta.url),
                ),
            },
        ],
    },
    server: { host: '127.0.0.1', port: 5186, strictPort: true },
});
