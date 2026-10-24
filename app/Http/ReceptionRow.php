<?php

namespace App\Http;

use Guixa\Ordering\Application\ReceptionData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A producer's reception in a cycle, as the manager's reception page shows it. */
#[TypeScript]
final readonly class ReceptionRow
{
    /**
     * @param  list<ReceptionProductRow>  $products
     * @param  list<ReceptionOptionRow>  $unorderedOptions
     */
    public function __construct(
        public string $cycleId,
        public string $producerId,
        public string $producerName,
        public string $pickupDate,
        public array $products,
        public array $unorderedOptions,
    ) {}

    public static function from(ReceptionData $reception, string $producerName): self
    {
        return new self(
            $reception->cycleId,
            $reception->producerId,
            $producerName,
            $reception->pickupDate,
            array_map(ReceptionProductRow::from(...), $reception->products),
            array_map(ReceptionOptionRow::from(...), $reception->unorderedOptions),
        );
    }
}
