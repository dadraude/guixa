import { describe, expect, it } from 'vite-plus/test';
import {
    currentNavigationItem,
    navigationItemsFor,
} from '@/composables/useAppNavigation';

describe('shared app navigation', () => {
    it('shows only destinations permitted by the current roles', () => {
        const manager = navigationItemsFor([{ role: 'manager' }]);
        const member = navigationItemsFor([{ role: 'member' }]);

        expect(manager.map((item) => item.title)).toContain('Stock');
        expect(manager.map((item) => item.title)).not.toContain('Wallet');
        expect(member.map((item) => item.title)).toContain('Wallet');
        expect(member.map((item) => item.title)).not.toContain('Stock');
    });

    it('keeps the parent destination selected on detail pages', () => {
        const items = navigationItemsFor([{ role: 'manager' }]);

        expect(
            currentNavigationItem(
                items,
                '/manage/stock/12345678-1234-1234-1234-123456789abc/movements',
            )?.title,
        ).toBe('Stock');
        expect(
            currentNavigationItem(items, '/manage/stockroom'),
        ).toBeUndefined();
    });
});
