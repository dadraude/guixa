<?php

namespace App\Http;

use Guixa\Sales\Application\BasketActions;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class BasketActionsRow
{
    public function __construct(
        public bool $edit,
        public bool $validate,
        public bool $cancel,
        public bool $markCollected,
        public bool $correct,
        public bool $correctCollection,
    ) {}

    public static function from(BasketActions $can): self
    {
        return new self($can->edit, $can->validate, $can->cancel, $can->markCollected, $can->correct, $can->correctCollection);
    }
}
