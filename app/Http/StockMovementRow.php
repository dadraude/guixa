<?php

namespace App\Http;

use DateTimeImmutable;
use DateTimeZone;
use Guixa\Stock\Application\StockMovementLine;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One line of a stock product's history, dated in the cooperative's local time like the wallet; `basketId` is null for a producer, who never sees baskets. */
#[TypeScript]
final readonly class StockMovementRow
{
    public function __construct(
        public string $id,
        public string $occurredAt,
        public string $type,
        public string $quantity,
        public string $balanceAfter,
        public ?string $note,
        public string $authorName,
        public ?string $cycleId,
        public ?string $pickupDate,
        public ?string $basketId,
    ) {}

    public static function from(StockMovementLine $line): self
    {
        return new self(
            $line->id, (new DateTimeImmutable($line->occurredAt))->setTimezone(new DateTimeZone(config('guixa.timezone')))->format('Y-m-d H:i'), $line->type, $line->quantity, $line->balanceAfter,
            $line->note, $line->authorName, $line->cycleId, $line->pickupDate, $line->basketId,
        );
    }
}
