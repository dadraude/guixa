<?php

namespace Guixa\Dashboard\Domain;

/** What an opportunity offers; none of them is pending work (R11). */
enum OpportunityKind: string
{
    case MemberOrder = 'member_order';
    case ProducerSummary = 'producer_summary';
    case CatalogueMaintenance = 'catalogue_maintenance';

    /** Fixed order among opportunities, separate from pending work (R13). */
    public function rank(): int
    {
        return match ($this) {
            self::MemberOrder => 10,
            self::ProducerSummary => 20,
            self::CatalogueMaintenance => 30,
        };
    }
}
