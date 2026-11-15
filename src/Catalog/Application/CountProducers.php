<?php

namespace Guixa\Catalog\Application;

use Guixa\Shared\Application\Actor;

final class CountProducers
{
    public function __construct(private readonly ListProducers $listProducers) {}

    public function __invoke(Actor $actor): ProducerCountsData
    {
        $producers = ($this->listProducers)($actor);

        return new ProducerCountsData(
            count($producers),
            count(array_filter($producers, fn (ProducerData $producer) => $producer->active)),
        );
    }
}
