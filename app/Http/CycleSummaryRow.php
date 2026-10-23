<?php

namespace App\Http;

use Guixa\Ordering\Application\CycleSummaryData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A cycle's totals as the manager's cycle page shows them, producers sorted by name. */
#[TypeScript]
final readonly class CycleSummaryRow
{
    /** @param  list<ProducerTotalsRow>  $producers */
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
        public array $producers,
    ) {}

    /** @param  array<string, string>  $producerNames  by producer id */
    public static function from(CycleSummaryData $summary, array $producerNames): self
    {
        $producers = array_map(
            fn ($producer) => ProducerTotalsRow::from($producer, $producerNames[$producer->producerId] ?? $producer->producerId),
            $summary->producers,
        );
        usort($producers, fn (ProducerTotalsRow $a, ProducerTotalsRow $b) => mb_strtolower($a->producerName) <=> mb_strtolower($b->producerName));

        return new self($summary->cycleId, $summary->pickupDate, $producers);
    }
}
