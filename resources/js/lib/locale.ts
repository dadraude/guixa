const money = new Intl.NumberFormat('ca-ES', {
    style: 'currency',
    currency: 'EUR',
});
const pickupDate = new Intl.DateTimeFormat('ca-ES', { timeZone: 'UTC' });
const eventDateTime = new Intl.DateTimeFormat('ca-ES', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'UTC',
});

/** Presentation only: request quantities and cent arithmetic keep their original values. */
export const formatMoney = (amount: string) => money.format(Number(amount));

/** Keep the source decimal digits, including trailing zeroes, for display only. */
export const formatQuantity = (quantity: string) => {
    const parts = /^(-?)(\d+)(?:\.(\d+))?$/.exec(quantity);
    if (!parts) return quantity;
    const integer = parts[2].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return `${parts[1] ? '−' : ''}${integer}${parts[3] === undefined ? '' : `,${parts[3]}`}`;
};

/** A pickup date is a calendar date, not an instant in the browser's timezone. */
export const formatPickupDate = (date: string) =>
    pickupDate.format(new Date(`${date}T00:00:00Z`));

/** Wallet rows supply cooperative local wall time, already converted by the HTTP adapter. */
export const formatEventDateTime = (localDateTime: string) =>
    eventDateTime.format(new Date(`${localDateTime.replace(' ', 'T')}:00Z`));
