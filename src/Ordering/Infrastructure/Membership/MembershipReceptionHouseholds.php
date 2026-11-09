<?php

namespace Guixa\Ordering\Infrastructure\Membership;

use Guixa\Membership\Application\ActiveHouseholdData;
use Guixa\Membership\Application\ReadActiveHouseholds;
use Guixa\Ordering\Domain\ReceptionHouseholds;
use Guixa\Ordering\Domain\ValueObject\HouseholdInfo;

/** Answers Ordering's household list through Membership's use case (A4). */
final class MembershipReceptionHouseholds implements ReceptionHouseholds
{
    public function __construct(private readonly ReadActiveHouseholds $readHouseholds) {}

    public function active(): array
    {
        $active = array_filter(($this->readHouseholds)(), fn (ActiveHouseholdData $household) => $household->active);
        usort($active, fn (ActiveHouseholdData $a, ActiveHouseholdData $b) => [mb_strtolower($a->name), $a->id] <=> [mb_strtolower($b->name), $b->id]);

        return array_map(fn (ActiveHouseholdData $household) => new HouseholdInfo($household->id, $household->name, true), $active);
    }
}
