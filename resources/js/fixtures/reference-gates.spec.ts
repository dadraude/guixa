import { describe, expect, it } from 'vite-plus/test';
import { resolveVisualIdentity } from './reference-gates';

describe('reference visual fixture query', () => {
    it('uses explicit public route paths for role-aware manager and producer navigation', () => {
        expect(
            resolveVisualIdentity(
                'Manage/Stock/Index',
                new URLSearchParams('role=manager&route=%2Fmanage%2Fstock'),
                '/manage/stock',
            ),
        ).toEqual({
            role: 'manager',
            pageUrl: '/manage/stock',
            welcomeAuthenticated: false,
        });
        expect(
            resolveVisualIdentity(
                'Producer/Products/Index',
                new URLSearchParams(
                    'role=producer&route=%2Fproducer%2Fproducts',
                ),
                '/producer/products',
            ),
        ).toEqual({
            role: 'producer',
            pageUrl: '/producer/products',
            welcomeAuthenticated: false,
        });
    });

    it('makes authenticated Welcome opt-in and retains the guest default', () => {
        expect(
            resolveVisualIdentity(
                'Welcome',
                new URLSearchParams('authenticated=1&role=manager&route=%2F'),
                '/',
            ),
        ).toEqual({
            role: 'manager',
            pageUrl: '/',
            welcomeAuthenticated: true,
        });
        expect(
            resolveVisualIdentity('Welcome', new URLSearchParams(), '/'),
        ).toEqual({
            role: 'member',
            pageUrl: '/',
            welcomeAuthenticated: false,
        });
    });

    it('rejects external route overrides', () => {
        expect(() =>
            resolveVisualIdentity(
                'Welcome',
                new URLSearchParams('route=%2F%2Fevil.test'),
                '/',
            ),
        ).toThrow('Invalid visual route');
    });
});
