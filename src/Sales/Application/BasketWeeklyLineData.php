<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketLine;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;

/** A weekly product of a base or late weekly basket: what was ordered, allocated, really taken (actual) and what stands after corrections. */
final readonly class BasketWeeklyLineData
{
    public function __construct(public string $productId, public string $name, public string $unit,
        public string $ordered, public string $allocated, public string $quantity, public int $priceCents,
        public int $amountCents, public string $effectiveQuantity, public int $effectiveAmountCents, public bool $differsFromAllocation) {}

    public static function from(BasketLine $line, int $effectiveThousandths): self
    {
        $effective = Quantity::ofThousandths($effectiveThousandths);
        $allocated = $line->allocatedQuantity ?? $line->quantity;

        return new self((string) $line->productId, $line->name, $line->unit,
            ($line->orderedQuantity ?? $line->quantity)->toString(), $allocated->toString(), $line->quantity->toString(),
            $line->priceCents, $line->amountCents(), $effective->toString(),
            Money::ofCents($line->priceCents)->multiply($effective)->cents, ! $line->quantity->equals($allocated));
    }
}
