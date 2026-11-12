<?php

namespace Guixa\Dashboard\Domain;

enum ActionKind: string
{
    case SendOrder = 'send_order';
    case CheckDeliveryAndAllocations = 'check_delivery_and_allocations';
    case ResolveAllocations = 'resolve_allocations';
    case MarkDistributed = 'mark_distributed';
    case PrepareBasket = 'prepare_basket';
    case ValidateBasket = 'validate_basket';
    case CollectBasket = 'collect_basket';

    /** Stage among groups with the same date: sending, reception, basket work, collection (R13). Sub-actions of one stage share it. */
    public function stage(): int
    {
        return match ($this) {
            self::SendOrder => 1,
            self::CheckDeliveryAndAllocations, self::ResolveAllocations, self::MarkDistributed => 2,
            self::PrepareBasket, self::ValidateBasket => 3,
            self::CollectBasket => 4,
        };
    }
}
