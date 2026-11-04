// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vite-plus/test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { installTranslations } from '@/lib/i18n';
import Show from './Show.vue';

vi.mock('@inertiajs/vue3', () => ({ Head: () => null }));

describe('Cycle container summary', () => {
    it('shows each household its accumulated balance to return, negative included (R18a, S92)', async () => {
        const app = createSSRApp(Show, {
            summary: {
                cycleId: 'cycle',
                pickupDate: '2026-11-05',
                cancelled: false,
                producers: [],
            },
            containers: {
                total: 3,
                households: [
                    {
                        householdId: 'h',
                        householdName: 'Household H',
                        count: 2,
                        balance: -2,
                    },
                    {
                        householdId: 'k',
                        householdName: 'Household K',
                        count: 1,
                        balance: 5,
                    },
                ],
            },
        });
        installTranslations(app);

        const html = await renderToString(app);

        expect(html).toMatch(
            /data-test="container-balance-h"[^>]*>Envasos per retornar: −2</,
        );
        expect(html).toMatch(
            /data-test="container-balance-k"[^>]*>Envasos per retornar: 5</,
        );
    });
});
