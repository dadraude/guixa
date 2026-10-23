<?php

namespace App\Http;

use Guixa\Catalog\Application\CutoffRuleData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class CutoffRuleRow
{
    public function __construct(
        public int $daysBefore,
        public string $time,
    ) {}

    public static function from(CutoffRuleData $rule): self
    {
        return new self($rule->daysBefore, $rule->time);
    }
}
