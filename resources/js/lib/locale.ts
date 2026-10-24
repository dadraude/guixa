const money = new Intl.NumberFormat('ca-ES', {
    style: 'currency',
    currency: 'EUR',
});
const pickupDate = new Intl.DateTimeFormat('ca-ES', { timeZone: 'UTC' });

/** Presentation only: request quantities and cent arithmetic keep their original values. */
export const formatMoney = (amount: string) => money.format(Number(amount));

/** A pickup date is a calendar date, not an instant in the browser's timezone. */
export const formatPickupDate = (date: string) =>
    pickupDate.format(new Date(`${date}T00:00:00Z`));
