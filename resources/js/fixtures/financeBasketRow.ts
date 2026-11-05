import { withServerFlags } from '@/testing/basketFlags';
import type { RowInput } from '@/testing/basketFlags';

type StockLineInput = Omit<
    App.Http.BasketStockLineRow,
    'effectiveQuantity' | 'effectiveAmountCents'
> &
    Partial<
        Pick<
            App.Http.BasketStockLineRow,
            'effectiveQuantity' | 'effectiveAmountCents'
        >
    >;

type FixtureInput = Omit<
    RowInput,
    | 'stockLines'
    | 'returnLines'
    | 'effectiveTotalCents'
    | 'adjustments'
    | 'collectionEvents'
    | 'pendingCollection'
    | 'weeklyLines'
    | 'kind'
    | 'late'
> &
    Partial<Omit<RowInput, 'stockLines'>> & {
        stockLines?: StockLineInput[];
    };

/** Builds a Finance basket row for a visual fixture, filling what the server would derive or default. */
export function financeBasketRow(
    input: FixtureInput,
    audience: 'staff' | 'member' = 'staff',
): App.Http.BasketRow {
    return withServerFlags(
        {
            returnLines: [],
            effectiveTotalCents: input.totalCents,
            adjustments: [],
            collectionEvents: [],
            pendingCollection: input.state === 'validated',
            weeklyLines: [],
            kind: 'stock',
            late: false,
            ...input,
            stockLines: (input.stockLines ?? []).map((line) => ({
                effectiveQuantity: line.quantity,
                effectiveAmountCents: line.amountCents,
                ...line,
            })),
        },
        audience,
    );
}
