<?php

namespace App\Http;

use Guixa\Sales\Application\BasketCollectionEventData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class BasketCollectionEventRow
{
    public function __construct(public string $id, public string $type, public string $recordedBy,
        public string $occurredAt, public ?string $reason) {}

    public static function from(BasketCollectionEventData $event): self
    {
        return new self($event->id, $event->type, $event->recordedBy,
            $event->occurredAt->format(DATE_ATOM), $event->reason);
    }
}
