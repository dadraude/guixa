<?php

namespace Guixa\Sales\Domain;

use Guixa\Shared\Domain\Quantity;

/** A signed change of one product's effective basket quantity; the original line is never rewritten. */
final readonly class BasketAdjustmentLine
{
    public function __construct(public string $productId, public int $quantityDeltaThousandths) {}

    public static function fromDecimal(string $productId, string $delta): self
    {
        $negative = str_starts_with($delta, '-');
        $thousandths = Quantity::fromString($negative ? substr($delta, 1) : $delta)->thousandths;

        return new self($productId, $negative ? -$thousandths : $thousandths);
    }

    public function quantityDelta(): string
    {
        $magnitude = Quantity::ofThousandths(abs($this->quantityDeltaThousandths))->toString();

        return ($this->quantityDeltaThousandths < 0 ? '-' : '').$magnitude;
    }
}
