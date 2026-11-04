<?php

namespace Guixa\Sales\Domain;

enum BasketLineKind: string
{
    case Stock = 'stock';

    case Return = 'return';

    case Weekly = 'weekly';

    public function isStock(): bool
    {
        return $this === self::Stock;
    }

    public function isReturn(): bool
    {
        return $this === self::Return;
    }

    public function isWeekly(): bool
    {
        return $this === self::Weekly;
    }
}
