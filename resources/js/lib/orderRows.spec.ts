import { describe, expect, it } from 'vite-plus/test';
import { orderRows } from './orderRows';

const baker = {
    producerId: 'baker',
    producerName: 'Baker',
    open: true,
    products: [
        {
            id: 'loaf',
            name: 'Loaf',
            unit: 'loaf',
            price: '3.50',
            variable: false,
        },
        {
            id: 'roll',
            name: 'Roll',
            unit: 'roll',
            price: '1.20',
            variable: false,
        },
    ],
};

const line = (
    productId: string,
    name: string,
    price: string,
    quantity = '2.000',
) => ({
    productId,
    producerId: 'baker',
    producerName: 'Baker',
    name,
    unit: 'loaf',
    price,
    variable: false,
    quantity,
});

describe('orderRows', () => {
    it('shows a line with the snapshot, not the current catalog product', () => {
        const [group] = orderRows({
            producers: [
                {
                    ...baker,
                    products: [
                        {
                            ...baker.products[0],
                            name: 'Loaf XL',
                            price: '4.00',
                        },
                    ],
                },
            ],
            lines: [line('loaf', 'Loaf', '3.50')],
        });

        expect(group.rows).toEqual([
            expect.objectContaining({
                productId: 'loaf',
                name: 'Loaf',
                price: '3.50',
                quantity: '2.000',
            }),
        ]);
    });

    it('offers the products that are not in the order, with an empty quantity', () => {
        const [group] = orderRows({
            producers: [baker],
            lines: [line('loaf', 'Loaf', '3.50')],
        });

        expect(group.rows.map((row) => [row.productId, row.quantity])).toEqual([
            ['loaf', '2.000'],
            ['roll', ''],
        ]);
    });

    it('keeps a line whose product is no longer offered', () => {
        const [group] = orderRows({
            producers: [{ ...baker, products: [] }],
            lines: [line('loaf', 'Loaf', '3.50')],
        });

        expect(group.rows.map((row) => row.productId)).toEqual(['loaf']);
    });

    it('keeps the lines of a producer that no longer takes part under its name, read-only', () => {
        const groups = orderRows({
            producers: [baker],
            lines: [
                {
                    ...line('jam', 'Jam', '5.00'),
                    producerId: 'jam-co',
                    producerName: 'Jam Co',
                },
                {
                    ...line('honey', 'Honey', '6.00'),
                    producerId: 'jam-co',
                    producerName: 'Jam Co',
                },
            ],
        });

        expect(
            groups.map((group) => [
                group.producerId,
                group.producerName,
                group.open,
            ]),
        ).toEqual([
            ['baker', 'Baker', true],
            ['jam-co', 'Jam Co', false],
        ]);
        expect(groups[1].rows.map((row) => row.productId)).toEqual([
            'jam',
            'honey',
        ]);
    });
});
