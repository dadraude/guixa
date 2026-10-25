<?php

namespace Guixa\Ordering\Infrastructure\Wallet;

use Guixa\Ordering\Application\WalletLedger;
use Guixa\Shared\Application\Actor;
use Guixa\Wallet\Application\ChargeForPickup;
use Guixa\Wallet\Application\RecordCorrection;
use Guixa\Wallet\Application\RecordTopUp;
use Illuminate\Contracts\Translation\Translator;

/** Records a pickup's top-up and charge through Wallet's use cases (A4). */
final class WalletModuleLedger implements WalletLedger
{
    public function __construct(
        private readonly RecordTopUp $recordTopUp,
        private readonly ChargeForPickup $chargeForPickup,
        private readonly RecordCorrection $recordCorrection,
        private readonly Translator $translator,
    ) {}

    public function topUp(Actor $actor, string $householdId, int $amountCents): void
    {
        ($this->recordTopUp)($actor, $householdId, $amountCents);
    }

    public function charge(Actor $actor, string $householdId, string $cycleId, int $totalCents): void
    {
        ($this->chargeForPickup)($actor, $householdId, $cycleId, $totalCents);
    }

    public function correct(Actor $actor, string $householdId, string $cycleId, int $amountCents, string $productName): void
    {
        if ($amountCents === 0) {
            return;
        }

        /** @var string $note */
        $note = $this->translator->get('Pickup redistribution: :product', ['product' => $productName]);
        ($this->recordCorrection)($actor, $householdId, $amountCents, $note, $cycleId);
    }
}
