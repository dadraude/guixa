<?php

namespace Guixa\Ordering\Application;

final readonly class ReceptionData
{
    /**
     * @param  list<ReceptionProductData>  $products  by name
     * @param  list<ReceptionOptionData>  $unorderedOptions  the producer's products not in the reception, deactivated ones included, by name
     * @param  bool  $validated  the reception was checked and no received total changed since (R17a)
     * @param  bool  $distributed  the reception is read-only (R18)
     * @param  bool  $settled  the cycle is settled, so the producer can no longer be reopened for now (R18)
     */
    public function __construct(
        public string $cycleId,
        public string $producerId,
        public string $pickupDate,
        public array $products,
        public array $unorderedOptions,
        public bool $validated,
        public bool $distributed,
        public bool $settled,
    ) {}
}
