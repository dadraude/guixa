import { formatMoney } from '@/lib/locale';

export const euros = (cents: number) => formatMoney((cents / 100).toFixed(2));
