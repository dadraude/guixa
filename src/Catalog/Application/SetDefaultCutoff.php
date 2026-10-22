<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Error\ProducerNotFound;
use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Catalog\Domain\ValueObject\CutoffRule;
use Guixa\Shared\Application\Actor;

final class SetDefaultCutoff
{
    public function __construct(private readonly ProducerRepository $producers) {}

    public function __invoke(Actor $actor, string $producerId, int $daysBefore, string $time): void
    {
        $actor->assertProducerOf($producerId);

        $producer = $this->producers->get($producerId) ?? throw ProducerNotFound::withId($producerId);
        $producer->changeCutoffRule(CutoffRule::of($daysBefore, $time));
        $this->producers->add($producer);
    }
}
