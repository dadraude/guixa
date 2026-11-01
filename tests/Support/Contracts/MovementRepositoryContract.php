<?php

namespace Tests\Support\Contracts;

use DateTimeImmutable;
use Guixa\Shared\Domain\Money;
use Guixa\Wallet\Domain\Error\BasketAlreadyCharged;
use Guixa\Wallet\Domain\Error\CorrectionIdentityConflict;
use Guixa\Wallet\Domain\Error\PickupAlreadyCharged;
use Guixa\Wallet\Domain\Movement;
use Guixa\Wallet\Domain\MovementRepository;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/** Behaviour every MovementRepository promises; see ProbeNoteRepositoryContract for how it is shared. */
trait MovementRepositoryContract
{
    #[Test]
    public function it_round_trips_a_basket_sourced_correction_without_a_base_charge(): void
    {
        $repository = $this->repository();
        $household = $this->household();
        $basket = $this->basket($household);
        $id = $repository->nextId();
        $movement = Movement::correction($id, $household, Money::ofCents(25), 'Corrected amount', null,
            $this->at('2026-10-22 10:00:00+00'), $this->account(), basketId: $basket);
        $repository->add($movement);
        $repository->add($movement);

        $found = $repository->findById($id);
        $this->assertSame($basket, $found->basketId);
        $this->assertNull($found->sourceMovementId);
        $this->assertSame(25, $repository->balanceOf($household)->cents);
        $this->assertCount(1, $repository->statementOf($household));
        try {
            $repository->add(Movement::correction($id, $household, Money::ofCents(25), 'Changed', null,
                $this->at('2026-10-22 11:00:00+00'), $this->account(), basketId: $basket));
            $this->fail('A basket correction identity cannot change its reason.');
        } catch (CorrectionIdentityConflict) {
            $this->addToAssertionCount(1);
        }
    }

    abstract protected function repository(): MovementRepository;

    /** The household and cycle must exist where the repository needs them to (a foreign key). */
    abstract protected function household(): string;

    abstract protected function account(): string;

    /** Create a basket accepted by this adapter for the given household. */
    abstract protected function basket(string $household): string;

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time);
    }

    private function topUp(MovementRepository $repository, string $household, int $cents, string $time = '2026-10-22 10:00:00+00'): Movement
    {
        return Movement::topUp($repository->nextId(), $household, Money::ofCents($cents), $this->at($time), $this->account(), 'cash');
    }

    #[Test]
    public function it_generates_distinct_uuid_v7_ids(): void
    {
        $repository = $this->repository();

        $first = $repository->nextId();

        $this->assertNotSame($first, $repository->nextId());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $first);
    }

    #[Test]
    public function it_returns_what_was_added_with_every_field(): void
    {
        $repository = $this->repository();
        $household = $this->household();
        $correction = Movement::correction($repository->nextId(), $household, Money::ofCents(-250), 'duplicated', (string) Str::uuid7(), $this->at('2026-10-22 10:00:00+00'), $this->account());
        $repository->add($correction);

        $found = $repository->statementOf($household)[0];

        $this->assertSame($correction->id, $found->id);
        $this->assertSame($household, $found->householdId);
        $this->assertSame($correction->type, $found->type);
        $this->assertSame(-250, $found->amount->cents);
        $this->assertEquals($correction->occurredAt, $found->occurredAt);
        $this->assertSame($this->account(), $found->recordedBy);
        $this->assertSame('duplicated', $found->note);
        $this->assertSame($correction->cycleId, $found->cycleId);
    }

    #[Test]
    public function it_replays_one_correction_identity_once_and_rejects_changed_contents(): void
    {
        $repository = $this->repository();
        $household = $this->household();
        $receipt = $this->topUp($repository, $household, 5000);
        $repository->add($receipt);
        $id = $repository->nextId();
        $correction = Movement::correction($id, $household, Money::ofCents(-5000), 'duplicate', null, $this->at('2026-10-22 11:00:00+00'), $this->account(), $receipt->id);
        $repository->add($correction);
        $repository->add($correction);

        $this->assertCount(2, $repository->statementOf($household));
        $recorded = $repository->findById($id);
        $this->assertEquals($correction->occurredAt, $recorded->occurredAt);
        $this->assertSame($correction->recordedBy, $recorded->recordedBy);
        $this->assertSame(0, $repository->balanceOf($household)->cents);
        try {
            $repository->add(Movement::correction($id, $household, Money::ofCents(-3000), 'changed', null, $this->at('2026-10-22 12:00:00+00'), $this->account(), $receipt->id));
            $this->fail('A changed correction reused an existing identity.');
        } catch (CorrectionIdentityConflict) {
            $this->addToAssertionCount(1);
        }
        $this->assertCount(2, $repository->statementOf($household));
    }

    #[Test]
    public function it_sums_signed_amounts_into_the_balance_which_may_be_negative(): void
    {
        $repository = $this->repository();
        $household = $this->household();

        $this->assertSame(0, $repository->balanceOf($household)->cents);

        $repository->add($this->topUp($repository, $household, 5000));
        $repository->add(Movement::charge($repository->nextId(), $household, Money::ofCents(7000), (string) Str::uuid7(), $this->at('2026-10-22 11:00:00+00'), $this->account()));

        $this->assertSame(-2000, $repository->balanceOf($household)->cents);
    }

    #[Test]
    public function it_keeps_each_household_balance_apart(): void
    {
        $repository = $this->repository();
        $household = $this->household();
        $other = $this->household();

        $repository->add($this->topUp($repository, $household, 5000));
        $repository->add($this->topUp($repository, $other, 300));

        $this->assertSame(5000, $repository->balanceOf($household)->cents);
        $this->assertCount(1, $repository->statementOf($other));
    }

    #[Test]
    public function it_lists_the_statement_newest_first(): void
    {
        $repository = $this->repository();
        $household = $this->household();
        $oldest = $this->topUp($repository, $household, 100, '2026-10-20 10:00:00+00');
        $newest = $this->topUp($repository, $household, 300, '2026-10-22 10:00:00+00');
        $middle = $this->topUp($repository, $household, 200, '2026-10-21 10:00:00+00');
        foreach ([$oldest, $newest, $middle] as $movement) {
            $repository->add($movement);
        }

        $amounts = array_map(fn (Movement $movement) => $movement->amount->cents, $repository->statementOf($household));

        $this->assertSame([300, 200, 100], $amounts);
    }

    #[Test]
    public function it_rejects_a_second_charge_or_credit_for_the_same_household_and_cycle(): void
    {
        $repository = $this->repository();
        $household = $this->household();
        $cycle = (string) Str::uuid7();
        $repository->add(Movement::charge($repository->nextId(), $household, Money::ofCents(1000), $cycle, $this->at('2026-10-22 10:00:00+00'), $this->account()));

        try {
            $repository->add(Movement::credit($repository->nextId(), $household, Money::ofCents(50), $cycle, $this->at('2026-10-22 10:01:00+00'), $this->account()));
            $this->fail('A second credit for the same household and cycle was accepted.');
        } catch (PickupAlreadyCharged) {
            $this->addToAssertionCount(1);
        }

        $this->assertCount(1, $repository->statementOf($household));
    }

    #[Test]
    public function it_allows_a_charge_in_another_cycle_and_corrections_in_the_same_cycle(): void
    {
        $repository = $this->repository();
        $household = $this->household();
        $cycle = (string) Str::uuid7();
        $repository->add(Movement::charge($repository->nextId(), $household, Money::ofCents(1000), $cycle, $this->at('2026-10-22 10:00:00+00'), $this->account()));

        $repository->add(Movement::charge($repository->nextId(), $household, Money::ofCents(1000), (string) Str::uuid7(), $this->at('2026-10-29 10:00:00+00'), $this->account()));
        $repository->add(Movement::correction($repository->nextId(), $household, Money::ofCents(100), 'typo', $cycle, $this->at('2026-10-22 12:00:00+00'), $this->account()));

        $this->assertCount(3, $repository->statementOf($household));
    }

    #[Test]
    public function it_keeps_distinct_basket_charges_and_legacy_cycle_charges_together(): void
    {
        $repository = $this->repository();
        $household = $this->household();
        $first = $this->basket($household);
        $second = $this->basket($household);
        $cycle = (string) Str::uuid7();
        $repository->add(Movement::basketCharge($repository->nextId(), $household, Money::ofCents(300), $first, $this->at('2026-10-22 10:00:00+00'), $this->account()));
        $repository->add(Movement::basketCharge($repository->nextId(), $household, Money::ofCents(200), $second, $this->at('2026-10-22 11:00:00+00'), $this->account()));
        $repository->add(Movement::charge($repository->nextId(), $household, Money::ofCents(100), $cycle, $this->at('2026-10-22 12:00:00+00'), $this->account()));

        $this->assertSame(-600, $repository->balanceOf($household)->cents);
        $this->assertSame([$cycle, null, null], array_map(fn (Movement $movement) => $movement->cycleId, $repository->statementOf($household)));
        $this->assertSame([null, $second, $first], array_map(fn (Movement $movement) => $movement->basketId, $repository->statementOf($household)));
    }

    #[Test]
    public function it_rejects_a_second_basket_charge_or_credit_globally_across_households(): void
    {
        $repository = $this->repository();
        $owner = $this->household();
        $other = $this->household();
        $basket = $this->basket($owner);
        $repository->add(Movement::basketCharge($repository->nextId(), $owner, Money::ofCents(300), $basket, $this->at('2026-10-22 10:00:00+00'), $this->account()));

        try {
            $repository->add(Movement::basketCredit($repository->nextId(), $other, Money::ofCents(300), $basket, $this->at('2026-10-22 11:00:00+00'), $this->account()));
            $this->fail('A second movement for the same basket was accepted.');
        } catch (BasketAlreadyCharged) {
            $this->addToAssertionCount(1);
        }

        $this->assertCount(1, $repository->statementOf($owner));
        $this->assertCount(0, $repository->statementOf($other));
    }
}
