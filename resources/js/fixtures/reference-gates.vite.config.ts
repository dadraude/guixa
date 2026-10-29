import { fileURLToPath } from 'node:url';
import { realpathSync } from 'node:fs';
import { defineConfig } from 'vite-plus';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    root: fileURLToPath(new URL('../../../', import.meta.url)),
    resolve: { alias: { '@': fileURLToPath(new URL('../', import.meta.url)) } },
    plugins: [vue(), tailwindcss()],
    server: {
        host: '127.0.0.1',
        port: 5197,
        strictPort: true,
        fs: {
            allow: [
                fileURLToPath(new URL('../../../', import.meta.url)),
                realpathSync(
                    fileURLToPath(
                        new URL('../../../node_modules', import.meta.url),
                    ),
                ),
            ],
        },
    },
});
