<?php

namespace App\Http;

use Guixa\Membership\Application\HouseholdDetail;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class HouseholdRow
{
    /**
     * @param  list<MemberRow>  $members
     */
    public function __construct(
        public string $id,
        public string $name,
        public bool $active,
        public array $members,
    ) {}

    public static function from(HouseholdDetail $household): self
    {
        return new self(
            $household->id,
            $household->name,
            $household->active,
            array_map(MemberRow::from(...), $household->members),
        );
    }
}
