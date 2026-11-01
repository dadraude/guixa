<?php

namespace App\Http;

use Guixa\Sales\Application\BasketReturnLineData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class BasketReturnLineRow
{
    public function __construct(public string $returnItemId, public string $name, public int $amountCents,
        public int $quantity, public int $creditCents) {}

    public static function from(BasketReturnLineData $line): self
    {
        return new self($line->returnItemId, $line->name, $line->amountCents, $line->quantity, $line->creditCents);
    }
}
