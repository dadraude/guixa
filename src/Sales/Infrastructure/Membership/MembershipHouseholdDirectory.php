<?php

namespace Guixa\Sales\Infrastructure\Membership;

use Guixa\Membership\Application\ReadActiveHouseholds;
use Guixa\Sales\Domain\HouseholdDirectory;
use Guixa\Sales\Domain\ValueObject\HouseholdSnapshot;

final class MembershipHouseholdDirectory implements HouseholdDirectory
{
    public function __construct(private readonly ReadActiveHouseholds $households) {}

    public function all(): array
    {
        return array_map(fn ($household) => new HouseholdSnapshot($household->id, $household->name,
            $household->active), ($this->households)());
    }
}
