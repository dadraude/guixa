<?php

namespace Guixa\Ordering\Application;

use Guixa\Shared\Application\Actor;

/**
 * The manager's send. An empty order is left to `SendEmptyProducerOrders`, which also validates and distributes it;
 * `SendProducerOrder` refuses it under the cycle lock, so a change racing the send cannot leave it sent but never
 * settled (R12a, D10).
 */
final class SendProducerOrderManually
{
    public function __construct(private readonly SendProducerOrder $send) {}

    public function __invoke(Actor $actor, string $cycleId, string $producerId): void
    {
        ($this->send)($actor, $cycleId, $producerId, refuseEmpty: true);
    }
}
