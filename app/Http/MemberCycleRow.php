<?php

namespace App\Http;

use Guixa\Ordering\Application\MemberCycleData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A cycle in the member's list: open while a participating producer is, with how many lines the household has in it. */
#[TypeScript]
final readonly class MemberCycleRow
{
    public function __construct(
        public string $cycleId,
        public string $pickupDate,
        public bool $open,
        public bool $cancelled,
        public int $lineCount,
    ) {}

    public static function from(MemberCycleData $cycle): self
    {
        return new self($cycle->cycleId, $cycle->pickupDate, $cycle->open, $cycle->cancelled, $cycle->lineCount);
    }
}
