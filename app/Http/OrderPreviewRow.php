<?php

namespace App\Http;

use Guixa\Ordering\Application\OrderPreviewData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class OrderPreviewRow
{
    /** @param  array<string, int>  $amountCents  by product id */
    public function __construct(public int $totalCents, public array $amountCents) {}

    public static function from(OrderPreviewData $preview): self
    {
        return new self($preview->totalCents, $preview->amountCents);
    }
}
