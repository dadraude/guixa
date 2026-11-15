<?php

namespace Guixa\Sales\Application;

use DateTimeImmutable;
use Guixa\Sales\Domain\BasketCollectionEvent;

final readonly class BasketCollectionEventData
{
    public function __construct(public string $id, public string $type, public string $authorName,
        public DateTimeImmutable $occurredAt, public ?string $reason) {}

    /** @param array<string, string> $authorNames name by account id; an author missing from it shows no name */
    public static function from(BasketCollectionEvent $event, array $authorNames = []): self
    {
        return new self($event->id, $event->type, $authorNames[$event->recordedBy] ?? '', $event->occurredAt, $event->reason);
    }
}
