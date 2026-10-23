type Offered = {
    id: string;
    name: string;
    unit: string;
    price: string;
    variable: boolean;
};

type Producer = {
    producerId: string;
    producerName: string;
    open: boolean;
    products: Offered[];
};

type Line = {
    productId: string;
    producerId: string;
    producerName: string;
    name: string;
    unit: string;
    price: string;
    variable: boolean;
    quantity: string;
};

export type OrderRow = {
    productId: string;
    name: string;
    unit: string;
    price: string;
    variable: boolean;
    /** The saved quantity, empty when the product is not in the order. */
    quantity: string;
};

export type ProducerRows = {
    producerId: string;
    producerName: string;
    open: boolean;
    rows: OrderRow[];
};

const fromLine = (line: Line): OrderRow => ({
    productId: line.productId,
    name: line.name,
    unit: line.unit,
    price: line.price,
    variable: line.variable,
    quantity: line.quantity,
});

const fromOffer = (product: Offered): OrderRow => ({
    productId: product.id,
    name: product.name,
    unit: product.unit,
    price: product.price,
    variable: product.variable,
    quantity: '',
});

/**
 * One group per producer: a line shows the product as it was when the line was last changed (R10), whatever the
 * catalog says now; the other offered products follow. Lines of a producer that no longer takes part stay under its name, read-only (T15 replaces this).
 */
export function orderRows(order: {
    producers: Producer[];
    lines: Line[];
}): ProducerRows[] {
    const groups: ProducerRows[] = order.producers.map((producer) => {
        const lines = order.lines.filter(
            (line) => line.producerId === producer.producerId,
        );
        const lineOf = new Map(lines.map((line) => [line.productId, line]));
        const offered = new Set(producer.products.map((product) => product.id));

        return {
            producerId: producer.producerId,
            producerName: producer.producerName,
            open: producer.open,
            rows: [
                ...producer.products.map((product) => {
                    const line = lineOf.get(product.id);

                    return line === undefined
                        ? fromOffer(product)
                        : fromLine(line);
                }),
                ...lines
                    .filter((line) => !offered.has(line.productId))
                    .map(fromLine),
            ],
        };
    });

    const known = new Set(
        order.producers.map((producer) => producer.producerId),
    );
    const orphans = order.lines.filter((line) => !known.has(line.producerId));

    const names = new Map(
        orphans.map((line) => [line.producerId, line.producerName]),
    );

    for (const producerId of names.keys()) {
        groups.push({
            producerId,
            producerName: names.get(producerId) ?? producerId,
            open: false,
            rows: orphans
                .filter((line) => line.producerId === producerId)
                .map(fromLine),
        });
    }

    return groups;
}
