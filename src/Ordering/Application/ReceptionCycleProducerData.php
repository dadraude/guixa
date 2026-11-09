<?php

namespace Guixa\Ordering\Application;

use Guixa\Ordering\Domain\ReceptionState;

final readonly class ReceptionCycleProducerData
{
    public function __construct(
        public string $producerId,
        public string $producerName,
        public ReceptionState $state,
    ) {}
}
