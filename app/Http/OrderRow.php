<?php

namespace App\Http;

use Guixa\Ordering\Application\HouseholdOrderData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A household's order in a cycle, its producers named and sorted by name; `basketId` links the Sales base basket once the cycle is settled (R23a). */
#[TypeScript]
final readonly class OrderRow
{
    /**
     * @param  list<OrderProducerRow>  $producers
     * @param  list<OrderLineRow>  $lines
     */
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
        public bool $cancelled,
        public array $producers,
        public array $lines,
        public ?string $basketId = null,
    ) {}

    /** @param  array<string, string>  $producerNames  by producer id, those of the participants and of every line */
    public static function from(HouseholdOrderData $order, array $producerNames): self
    {
        $producers = array_map(
            fn ($producer) => OrderProducerRow::from($producer, $producerNames[$producer->producerId] ?? $producer->producerId),
            $order->producers,
        );
        usort($producers, fn (OrderProducerRow $a, OrderProducerRow $b) => mb_strtolower($a->producerName) <=> mb_strtolower($b->producerName));

        return new self($order->cycleId, $order->pickupDate, $order->cancelled, $producers, array_map(fn ($line) => OrderLineRow::from($line, $producerNames[$line->producerId] ?? $line->producerId), $order->lines), $order->basketId);
    }
}
