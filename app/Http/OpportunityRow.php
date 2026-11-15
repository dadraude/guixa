<?php

namespace App\Http;

use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class OpportunityRow
{
    /**
     * @param  list<array{producerName: string, closesAt: string}>  $cutoffs  closing moments in cooperative-local `Y-m-d H:i`
     */
    public function __construct(
        public string $key,
        #[LiteralTypeScriptType('Guixa.Dashboard.Domain.OpportunityKind')]
        public string $kind,
        public ?string $scheduledDate,
        public string $href,
        #[LiteralTypeScriptType('Array<{ producerName: string; closesAt: string }>')]
        public array $cutoffs = [],
    ) {}
}
