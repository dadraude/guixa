<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Shared\Application\Actor;

final class ListProducers
{
    public function __construct(private readonly ProducerRepository $producers) {}

    /** @return list<ProducerData> */
    public function __invoke(Actor $actor): array
    {
        $actor->assertManager();

        return array_map(ProducerData::of(...), $this->producers->list());
    }
}
