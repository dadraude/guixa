import { fileURLToPath } from 'node:url';
import { realpathSync } from 'node:fs';
import { defineConfig } from 'vite-plus';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

// Uses existing generated routes without booting PHP or connecting to a database.
export default defineConfig({
    root: fileURLToPath(new URL('../../../', import.meta.url)),
    resolve: { alias: { '@': fileURLToPath(new URL('../', import.meta.url)) } },
    plugins: [vue(), tailwindcss()],
    test: { include: ['resources/js/**/*.spec.ts'] },
    server: {
        host: '127.0.0.1',
        port: 5193,
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
