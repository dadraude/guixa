<?php

namespace App\Http;

use Guixa\Ordering\Application\ProducerCycleSummaryData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A cycle as the producer's page shows it: its own products, and their totals once the order is sent. */
#[TypeScript]
final readonly class ProducerCycleSummaryRow
{
    /** @param  list<ProducerProductTotalRow>  $products */
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
        public bool $sent,
        public array $products,
    ) {}

    public static function from(ProducerCycleSummaryData $summary): self
    {
        return new self(
            $summary->cycleId,
            $summary->pickupDate,
            $summary->sent,
            array_map(ProducerProductTotalRow::from(...), $summary->products),
        );
    }
}
