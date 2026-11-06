<?php

namespace App\Http;

use DateTimeZone;
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
            $event->occurredAt->setTimezone(new DateTimeZone(config('guixa.timezone')))->format(DATE_ATOM), $event->reason);
    }
}
