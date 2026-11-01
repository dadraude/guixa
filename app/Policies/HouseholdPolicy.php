<?php

namespace App\Policies;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;

final class HouseholdPolicy
{
    public function memberOf(Actor $actor, string $householdId): bool
    {
        return $actor->hasRole(Role::Member) && $actor->householdId === $householdId;
    }

    public function collectFor(Actor $actor, string $householdId): bool
    {
        return $actor->hasAnyRole(Role::Manager->value, Role::Finance->value)
            || $this->memberOf($actor, $householdId);
    }
}
