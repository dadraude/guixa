<?php

namespace Guixa\Sales\Domain;

/** Where a basket stands in its lifecycle. */
enum BasketState: string
{
    case Draft = 'draft';

    case Validated = 'validated';

    case Collected = 'collected';

    case Cancelled = 'cancelled';

    /** A weekly basket whose allocations are all zero (R24). */
    case NothingToCollect = 'nothing_to_collect';

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isValidated(): bool
    {
        return $this === self::Validated;
    }

    public function isCollected(): bool
    {
        return $this === self::Collected;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    /** Validated or collected: the basket was charged. */
    public function isSettled(): bool
    {
        return $this === self::Validated || $this === self::Collected;
    }

    /** A weekly basket that still follows its source order. */
    public function followsSource(): bool
    {
        return $this === self::Draft || $this === self::NothingToCollect;
    }

    /** States a stored basket cannot leave except by the explicit collection transitions. */
    public function isFinal(): bool
    {
        return $this === self::Validated || $this === self::Collected || $this === self::Cancelled;
    }
}
