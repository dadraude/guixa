<?php

namespace Guixa\Ordering\Infrastructure\Membership;

use Guixa\Membership\Application\GetHouseholds;
use Guixa\Membership\Application\HouseholdData;
use Guixa\Ordering\Domain\Households;
use Guixa\Ordering\Domain\ValueObject\HouseholdInfo;

/** Answers Ordering's questions about households through Membership's use cases (A4). */
final class MembershipHouseholds implements Households
{
    public function __construct(private readonly GetHouseholds $getHouseholds) {}

    public function find(array $householdIds): array
    {
        return array_map(
            fn (HouseholdData $household) => new HouseholdInfo($household->id, $household->name, $household->active),
            ($this->getHouseholds)($householdIds),
        );
    }
}
