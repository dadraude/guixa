<?php

namespace App\Http;

use Guixa\Ordering\Application\ProducerTotalsData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** What was ordered from one producer in a cycle, the producer named. */
#[TypeScript]
final readonly class ProducerTotalsRow
{
    /** @param  list<ProductTotalRow>  $products */
    public function __construct(
        public string $producerId,
        public string $producerName,
        public array $products,
    ) {}

    public static function from(ProducerTotalsData $producer, string $producerName): self
    {
        return new self($producer->producerId, $producerName, array_map(ProductTotalRow::from(...), $producer->products));
    }
}
