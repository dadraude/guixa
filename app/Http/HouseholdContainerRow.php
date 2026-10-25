<?php

namespace App\Http;

use Guixa\Ordering\Application\HouseholdContainerCountData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class HouseholdContainerRow
{
    public function __construct(
        public string $householdId,
        public string $householdName,
        public int $count,
    ) {}

    public static function from(HouseholdContainerCountData $data): self
    {
        return new self($data->householdId, $data->householdName, $data->count);
    }
}
