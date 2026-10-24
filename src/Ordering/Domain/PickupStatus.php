<?php

namespace Guixa\Ordering\Domain;

/** Where a household's pickup stands (R19, R24); the done states arrive with the pickup tasks. */
enum PickupStatus: string
{
    /** The cycle is not settled yet, or the order has no line. */
    case None = 'none';

    case Pending = 'pending';

    /** Every allocation is 0 and there is nothing else to hand over (R24). */
    case NothingToCollect = 'nothing_to_collect';
}
