<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProducerNotFound;
use Guixa\Catalog\Domain\ProducerAccess;
use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;
use Illuminate\Support\Facades\Gate;

final class ReactivateProducer
{
    public function __construct(
        private readonly ProducerRepository $producers,
        private readonly ProducerAccess $access,
        private readonly TransactionRunner $transactions,
    ) {}

    /** Restores the role only; the cycles it left are not rejoined (R3a, D13). */
    public function __invoke(Actor $actor, string $producerId): void
    {
        Gate::forUser($actor)->authorize('manage');

        $producer = $this->producers->get($producerId) ?? throw ProducerNotFound::withId($producerId);

        $this->transactions->run(function () use ($producer): void {
            $producer->reactivate();
            $this->producers->add($producer);
            $this->access->restore($producer->id);
        });
    }
}
