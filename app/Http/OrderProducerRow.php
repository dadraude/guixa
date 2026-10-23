<?php

namespace App\Http;

use Guixa\Ordering\Application\OrderProducerData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A producer taking part in the cycle, named, with whether members can still order from it. */
#[TypeScript]
final readonly class OrderProducerRow
{
    /** @param  list<OfferedProductRow>  $products */
    public function __construct(
        public string $producerId,
        public string $producerName,
        public bool $open,
        public array $products,
    ) {}

    public static function from(OrderProducerData $producer, string $producerName): self
    {
        return new self(
            $producer->producerId,
            $producerName,
            $producer->open,
            array_map(OfferedProductRow::from(...), $producer->products),
        );
    }
}
