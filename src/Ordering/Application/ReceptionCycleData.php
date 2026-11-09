<?php

namespace Guixa\Ordering\Application;

final readonly class ReceptionCycleData
{
    /** @param  list<ReceptionCycleProducerData>  $producers  the producer orders already sent */
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
        public bool $closed,
        public array $producers,
    ) {}
}
