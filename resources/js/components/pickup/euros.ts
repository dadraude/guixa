export const euros = (cents: number) =>
    `${(cents / 100).toFixed(2).replace('.', ',')} €`;
