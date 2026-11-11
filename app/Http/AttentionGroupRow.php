<?php

namespace App\Http;

use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class AttentionGroupRow
{
    public function __construct(
        public string $key,
        #[LiteralTypeScriptType('Guixa.Dashboard.Domain.ActionKind')]
        public string $action,
        public ?string $cycleId,
        public ?string $scheduledDate,
        public int $count,
        public string $href,
    ) {}
}
