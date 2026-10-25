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
]);

export function isPilotThemePage(pageName: string): boolean {
    return pilotPages.has(pageName);
}
