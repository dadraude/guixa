<?php

namespace Guixa\Wallet\Application;

use Guixa\Shared\Application\Actor;
use Guixa\Shared\Domain\Clock;
use Guixa\Shared\Domain\Money;
use Guixa\Wallet\Domain\Movement;
use Guixa\Wallet\Domain\MovementRepository;

final class RecordCorrection
{
    public function __construct(
        private readonly MovementRepository $movements,
        private readonly Clock $clock,
    ) {}

    /** @return string the new movement's id */
    public function __invoke(Actor $actor, string $householdId, int $amountCents, string $note, ?string $cycleId = null): string
    {
        $actor->assertManager();

        $movement = Movement::correction(
            $this->movements->nextId(),
            $householdId,
            Money::ofCents($amountCents),
            $note,
            $cycleId,
            $this->clock->now(),
            $actor->accountId,
        );
        $this->movements->add($movement);

        return $movement->id;
    }
}
