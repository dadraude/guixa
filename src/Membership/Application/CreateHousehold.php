<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\Household;
use Guixa\Membership\Domain\HouseholdRepository;
use Guixa\Shared\Application\Actor;

final class CreateHousehold
{
    public function __construct(private readonly HouseholdRepository $households) {}

    /** @return string the new household's id */
    public function __invoke(Actor $actor, string $name): string
    {
        $actor->assertManager();

        $household = Household::create($this->households->nextId(), $name);
        $this->households->save($household);

        return $household->id;
    }
}
