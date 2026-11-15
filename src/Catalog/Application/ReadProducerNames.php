<?php

namespace Guixa\Catalog\Application;

use Guixa\Catalog\Domain\Producer;
use Guixa\Catalog\Domain\ProducerRepository;

/** Internal query: other contexts use it through their own ports, so it takes no actor. One read for any number of producers. */
final class ReadProducerNames
{
    public function __construct(private readonly ProducerRepository $producers) {}

    /**
     * @param  list<string>  $producerIds
     * @return array<string, string> name by producer id; unknown producers are left out
     */
    public function __invoke(array $producerIds): array
    {
        $wanted = array_flip($producerIds);
        $names = [];

        foreach ($this->producers->list() as $producer) {
            if (isset($wanted[$producer->id])) {
                $names[$producer->id] = $producer->name();
            }
        }

        return $names;
    }
}
