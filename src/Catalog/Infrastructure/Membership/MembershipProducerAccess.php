<?php

namespace Guixa\Catalog\Infrastructure\Membership;

use Guixa\Catalog\Domain\ProducerAccess;
use Guixa\Membership\Application\RestoreProducerRole;
use Guixa\Membership\Application\SuspendProducerRole;

/** Changes the producer role through Membership's use cases (A4). */
final class MembershipProducerAccess implements ProducerAccess
{
    public function __construct(
        private readonly SuspendProducerRole $suspendProducerRole,
        private readonly RestoreProducerRole $restoreProducerRole,
    ) {}

    public function suspend(string $producerId): void
    {
        ($this->suspendProducerRole)($producerId);
    }

    public function restore(string $producerId): void
    {
        ($this->restoreProducerRole)($producerId);
    }
}
