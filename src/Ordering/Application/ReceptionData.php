<?php

namespace Guixa\Ordering\Application;

final readonly class ReceptionData
{
    /**
     * @param  list<ReceptionProductData>  $products  by name
     * @param  list<ReceptionOptionData>  $unorderedOptions  the producer's products not in the reception, deactivated ones included, by name
     */
    public function __construct(
        public string $cycleId,
        public string $producerId,
        public string $pickupDate,
        public array $products,
        public array $unorderedOptions,
    ) {}
}
