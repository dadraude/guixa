<?php

namespace Guixa\Dashboard\Domain;

enum ActionKind: string
{
    case CheckDeliveryAndAllocations = 'check_delivery_and_allocations';
    case ResolveAllocations = 'resolve_allocations';
    case MarkDistributed = 'mark_distributed';
    case PrepareBasket = 'prepare_basket';
    case ValidateBasket = 'validate_basket';
    case CollectBasket = 'collect_basket';

    /** Stage order among groups with the same date: reception before basket work (R13). */
    public function rank(): int
    {
        return match ($this) {
            self::CheckDeliveryAndAllocations => 20,
            self::ResolveAllocations => 21,
            self::MarkDistributed => 22,
            self::PrepareBasket => 30,
            self::ValidateBasket => 31,
            self::CollectBasket => 40,
        };
    }
}
