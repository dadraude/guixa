<?php

namespace App\Http;

use Guixa\Ordering\Application\ReceptionOptionData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A product of the producer that can be added to the reception. */
#[TypeScript]
final readonly class ReceptionOptionRow
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $unit,
        public bool $variable,
    ) {}

    public static function from(ReceptionOptionData $option): self
    {
        return new self($option->productId, $option->name, $option->unit, $option->variable);
    }
}
