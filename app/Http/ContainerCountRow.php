<?php

namespace App\Http;

use Guixa\Ordering\Application\ContainerCountData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class ContainerCountRow
{
    /** @param list<HouseholdContainerRow> $households */
    public function __construct(
        public int $total,
        public array $households,
    ) {}

    public static function from(ContainerCountData $data): self
    {
        return new self($data->total, array_map(HouseholdContainerRow::from(...), $data->households));
    }
}
