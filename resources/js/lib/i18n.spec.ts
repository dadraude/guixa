import { I18n, reset } from 'laravel-vue-i18n';
import dictionary from '../../../lang/ca.json';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import { i18nOptions } from './i18n';
import { formatMoney, formatPickupDate } from './locale';

afterEach(reset);

describe('shared Catalan translations', () => {
    it('loads the shared dictionary and Laravel-style placeholders', () => {
        const i18n = new I18n(i18nOptions('ca'));

        expect(i18n.trans('Your order')).toBe('La teva comanda');
        expect(i18n.trans('Pickup on :date', { date: '30/10/2026' })).toBe(
            'Recollida el 30/10/2026',
        );
        expect(i18n.trans('errors.ProducerClosed')).toBe(
            'El termini d’aquest productor ja s’ha tancat.',
        );
    });

    it('falls back to Catalan for a locale not shipped yet', () => {
        const i18n = new I18n(i18nOptions('en'));

        expect(i18n.trans('Your order')).toBe('La teva comanda');
    });

    it('formats display values without changing the date or monetary value', () => {
        expect(formatPickupDate('2026-10-30')).toBe('30/10/2026');
        expect(formatMoney('7.70')).toBe('7,70\u00a0€');
        expect(formatMoney('0.00')).toBe('0,00\u00a0€');
    });

    it('has Catalan entries for literal translation keys in the migrated surfaces', () => {
        const files = import.meta.glob('../components/orders/*.vue', {
            query: '?raw',
            eager: true,
            import: 'default',
        });
        const pages = import.meta.glob('../pages/Orders/*.vue', {
            query: '?raw',
            eager: true,
            import: 'default',
        });
        for (const [path, source] of Object.entries({ ...files, ...pages })) {
            for (const match of String(source).matchAll(
                /\btrans(?:Choice)?\('([^']+)'/g,
            )) {
                expect(
                    (dictionary as Record<string, string>)[match[1]],
                    `${path}: ${match[1]}`,
                ).toBeTypeOf('string');
            }
        }
    });
});
