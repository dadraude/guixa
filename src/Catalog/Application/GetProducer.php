<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\ProducerRepository;

/** Internal query: other contexts use it through their own ports, so it takes no actor. */
final class GetProducer
{
    public function __construct(private readonly ProducerRepository $producers) {}

    public function __invoke(string $producerId): ?ProducerData
    {
        $producer = $this->producers->get($producerId);

        return $producer === null ? null : ProducerData::of($producer);
    }
}
