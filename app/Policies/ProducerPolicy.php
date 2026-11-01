<?php

namespace App\Policies;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Role;

final class ProducerPolicy
{
    public function owns(Actor $actor, string $producerId): bool
    {
        return $actor->hasRole(Role::Producer) && $actor->producerId === $producerId;
    }
}
