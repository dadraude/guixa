<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketLine;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use LogicException;

final readonly class BasketLineData
{
    public function __construct(public string $productId, public string $name, public string $unit,
        public string $quantity, public int $priceCents, public int $amountCents,
        public string $effectiveQuantity, public int $effectiveAmountCents) {}

    public static function from(BasketLine $line, int $effectiveThousandths): self
    {
        $productId = $line->productId ?? throw new LogicException('A stock basket line needs a product.');
        $effective = Quantity::ofThousandths($effectiveThousandths);

        return new self($productId, $line->name, $line->unit, $line->quantity->toString(),
            $line->priceCents, $line->amountCents(),
            $effective->toString(), Money::ofCents($line->priceCents)->multiply($effective)->cents);
    }
}
