<?php

namespace App\Http;

/** Converts between the euro text of a form field and the integer cents the use cases take. */
final class EuroAmount
{
    public const PATTERN = '/^-?\d{1,7}(?:[.,]\d{1,2})?$/';

    public static function toCents(string $euros): int
    {
        $negative = str_starts_with($euros, '-');
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '.', ltrim($euros, '-')), 2), 2, '');
        $cents = ((int) $whole) * 100 + (int) str_pad($fraction, 2, '0');

        return $negative ? -$cents : $cents;
    }

    public static function format(int $cents): string
    {
        return sprintf('%s%d.%02d', $cents < 0 ? '-' : '', intdiv(abs($cents), 100), abs($cents) % 100);
    }
}
