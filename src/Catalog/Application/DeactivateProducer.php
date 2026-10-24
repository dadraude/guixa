<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProducerNotFound;
use Guixa\Catalog\Domain\ProducerAccess;
use Guixa\Catalog\Domain\ProducerCycles;
use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Shared\Application\Actor;
use Guixa\Shared\Application\TransactionRunner;

final class DeactivateProducer
{
    public function __construct(
        private readonly ProducerRepository $producers,
        private readonly ProducerAccess $access,
        private readonly ProducerCycles $cycles,
        private readonly TransactionRunner $transactions,
    ) {}

    /** The producer loses its role and stops participating in every cycle where its order is not sent (R3a, R6c, D13). */
    public function __invoke(Actor $actor, string $producerId): void
    {
        $actor->assertManager();

        $producer = $this->producers->get($producerId) ?? throw ProducerNotFound::withId($producerId);

        $this->transactions->run(function () use ($producer): void {
            $producer->deactivate();
            $this->producers->add($producer);
            $this->access->suspend($producer->id);
            $this->cycles->stopParticipatingWhereNotSent($producer->id);
        });
    }
}
