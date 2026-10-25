<?php

namespace App\Http;

use Guixa\Stock\Application\ReturnItemData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A return item as the manage pages show it; the amount is in euros, like the form field. */
#[TypeScript]
final readonly class ReturnItemRow
{
    public function __construct(
        public string $id,
        public string $name,
        public string $amount,
        public bool $active,
        public int $amountCents,
    ) {}

    public static function from(ReturnItemData $item): self
    {
        return new self($item->id, $item->name, EuroAmount::format($item->amountCents), $item->active, $item->amountCents);
    }
}
