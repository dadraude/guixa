<?php

namespace Guixa\Sales\Application;

use DateTimeImmutable;
use Guixa\Sales\Domain\BasketCollectionEvent;

final readonly class BasketCollectionEventData
{
    public function __construct(public string $id, public string $type, public string $recordedBy,
        public DateTimeImmutable $occurredAt, public ?string $reason) {}

    public static function from(BasketCollectionEvent $event): self
    {
        return new self($event->id, $event->type, $event->recordedBy, $event->occurredAt, $event->reason);
    }
}
