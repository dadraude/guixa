<?php

namespace Guixa\Sales\Application;

/** A source order line with the product snapshot Sales keeps for the weekly base basket. */
final readonly class WeeklyBaseLineData
{
    public function __construct(
        public string $sourceLineId,
        public string $productId,
        public string $name,
        public string $unit,
        public int $priceCents,
        public bool $variable,
        public ?string $containersPerUnit,
        public string $ordered,
        public string $allocated,
    ) {}
}
