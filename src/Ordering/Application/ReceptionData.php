<?php

namespace Guixa\Ordering\Application;

final readonly class ReceptionData
{
    /**
     * @param  list<ReceptionProductData>  $products  by name
     * @param  list<ReceptionOptionData>  $unorderedOptions  the producer's products not in the reception, deactivated ones included, by name
     * @param  bool  $validated  the reception was checked and no received total changed since (R17a)
     * @param  bool  $distributed  the reception is read-only (R18)
     * @param  bool  $canEdit  the actor may change received totals and allocations: it may receive and the reception is not distributed (R6, R18)
     * @param  bool  $canValidate  the actor may settle receptions and this one is not validated or distributed
     * @param  bool  $canDistribute  the actor may settle receptions and this one is validated and not distributed
     * @param  bool  $canReopen  the actor may settle receptions and this one is distributed in a cycle that is not closed
     * @param  bool  $settled  the cycle is settled; pickups continue during a producer reopen (R18)
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
        public bool $closed = false,
        public bool $canEdit = false,
        public bool $canValidate = false,
        public bool $canDistribute = false,
        public bool $canReopen = false,
    ) {}
}
