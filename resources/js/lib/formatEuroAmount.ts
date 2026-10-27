const euroFormatter = new Intl.NumberFormat('ca-ES', {
    style: 'currency',
    currency: 'EUR',
});

export function formatEuroAmount(value: string): string {
    return euroFormatter.format(Number(value));
}
