const pilotPages = new Set(['Orders/Index', 'Orders/Edit']);

export function isPilotThemePage(pageName: string): boolean {
    return pilotPages.has(pageName);
}
