<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\Error\HouseholdNotFound;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Application\Actor;

final class RenameHousehold
{
    public function __construct(private readonly HouseholdRepository $households) {}

    public function __invoke(Actor $actor, string $householdId, string $name): void
    {
        $actor->assertManager();

        $household = $this->households->get($householdId) ?? throw HouseholdNotFound::withId($householdId);
        $household->rename($name);
        $this->households->save($household);
    }
}
