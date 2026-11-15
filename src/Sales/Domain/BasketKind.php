<?php

namespace Guixa\Sales\Domain;

enum BasketKind: string
{
    case Stock = 'stock';

    case Weekly = 'weekly';

    public function isWeekly(): bool
    {
        return $this === self::Weekly;
    }
}
