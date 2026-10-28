import { expect, it } from 'vite-plus/test';
import { euros } from './euros';

it('formats positive and negative cents as Catalan money with grouping', () => {
    expect(euros(123450)).toBe('1.234,50\u00a0€');
    expect(euros(-123450)).toBe('-1.234,50\u00a0€');
    expect(euros(50)).toBe('0,50\u00a0€');
});
