import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

// Vitest boots a Vite dev server, which laravel-vite-plugin refuses to start when CI is set.
if (process.env.VITEST) {
    process.env.LARAVEL_BYPASS_ENV_CHECK = '1';
}

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    test: {
        include: ['resources/js/**/*.spec.ts'],
        // Specs arrive with the first component that has client-side logic.
        passWithNoTests: true,
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/generated/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
        overrides: [
            {
                // A9: presentational components never fetch, navigate or reach into pages.
                files: ['resources/js/components/**/*.{vue,ts}'],
                rules: {
                    'no-restricted-imports': [
                        'error',
                        {
                            paths: [
                                {
                                    name: 'axios',
                                    message:
                                        'A9: components do not fetch data.',
                                },
                            ],
                            patterns: [
                                {
                                    group: ['@inertiajs/*'],
                                    message:
                                        'A9: components do not import Inertia; the page passes props and handles events.',
                                },
                                {
                                    group: ['@/pages/**', '**/pages/**'],
                                    message:
                                        'A9: components never import from pages.',
                                },
                            ],
                        },
                    ],
                    'no-restricted-globals': [
                        'error',
                        {
                            name: 'fetch',
                            message: 'A9: components do not fetch data.',
                        },
                        {
                            name: 'axios',
                            message: 'A9: components do not fetch data.',
                        },
                        {
                            name: 'router',
                            message: 'A9: components do not navigate.',
                        },
                    ],
                },
            },
            {
                // Starter-kit components that predate A9; move them out of components/ or
                // refactor them, then delete their line here.
                files: [
                    'resources/js/components/AppHeader.vue',
                    'resources/js/components/AppLogo.vue',
                    'resources/js/components/AppShell.vue',
                    'resources/js/components/AppSidebar.vue',
                    'resources/js/components/Breadcrumbs.vue',
                    'resources/js/components/DeleteUser.vue',
                    'resources/js/components/ManagePasskeys.vue',
                    'resources/js/components/ManageTwoFactor.vue',
                    'resources/js/components/NavMain.vue',
                    'resources/js/components/NavUser.vue',
                    'resources/js/components/PasskeyVerify.vue',
                    'resources/js/components/TextLink.vue',
                    'resources/js/components/TwoFactorRecoveryCodes.vue',
                    'resources/js/components/TwoFactorSetupModal.vue',
                    'resources/js/components/UserMenuContent.vue',
                ],
                rules: {
                    'no-restricted-imports': 'off',
                    'no-restricted-globals': 'off',
                },
            },
        ],
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
            'resources/js/generated/**',
            // Hand-wrapped documents; log.md is append-only and must stay byte-identical.
            'specs/**',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
