<?php

namespace App\Http;

use Guixa\Catalog\Application\ProducerData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A producer to pick in a form. */
#[TypeScript]
final readonly class ProducerOptionRow
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function from(ProducerData $producer): self
    {
        return new self($producer->id, $producer->name);
    }
}
