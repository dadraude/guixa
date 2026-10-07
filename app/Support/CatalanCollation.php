<?php

declare(strict_types=1);

namespace App\Support;

use Collator;

/**
 * Locale-aware ordering so that "Àlex" sorts before "Zoe"; a plain lowercase
 * compare orders by code point and puts accented letters last.
 */
final class CatalanCollation
{
    private readonly Collator $collator;

    public function __construct()
    {
        $this->collator = new Collator('ca_ES');
    }

    public function compare(string $a, string $b): int
    {
        return (int) $this->collator->compare($a, $b);
    }
}
