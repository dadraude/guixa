import dictionary from '../../lang/ca.json';
import { describe, expect, it } from 'vite-plus/test';
import { untranslatedStaticText } from './lib/translationCoverage';

const sources = {
    ...import.meta.glob('./pages/**/*.vue', {
        query: '?raw',
        eager: true,
        import: 'default',
    }),
    ...import.meta.glob('./components/**/*.vue', {
        query: '?raw',
        eager: true,
        import: 'default',
    }),
    ...import.meta.glob('./layouts/**/*.vue', {
        query: '?raw',
        eager: true,
        import: 'default',
    }),
    ...import.meta.glob('./composables/useTwoFactorAuth.ts', {
        query: '?raw',
        eager: true,
        import: 'default',
    }),
    ...import.meta.glob('./lib/passkeyErrors.ts', {
        query: '?raw',
        eager: true,
        import: 'default',
    }),
} as Record<string, string>;

describe('app-wide translation coverage', () => {
    it('rejects raw text in template labels, accessible attributes and script display copy', () => {
        const source = `<script setup lang="ts">const title = { label: 'A Catalan label' }</script>
<template><button aria-label="Close dialog">Desa <span title="More information">{{ title.label }}</span></button></template>`;
        expect(
            untranslatedStaticText(source, 'Fixture.vue').map(
                ({ value }) => value,
            ),
        ).toEqual([
            'Close dialog',
            'Desa',
            'More information',
            'A Catalan label',
        ]);
    });

    it('rejects untranslated bound labels, interpolated choices and mixed template strings', () => {
        const source =
            '<script setup lang="ts">const title = `Edit ${name}`</script>\n' +
            "<template><button :aria-label=\"shown ? 'Hide password' : 'Show password'\">" +
            "{{ ready ? 'Ready' : 'Waiting' }}</button></template>";
        expect(
            untranslatedStaticText(source, 'Mixed.vue').map(
                ({ value }) => value,
            ),
        ).toEqual([
            'Hide password',
            'Show password',
            'Ready',
            'Waiting',
            '`Edit ${name}`',
        ]);
    });

    it('rejects raw custom passkey props and a static generated-name connector', () => {
        const source = `<script setup lang="ts">const name = [browser, os].filter(Boolean).join(' on ')</script>
<template><PasskeyVerify loading-label="Confirming..." separator="Or confirm with password" /></template>`;
        expect(
            untranslatedStaticText(source, 'Passkey.vue').map(
                ({ value }) => value,
            ),
        ).toEqual(['Confirming...', 'Or confirm with password', ' on ']);
    });

    it('distinguishes package error identity comparisons from displayed copy', () => {
        expect(
            untranslatedStaticText(
                "const known = error.message === 'An unknown error occurred.'",
                'Adapter.ts',
            ),
        ).toEqual([]);
    });

    it('rejects raw options and async errors that flow into user-visible components', () => {
        expect(
            untranslatedStaticText(
                "const weekdays = ['Monday']; errors.value.push('Failed to load')",
                'Labels.ts',
            ).map(({ value }) => value),
        ).toEqual(['Monday', 'Failed to load']);
    });

    it('keeps cold-imported auth layout metadata as translated source keys', () => {
        const consumer = sources['./layouts/auth/AuthSimpleLayout.vue'];
        expect(consumer).toContain('trans(title)');
        expect(consumer).toContain('trans(description)');
        for (const [path, source] of Object.entries(sources).filter(([path]) =>
            path.startsWith('./pages/auth/'),
        )) {
            for (const [, key] of source.matchAll(
                /(?:title|description):\s*'([^']+)'/g,
            )) {
                expect(
                    (dictionary as Record<string, string>)[key],
                    `${path}: ${key}`,
                ).toBeTypeOf('string');
            }
        }
        const coldSource =
            '<script setup lang="ts">defineOptions({ layout: { title: \'Cold auth heading\' } })</script>';
        expect(untranslatedStaticText(coldSource, 'Auth.vue')).toEqual([]);
    });

    it('rejects translation calls in hoisted macros and verifies breadcrumb resolution', () => {
        const hoisted =
            "<script setup lang=\"ts\">defineOptions({ layout: { breadcrumbs: [{ title: trans('Cycles') }] } }); withDefaults(defineProps<{title?: string}>(), { title: trans('Error') })</script>";
        expect(
            untranslatedStaticText(hoisted, 'Hoisted.vue').map(
                ({ value }) => value,
            ),
        ).toEqual(["trans('Cycles')", "trans('Error')"]);
        expect(sources['./components/Breadcrumbs.vue']).toContain(
            'trans(item.title)',
        );
        for (const [path, source] of Object.entries(sources).filter(([path]) =>
            path.startsWith('./pages/'),
        )) {
            const block =
                source.match(/defineOptions\(\{[\s\S]*?\}\);/)?.[0] ?? '';
            for (const [, key] of block.matchAll(/title:\s*'([^']+)'/g)) {
                expect(
                    (dictionary as Record<string, string>)[key],
                    `${path}: ${key}`,
                ).toBeTypeOf('string');
            }
        }
    });

    it('has no untranslated static user-facing text in any page, component or layout', () => {
        const findings = Object.entries(sources).flatMap(([path, source]) =>
            untranslatedStaticText(source, path).map(
                ({ kind, value }) => `${path}: ${kind}: ${value}`,
            ),
        );
        expect(findings).toEqual([]);
    });

    it('has a Catalan value for each literal Vue translation key', () => {
        const keys = Object.entries(sources).flatMap(([path, source]) =>
            [
                ...source.matchAll(
                    /\b(?:trans|transChoice|\$t)\(\s*(?:'((?:\\.|[^'])*)'|"((?:\\.|[^"])*)")/g,
                ),
            ]
                .map(([, single, double]) =>
                    (single ?? double).replace(/\\(['"\\])/g, '$1'),
                )
                .filter((key) => !key.startsWith('errors.'))
                .map((key) => [path, key] as const),
        );
        const missing = keys.filter(
            ([, key]) =>
                typeof (dictionary as Record<string, string>)[key] !== 'string',
        );
        expect(missing).toEqual([]);
    });
});
