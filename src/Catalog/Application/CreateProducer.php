<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Producer;
use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Catalog\Domain\ValueObject\CutoffRule;
use Guixa\Shared\Application\Actor;

final class CreateProducer
{
    public function __construct(private readonly ProducerRepository $producers) {}

    /** @return string the new producer's id */
    public function __invoke(Actor $actor, string $name, int $cutoffDaysBefore, string $cutoffTime): string
    {
        $actor->assertManager();

        $producer = Producer::create(
            $this->producers->nextId(),
            $name,
            CutoffRule::of($cutoffDaysBefore, $cutoffTime),
        );
        $this->producers->add($producer);

        return $producer->id;
    }
}
