<?php

namespace Guixa\Wallet\Domain;

use DateTimeImmutable;
use Guixa\Shared\Domain\Money;
use Guixa\Wallet\Domain\Error\CorrectionNoteRequired;
use Guixa\Wallet\Domain\Error\InvalidChargeAmount;
use Guixa\Wallet\Domain\Error\InvalidCorrectionAmount;
use Guixa\Wallet\Domain\Error\InvalidTopUpAmount;

/**
 * One line of a household's wallet. The amount is signed so that the balance is a plain sum:
 * top-ups and credits are positive, charges negative, corrections either (R29, R30, R32).
 * A movement is never edited or deleted.
 */
final readonly class Movement
{
    private function __construct(
        public string $id,
        public string $householdId,
        public MovementType $type,
        public Money $amount,
        public DateTimeImmutable $occurredAt,
        public string $recordedBy,
        public ?string $note,
        public ?string $cycleId,
        public ?string $basketId = null,
        public ?string $sourceMovementId = null,
    ) {}

    public static function topUp(string $id, string $householdId, Money $amount, DateTimeImmutable $occurredAt, string $recordedBy, ?string $note): self
    {
        if ($amount->cents <= 0) {
            throw InvalidTopUpAmount::create();
        }

        return new self($id, $householdId, MovementType::TopUp, $amount, $occurredAt, $recordedBy, self::cleanNote($note), null);
    }

    /** @param Money $total what the household owes for the cycle, greater than zero */
    public static function charge(string $id, string $householdId, Money $total, string $cycleId, DateTimeImmutable $occurredAt, string $recordedBy): self
    {
        if ($total->cents <= 0) {
            throw InvalidChargeAmount::create();
        }

        return new self($id, $householdId, MovementType::Charge, $total->negate(), $occurredAt, $recordedBy, null, $cycleId);
    }

    /** @param Money $total what the household is owed for the cycle (returned containers), greater than zero */
    public static function credit(string $id, string $householdId, Money $total, string $cycleId, DateTimeImmutable $occurredAt, string $recordedBy): self
    {
        if ($total->cents <= 0) {
            throw InvalidChargeAmount::create();
        }

        return new self($id, $householdId, MovementType::Credit, $total, $occurredAt, $recordedBy, null, $cycleId);
    }

    public static function basketCharge(string $id, string $householdId, Money $total, string $basketId, DateTimeImmutable $occurredAt, string $recordedBy): self
    {
        if ($total->cents <= 0) {
            throw InvalidChargeAmount::create();
        }

        return new self($id, $householdId, MovementType::Charge, $total->negate(), $occurredAt, $recordedBy, null, null, $basketId);
    }

    public static function basketCredit(string $id, string $householdId, Money $total, string $basketId, DateTimeImmutable $occurredAt, string $recordedBy): self
    {
        if ($total->cents <= 0) {
            throw InvalidChargeAmount::create();
        }

        return new self($id, $householdId, MovementType::Credit, $total, $occurredAt, $recordedBy, null, null, $basketId);
    }

    public static function correction(string $id, string $householdId, Money $amount, string $note, ?string $cycleId, DateTimeImmutable $occurredAt, string $recordedBy, ?string $sourceMovementId = null, ?string $basketId = null): self
    {
        if ($amount->cents === 0) {
            throw InvalidCorrectionAmount::create();
        }

        $note = self::cleanNote($note);

        if ($note === null) {
            throw CorrectionNoteRequired::create();
        }

        return new self($id, $householdId, MovementType::Correction, $amount, $occurredAt, $recordedBy, $note, $cycleId, $basketId, $sourceMovementId);
    }

    public static function restore(string $id, string $householdId, MovementType $type, Money $amount, DateTimeImmutable $occurredAt, string $recordedBy, ?string $note, ?string $cycleId, ?string $basketId = null, ?string $sourceMovementId = null): self
    {
        return new self($id, $householdId, $type, $amount, $occurredAt, $recordedBy, $note, $cycleId, $basketId, $sourceMovementId);
    }

    public function matchesCorrectionReplay(self $other): bool
    {
        return $this->type === MovementType::Correction
            && $other->type === MovementType::Correction
            && $this->id === $other->id
            && $this->householdId === $other->householdId
            && $this->amount->cents === $other->amount->cents
            && $this->note === $other->note
            && $this->cycleId === $other->cycleId
            && $this->sourceMovementId === $other->sourceMovementId
            && $this->basketId === $other->basketId
            && $this->recordedBy === $other->recordedBy;
    }

    public function isChargeOrCredit(): bool
    {
        return $this->type === MovementType::Charge || $this->type === MovementType::Credit;
    }

    private static function cleanNote(?string $note): ?string
    {
        $note = $note === null ? '' : trim($note);

        return $note === '' ? null : $note;
    }
}
