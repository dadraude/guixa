import { describe, expect, it } from 'vite-plus/test';
import { lineCents, orderTotal } from './orderTotal';

describe('orderTotal', () => {
    it('multiplies a price by a quantity in thousandths', () => {
        expect(lineCents({ price: '20.00', quantity: '1.120' })).toBe(2240);
    });

    it('rounds half up to the cent', () => {
        expect(lineCents({ price: '0.01', quantity: '0.500' })).toBe(1);
        expect(lineCents({ price: '0.01', quantity: '0.499' })).toBe(0);
    });

    it('adds the lines and formats euros', () => {
        expect(
            orderTotal([
                { price: '3.50', quantity: '2' },
                { price: '2.80', quantity: '0.250' },
            ]),
        ).toBe('7.70');
        expect(orderTotal([])).toBe('0.00');
    });

    it('counts a draft that is not a quantity as nothing', () => {
        expect(lineCents({ price: '3.50', quantity: '' })).toBe(0);
        expect(lineCents({ price: '3.50', quantity: '-1' })).toBe(0);
        expect(lineCents({ price: '3.50', quantity: '1.2345' })).toBe(0);
    });
});
