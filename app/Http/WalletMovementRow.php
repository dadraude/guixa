<?php

namespace App\Http;

use DateTimeZone;
use Guixa\Wallet\Application\MovementData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A wallet movement as the pages show it: the amount is signed euros, like the form fields, and the date is
 * local time in the cooperative's timezone, formatted here so the browser's timezone never shows.
 */
#[TypeScript]
final readonly class WalletMovementRow
{
    public function __construct(
        public string $id,
        public string $type,
        public string $amount,
        public string $occurredAt,
        public ?string $note,
        public ?string $cycleId,
        public ?string $basketId,
        public ?string $sourceMovementId,
        public string $authorName,
    ) {}

    public static function from(MovementData $movement): self
    {
        return new self(
            $movement->id,
            $movement->type,
            EuroAmount::format($movement->amountCents),
            $movement->occurredAt->setTimezone(new DateTimeZone(config('guixa.timezone')))->format('Y-m-d H:i'),
            $movement->note,
            $movement->cycleId,
            $movement->basketId,
            $movement->sourceMovementId,
            $movement->authorName,
        );
    }
}
