import { describe, expect, it } from 'vite-plus/test';
import {
    currentNavigationItem,
    navigationItemsFor,
    navigationSectionsFor,
} from '@/composables/useAppNavigation';

describe('shared app navigation', () => {
    it('shows only destinations permitted by the current roles', () => {
        const manager = navigationItemsFor([{ role: 'manager' }]);
        const member = navigationItemsFor([{ role: 'member' }]);

        expect(manager.map((item) => item.title)).toContain('Stock');
        expect(manager.map((item) => item.title)).toContain('Baskets');
        expect(manager.map((item) => item.title)).not.toContain('Wallet');
        expect(member.map((item) => item.title)).toContain('Wallet');
        expect(member.map((item) => item.title)).toContain('Stock catalogue');
        expect(member.map((item) => item.title)).not.toContain('Stock');
        expect(member.map((item) => item.title)).toContain('My baskets');
        expect(
            navigationItemsFor([{ role: 'producer' }]).map(
                (item) => item.title,
            ),
        ).not.toContain('Stock catalogue');
        expect(
            navigationItemsFor([{ role: 'producer' }, { role: 'member' }]).map(
                (item) => item.title,
            ),
        ).toContain('Stock catalogue');
    });

    it('groups destinations into role-appropriate sections without duplicates', () => {
        const sections = navigationSectionsFor([
            { role: 'member' },
            { role: 'manager' },
            { role: 'producer' },
        ]);

        expect(sections.map((section) => section.title)).toEqual([
            'Overview',
            'Orders and wallet',
            'Cooperative',
            'Catalog and stock',
            'Administration',
            'Production',
        ]);
        expect(
            sections.map((section) => section.items.map((item) => item.title)),
        ).toEqual([
            ['Dashboard'],
            ['Orders', 'My baskets', 'Stock catalogue', 'Wallet'],
            ['Cycles', 'Households', 'Baskets'],
            ['Producers', 'Stock', 'Return items'],
            ['Accounts', 'Settings'],
            ['Products', 'Default cutoff'],
        ]);
        const titles = navigationItemsFor([
            { role: 'member' },
            { role: 'manager' },
            { role: 'producer' },
        ]).map((item) => item.title);
        expect(titles).toHaveLength(15);
        expect(new Set(titles).size).toBe(titles.length);
    });

    it('keeps the parent destination selected on detail pages', () => {
        const items = navigationItemsFor([{ role: 'manager' }]);
        const memberItems = navigationItemsFor([{ role: 'member' }]);

        expect(
            currentNavigationItem(
                items,
                '/manage/stock/12345678-1234-1234-1234-123456789abc/movements',
            )?.title,
        ).toBe('Stock');
        expect(
            currentNavigationItem(items, '/manage/stockroom'),
        ).toBeUndefined();
        expect(
            currentNavigationItem(items, '/finance/baskets/example')?.title,
        ).toBe('Baskets');
        expect(
            currentNavigationItem(memberItems, '/baskets/example')?.title,
        ).toBe('My baskets');
    });
});
