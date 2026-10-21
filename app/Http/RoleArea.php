<?php

namespace App\Http;

use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class RoleArea
{
    public function __construct(
        #[LiteralTypeScriptType('Guixa.Shared.Domain.Role')]
        public string $role,
        public string $label,
        public string $href,
    ) {}
}
