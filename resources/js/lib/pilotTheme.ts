const pilotPages = new Set([
    'Orders/Index',
    'Orders/Edit',
    'Dashboard',
    'Wallet/Statement',
    'Producer/Cutoff',
    'Producer/CycleSummary',
    'Producer/Products/Index',
    'Producer/Products/Create',
    'Producer/Products/Edit',
    'Manage/Accounts/Index',
    'Manage/Households/Index',
    'Manage/Households/Show',
    'Manage/Producers/Index',
    'Manage/Cycles/Index',
    'Manage/Cycles/Show',
    'Manage/Settings',
    'Manage/Stock/Index',
    'Manage/Stock/Create',
    'Manage/Stock/Edit',
    'Manage/Stock/Movements',
    'Manage/ReturnItems/Index',
    'Manage/ReturnItems/Create',
    'Manage/ReturnItems/Edit',
]);

export function isPilotThemePage(pageName: string): boolean {
    return pilotPages.has(pageName);
}
