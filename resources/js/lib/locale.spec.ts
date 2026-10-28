import { expect, it } from 'vite-plus/test';
import { formatEventDateTime, formatQuantity } from './locale';

it('formats cooperative local wallet times without changing their supplied hour', () => {
    const winter = formatEventDateTime('2026-01-15 12:00');
    const summer = formatEventDateTime('2026-07-15 12:00');

    expect(winter).toContain('12:00');
    expect(summer).toContain('12:00');
    expect(winter).not.toContain('2026-01-15');
});

it('formats decimal quantities without changing precision or their request strings', () => {
    expect(formatQuantity('2.000')).toBe('2,000');
    expect(formatQuantity('12345.678')).toBe('12.345,678');
    expect(formatQuantity('-1234.5')).toBe('−1.234,5');
    expect(formatQuantity('0')).toBe('0');
});
