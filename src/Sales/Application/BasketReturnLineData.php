<?php

namespace Guixa\Sales\Application;

use Guixa\Sales\Domain\BasketLine;
use LogicException;

final readonly class BasketReturnLineData
{
    public function __construct(public string $returnItemId, public string $name, public int $amountCents,
        public int $quantity, public int $creditCents) {}

    public static function from(BasketLine $line): self
    {
        $returnItemId = $line->returnItemId ?? throw new LogicException('A return basket line needs a return item.');

        return new self($returnItemId, $line->name, $line->priceCents,
            intdiv($line->quantity->thousandths, 1000), -$line->amountCents());
    }
}
