// Test helper: the server decides these flags (BasketActions, BasketData); specs build rows by hand,
// so this derives the same values from a row's state, kind and pendingCollection.
export type ServerFlags = 'can' | 'status' | 'settled' | 'collected' | 'weekly';

/** A row as a spec writes it: everything the server derives is left out. */
export type RowInput = Omit<App.Http.BasketRow, ServerFlags>;

export function withServerFlags(
    row: RowInput,
    audience: 'staff' | 'member',
): App.Http.BasketRow {
    const staff = audience === 'staff';
    const draft = row.state === 'draft';
    const settled = row.state === 'validated' || row.state === 'collected';
    const reversed = row.state === 'validated' && !row.pendingCollection;

    return {
        ...row,
        projectedBalanceCents: draft ? row.balanceCents - row.totalCents : null,
        status: reversed ? 'reversed' : row.state,
        settled,
        collected: row.state === 'collected',
        weekly: row.kind === 'weekly',
        can: {
            edit: staff && draft,
            validate: staff && draft,
            cancel: staff && draft && row.kind !== 'weekly',
            markCollected: row.pendingCollection && row.state === 'validated',
            correct: staff && settled,
            correctCollection: staff && row.state === 'collected',
        },
    };
}
