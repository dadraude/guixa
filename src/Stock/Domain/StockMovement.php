<?php

namespace Guixa\Stock\Domain;

use DateTimeImmutable;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Domain\Error\AdjustmentNoteRequired;
use Guixa\Stock\Domain\Error\InvalidMovementQuantity;
use Guixa\Stock\Domain\ValueObject\SignedQuantity;

/** One line of a stock product's ledger. Never edited or deleted (R28). */
final readonly class StockMovement
{
    private function __construct(
        public string $id,
        public string $productId,
        public MovementType $type,
        public SignedQuantity $quantity,
        public DateTimeImmutable $occurredAt,
        public string $recordedBy,
        public ?string $note,
        public ?string $cycleId,
        public ?string $basketId = null,
        public ?string $correctionId = null,
    ) {}

    public static function entry(
        string $id,
        string $productId,
        Quantity $quantity,
        DateTimeImmutable $occurredAt,
        string $recordedBy,
        ?string $note,
    ): self {
        if ($quantity->isZero()) {
            throw InvalidMovementQuantity::entryMustBePositive();
        }

        return new self(
            $id, $productId, MovementType::Entry, SignedQuantity::fromQuantity($quantity),
            $occurredAt, $recordedBy, self::cleanNote($note), null,
        );
    }

    public static function adjustment(
        string $id,
        string $productId,
        SignedQuantity $quantity,
        DateTimeImmutable $occurredAt,
        string $recordedBy,
        string $note,
        ?string $cycleId,
    ): self {
        if ($quantity->isZero()) {
            throw InvalidMovementQuantity::adjustmentMustNotBeZero();
        }

        $note = self::cleanNote($note);

        if ($note === null) {
            throw AdjustmentNoteRequired::create();
        }

        return new self($id, $productId, MovementType::Adjustment, $quantity, $occurredAt, $recordedBy, $note, $cycleId);
    }

    public static function basketSale(
        string $id,
        string $productId,
        Quantity $quantity,
        DateTimeImmutable $occurredAt,
        string $recordedBy,
        string $basketId,
    ): self {
        if ($quantity->isZero()) {
            throw InvalidMovementQuantity::saleMustBePositive();
        }

        return new self($id, $productId, MovementType::Sale,
            SignedQuantity::fromQuantity($quantity)->negate(), $occurredAt, $recordedBy,
            null, null, $basketId);
    }

    /** A signed correction of a validated basket's goods; the correction id is its own stable source. */
    public static function basketCorrection(
        string $id,
        string $productId,
        SignedQuantity $quantity,
        DateTimeImmutable $occurredAt,
        string $recordedBy,
        string $reason,
        string $basketId,
        string $correctionId,
    ): self {
        if ($quantity->isZero()) {
            throw InvalidMovementQuantity::adjustmentMustNotBeZero();
        }

        $reason = self::cleanNote($reason) ?? throw AdjustmentNoteRequired::create();

        return new self($id, $productId, MovementType::Adjustment, $quantity, $occurredAt, $recordedBy,
            $reason, null, $basketId, $correctionId);
    }

    public static function restore(
        string $id,
        string $productId,
        MovementType $type,
        SignedQuantity $quantity,
        DateTimeImmutable $occurredAt,
        string $recordedBy,
        ?string $note,
        ?string $cycleId,
        ?string $basketId = null,
        ?string $correctionId = null,
    ): self {
        return new self($id, $productId, $type, $quantity, $occurredAt, $recordedBy, $note, $cycleId, $basketId, $correctionId);
    }

    private static function cleanNote(?string $note): ?string
    {
        $note = $note === null ? '' : trim($note);

        return $note === '' ? null : $note;
    }
}
