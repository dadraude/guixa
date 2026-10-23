<?php

namespace App\Http;

use Guixa\Ordering\Application\CycleData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A cycle to pick in a form: its id and pickup date (YYYY-MM-DD). */
#[TypeScript]
final readonly class CycleOptionRow
{
    public function __construct(
        public string $id,
        public string $pickupDate,
    ) {}

    public static function from(CycleData $cycle): self
    {
        return new self($cycle->id, $cycle->pickupDate);
    }
}
