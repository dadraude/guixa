<?php

namespace Guixa\Wallet\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Money;
use Guixa\Wallet\Domain\Error\CorrectionIdentityConflict;
use Guixa\Wallet\Domain\Error\CorrectionIdentityRequired;
use Guixa\Wallet\Domain\Error\InvalidCorrectionSource;
use Guixa\Wallet\Domain\Movement;
use Guixa\Wallet\Domain\MovementRepository;
use Guixa\Wallet\Domain\MovementType;
use Illuminate\Support\Facades\Gate;

final class RecordCorrection
{
    public function __construct(
        private readonly MovementRepository $movements,
        private readonly Clock $clock,
    ) {}

    /** @return string the new movement's id */
    public function __invoke(Actor $actor, string $householdId, int $amountCents, string $note, ?string $cycleId = null, ?string $sourceMovementId = null, ?string $correctionId = null): string
    {
        Gate::forUser($actor)->authorize($sourceMovementId === null && $cycleId !== null ? 'manage' : 'manage-wallet');

        $movement = Movement::correction(
            $correctionId ?? $this->movements->nextId(),
            $householdId,
            Money::ofCents($amountCents),
            $note,
            $cycleId,
            $this->clock->now(),
            $actor->accountId,
            $sourceMovementId,
        );
        // Only the Manager's existing weekly redistribution path may omit a receipt source.
        if ($sourceMovementId === null && $cycleId === null) {
            throw InvalidCorrectionSource::create();
        }

        if ($sourceMovementId !== null) {
            if ($correctionId === null) {
                throw CorrectionIdentityRequired::create();
            }

            $source = $this->movements->findById($sourceMovementId);
            if ($source === null || $source->householdId !== $householdId || $source->type !== MovementType::TopUp) {
                throw InvalidCorrectionSource::create();
            }
        }

        $existing = $this->movements->findById($movement->id);
        if ($existing !== null) {
            if (! $existing->matchesCorrectionReplay($movement)) {
                throw CorrectionIdentityConflict::create();
            }

            return $existing->id;
        }

        $this->movements->add($movement);

        return $movement->id;
    }
}
