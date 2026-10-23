/** Display-only arithmetic for the order page: the server owns every real amount. */

const SCALE = 1000;

/** Euro text such as "3.50" to cents; null when it is not an amount. */
function parseCents(euros: string): number | null {
    const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(euros);

    if (match === null) {
        return null;
    }

    return Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0'));
}

/** Quantity text such as "1.250" to thousandths; null when it is not a quantity. */
function parseThousandths(quantity: string): number | null {
    const match = /^(\d+)(?:[.,](\d{1,3}))?$/.exec(quantity.trim());

    if (match === null) {
        return null;
    }

    return Number(match[1]) * SCALE + Number((match[2] ?? '').padEnd(3, '0'));
}

export type PricedQuantity = { price: string; quantity: string };

/** Price times quantity in cents, rounded half up; a quantity that is not a number counts as nothing. */
export function lineCents({ price, quantity }: PricedQuantity): number {
    const cents = parseCents(price);
    const thousandths = parseThousandths(quantity);

    if (cents === null || thousandths === null) {
        return 0;
    }

    return Math.floor((cents * thousandths + SCALE / 2) / SCALE);
}

export function formatCents(cents: number): string {
    const whole = Math.floor(cents / 100);

    return `${whole}.${String(cents % 100).padStart(2, '0')}`;
}

/** The running total of the order in euros, e.g. "22.40". */
export function orderTotal(lines: PricedQuantity[]): string {
    return formatCents(lines.reduce((sum, line) => sum + lineCents(line), 0));
}
