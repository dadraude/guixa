<?php

namespace Tests\Support\Contracts;

use DateTimeImmutable;
use Guixa\Sales\Domain\Basket;
use Guixa\Sales\Domain\BasketAdjustment;
use Guixa\Sales\Domain\BasketAdjustmentLine;
use Guixa\Sales\Domain\BasketCollectionEvent;
use Guixa\Sales\Domain\BasketKind;
use Guixa\Sales\Domain\BasketLine;
use Guixa\Sales\Domain\BasketLineKind;
use Guixa\Sales\Domain\BasketRepository;
use Guixa\Sales\Domain\BasketState;
use Guixa\Sales\Domain\Error\BasketNotEditable;
use Guixa\Shared\Domain\Quantity;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

trait BasketRepositoryContract
{
    /** @return array{string, string} a source order id and its cycle id that weekly baskets can reference */
    abstract protected function weeklySource(): array;

    #[Test]
    public function it_keeps_one_base_and_at_most_one_open_late_draft_per_source_order(): void
    {
        [$repository, $manager, $household] = $this->basketWorld();
        [$orderId, $cycleId] = $this->weeklySource();
        $at = new DateTimeImmutable('2026-11-07T10:00:00+00:00');
        $weekly = fn (string $id, bool $late, BasketState $state = BasketState::Draft) => new Basket($id, $household, 'Household K', $state, null, $at, [],
            validatedBy: $state === BasketState::Validated ? $manager : null, validatedAt: $state === BasketState::Validated ? $at : null,
            validatedTotalCents: $state === BasketState::Validated ? 0 : null,
            kind: BasketKind::Weekly, sourceOrderId: $orderId, referenceCycleId: $cycleId, late: $late);
        $base = $repository->nextId();
        $first = $repository->nextId();
        $repository->save($weekly($base, false));
        $repository->save($weekly($first, true));

        $this->assertSame($base, $repository->getBySourceOrder($orderId)->id);
        $this->assertSame($first, $repository->getOpenLateBySourceOrder($orderId)->id);
        $this->assertTrue($repository->get($first)->late);
        $this->assertRejected(fn () => $repository->save($weekly($repository->nextId(), false)));
        $this->assertRejected(fn () => $repository->save($weekly($repository->nextId(), true)));

        $repository->save($weekly($first, true, BasketState::Validated));
        $this->assertNull($repository->getOpenLateBySourceOrder($orderId));
        $second = $repository->nextId();
        $repository->save($weekly($second, true));
        $this->assertSame($second, $repository->getOpenLateBySourceOrder($orderId)->id);
        $this->assertSame($base, $repository->getBySourceOrder($orderId)->id);
        $this->assertCount(3, $repository->weeklyOfCycle($cycleId));

        // A late draft corrected to zero (nothing to collect) still holds the open slot.
        $repository->save($weekly($second, true, 'nothing_to_collect'));
        $this->assertSame($second, $repository->getOpenLateBySourceOrder($orderId)->id);
        $this->assertRejected(fn () => $repository->save($weekly($repository->nextId(), true)));
    }

    private function assertRejected(callable $write): void
    {
        try {
            $write();
        } catch (Throwable) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('A second base or open late draft for one source order was accepted.');
    }

    #[Test]
    public function it_reloads_same_second_collection_events_in_append_order_regardless_of_identity(): void
    {
        [$repository, $manager, $household, $product] = $this->basketWorld();
        $basket = $this->basket($repository, $manager, $household, []);
        $repository->save($basket->withLines([$this->line($repository, $basket->id, $product)]));
        $instant = new DateTimeImmutable('2026-10-30T12:00:00+00:00');
        $repository->save($repository->get($basket->id)->validated($manager, $instant));
        $repository->save($repository->get($basket->id)->collected($manager, $instant, $repository->nextId()));
        $correction = new BasketCollectionEvent('ffffffff-ffff-4fff-8fff-ffffffffffff', $basket->id,
            'corrected', $manager, $instant, 'Wrong mark');
        $repository->save($repository->get($basket->id)->collectionCorrected($correction));
        $repository->save($repository->get($basket->id)->collected($manager, $instant, $repository->nextId()));

        $reloaded = $repository->get($basket->id);
        $this->assertSame(['collected', 'corrected', 'collected'],
            array_map(fn (BasketCollectionEvent $event) => $event->type, $reloaded->collectionEvents));
        $this->assertSame(BasketState::Collected, $reloaded->state);
        $this->assertEquals($instant, $reloaded->collectedAt);
    }

    #[Test]
    public function it_retains_collection_history_and_rejects_reused_or_removed_event_identities(): void
    {
        [$repository, $manager, $household, $product] = $this->basketWorld();
        $basket = $this->basket($repository, $manager, $household, []);
        $repository->save($basket->withLines([$this->line($repository, $basket->id, $product)]));
        $validated = $repository->get($basket->id)->validated($manager, new DateTimeImmutable('2026-10-30T11:00:00+00:00'));
        $repository->save($validated);
        $firstAt = new DateTimeImmutable('2026-10-30T12:00:00+00:00');
        $repository->save($repository->get($basket->id)->collected($manager, $firstAt, $repository->nextId()));
        $collected = $repository->get($basket->id);
        $this->assertCount(1, $collected->collectionEvents);
        $identity = $repository->nextId();
        $correction = new BasketCollectionEvent($identity, $basket->id, 'corrected', $manager,
            new DateTimeImmutable('2026-10-30T13:00:00+00:00'), 'Wrong mark');
        $repository->save($collected->collectionCorrected($correction));
        $pending = $repository->get($basket->id);
        $this->assertSame(BasketState::Validated, $pending->state);
        $this->assertSame($manager, $pending->collectedBy);
        $this->assertEquals($firstAt, $pending->collectedAt);
        $this->assertSame(['collected', 'corrected'], array_map(fn (BasketCollectionEvent $event) => $event->type, $pending->collectionEvents));
        $this->assertSame('Wrong mark', $pending->collectionEvents[1]->reason);
        try {
            $repository->save($collected);
            $this->fail('Retained correction history cannot be removed.');
        } catch (BasketNotEditable) {
            // Expected append-only boundary.
        }
        $this->assertCount(2, $repository->get($basket->id)->collectionEvents);

        $other = $this->basket($repository, $manager, $household, []);
        $repository->save($other->withLines([$this->line($repository, $other->id, $product)]));
        $repository->save($repository->get($other->id)->validated($manager, $firstAt));
        $repository->save($repository->get($other->id)->collected($manager, $firstAt, $repository->nextId()));
        try {
            $repository->save($repository->get($other->id)->collectionCorrected(new BasketCollectionEvent(
                $identity, $other->id, 'corrected', $manager, new DateTimeImmutable('2026-10-30T14:00:00+00:00'), 'Other')));
            $this->fail('A correction identity cannot be reused for another basket.');
        } catch (BasketNotEditable) {
            // Expected global event identity boundary.
        }
        $this->assertSame(BasketState::Collected, $repository->get($other->id)->state);
    }

    #[Test]
    public function it_appends_ordered_amount_adjustments_without_rewriting_prior_history(): void
    {
        [$repository, $manager, $household, $product] = $this->basketWorld();
        $basket = $this->basket($repository, $manager, $household, []);
        $basket = $basket->withLines([$this->line($repository, $basket->id, $product)]);
        $repository->save($basket);
        $validated = $basket->validated($manager, new DateTimeImmutable('2026-10-30T11:00:00+00:00'));
        $repository->save($validated);
        $first = new BasketAdjustment($repository->nextId(), $basket->id, 200, 'Price correction', $manager,
            new DateTimeImmutable('2026-10-30T12:00:00+00:00'));
        $second = new BasketAdjustment($repository->nextId(), $basket->id, -50, 'Second correction', $manager,
            new DateTimeImmutable('2026-10-30T13:00:00+00:00'));
        $repository->save($validated->withAdjustment($first));
        $repository->save($repository->get($basket->id)->withAdjustment($second));

        $reloaded = $repository->get($basket->id);
        $this->assertSame([$first->id, $second->id], array_map(fn (BasketAdjustment $row) => $row->id, $reloaded->adjustments));
        $this->assertSame(1050, $reloaded->effectiveTotalCents());
        $collectedAt = new DateTimeImmutable('2026-10-30T14:00:00+00:00');
        $repository->save($reloaded->collected($manager, $collectedAt));
        $collected = $repository->get($basket->id);
        $this->assertSame(BasketState::Collected, $collected->state);
        $this->assertSame($manager, $collected->collectedBy);
        $this->assertEquals($collectedAt, $collected->collectedAt);
        $this->assertSame(1200, $collected->validatedTotalCents);
        $this->assertSame(1050, $collected->effectiveTotalCents());
        $this->assertEquals([$first, $second], $collected->adjustments);
        $repository->save($collected->collected($manager, $collectedAt));
        $this->assertEquals([$first, $second], $repository->get($basket->id)->adjustments);
        try {
            $repository->save($validated);
            $this->fail('A saved correction cannot be removed.');
        } catch (BasketNotEditable) {
            // Expected append-only boundary.
        }
        $this->assertCount(2, $repository->get($basket->id)->adjustments);
    }

    #[Test]
    public function it_reloads_signed_quantity_lines_of_an_adjustment_and_rejects_changing_them(): void
    {
        [$repository, $manager, $household, $product] = $this->basketWorld();
        $basket = $this->basket($repository, $manager, $household, []);
        $line = new BasketLine($repository->nextId(), $basket->id, $product, 'Oil', 'bottle', 1200, false, null,
            Quantity::fromString('3'), new DateTimeImmutable('2026-10-30T10:00:00+00:00'));
        $repository->save($basket->withLines([$line]));
        $validated = $repository->get($basket->id)->validated($manager, new DateTimeImmutable('2026-10-30T11:00:00+00:00'));
        $repository->save($validated);
        $adjustment = new BasketAdjustment($repository->nextId(), $basket->id, 1200, 'One came back', $manager,
            new DateTimeImmutable('2026-10-30T12:00:00+00:00'), [new BasketAdjustmentLine($product, -1000)]);
        $repository->save($validated->withAdjustment($adjustment));

        $reloaded = $repository->get($basket->id);
        $this->assertEquals([$adjustment], $reloaded->adjustments);
        $this->assertSame(2000, $reloaded->effectiveQuantity($product));
        $this->assertSame(2400, $reloaded->effectiveTotalCents());
        $this->assertSame('3.000', $reloaded->lines[0]->quantity->toString());
        $this->assertEquals([$adjustment], $repository->getForHousehold($basket->id, $household)->adjustments);

        $changed = new BasketAdjustment($adjustment->id, $basket->id, 1200, 'One came back', $manager,
            $adjustment->occurredAt, [new BasketAdjustmentLine($product, -2000)]);
        try {
            $repository->save(new Basket($reloaded->id, $reloaded->householdId, $reloaded->householdName, $reloaded->state,
                $reloaded->createdBy, $reloaded->createdAt, $reloaded->lines, $reloaded->validatedBy, $reloaded->validatedAt,
                $reloaded->validatedTotalCents, adjustments: [$changed]));
            $this->fail('A saved adjustment cannot change its lines.');
        } catch (BasketNotEditable) {
            // Expected append-only boundary.
        }
        $this->assertSame(2000, $repository->get($basket->id)->effectiveQuantity($product));
    }

    /** @return array{BasketRepository,string,string,string,string,string} repository, manager, household, product, return A, return B */
    abstract protected function basketWorld(): array;

    private function basket(BasketRepository $repository, string $manager, string $household, array $lines): Basket
    {
        return new Basket($repository->nextId(), $household, 'Household K', BasketState::Draft, $manager,
            new DateTimeImmutable('2026-10-30T10:00:00+00:00'), $lines);
    }

    private function line(BasketRepository $repository, string $basketId, string $product, int $price = 1200): BasketLine
    {
        return new BasketLine($repository->nextId(), $basketId, $product, 'Oil', 'bottle', $price,
            false, null, Quantity::fromString('1'), new DateTimeImmutable('2026-10-30T10:00:00+00:00'));
    }

    #[Test]
    public function it_reloads_distinct_return_lines_and_retains_their_snapshots(): void
    {
        [$repository, $manager, $household, , $returnA, $returnB] = $this->basketWorld();
        $basket = $this->basket($repository, $manager, $household, []);
        $created = new DateTimeImmutable('2026-10-30T10:00:00+00:00');
        $first = new BasketLine($repository->nextId(), $basket->id, null, 'Glass jar', 'item', 50,
            false, null, Quantity::fromString('1'), $created, kind: BasketLineKind::Return, returnItemId: $returnA);
        $second = new BasketLine($repository->nextId(), $basket->id, null, 'Bottle', 'item', 25,
            false, null, Quantity::fromString('2'), $created, kind: BasketLineKind::Return, returnItemId: $returnB);

        $repository->save($basket->withLines([$first, $second]));
        $reloaded = $repository->get($basket->id);
        $this->assertCount(2, $reloaded->activeLines());
        $this->assertSame(-100, $reloaded->totalCents());
        $this->assertSame(['Glass jar', 'Bottle'], array_map(fn (BasketLine $line) => $line->name, $reloaded->activeLines()));
    }

    #[Test]
    public function it_reloads_current_lines_and_keeps_archived_product_history(): void
    {
        [$repository, $manager, $household, $product] = $this->basketWorld();
        $basket = $this->basket($repository, $manager, $household, []);
        $first = $this->line($repository, $basket->id, $product);
        $repository->save($basket->withLines([$first]));
        $this->assertSame(1200, $repository->get($basket->id)->activeLineFor($product)->priceCents);

        $archived = $first->removedAt(new DateTimeImmutable('2026-10-30T11:00:00+00:00'));
        $repository->save($basket->withLines([$archived]));
        $this->assertTrue($repository->hasProductHistory($product));
        $this->assertCount(0, $repository->get($basket->id)->activeLines());

        $second = $this->line($repository, $basket->id, $product, 1500);
        $repository->save($basket->withLines([$archived, $second]));
        $this->assertCount(2, $repository->get($basket->id)->lines);
        $this->assertSame(1500, $repository->get($basket->id)->activeLineFor($product)->priceCents);
    }

    #[Test]
    public function it_rejects_two_active_lines_for_one_product(): void
    {
        [$repository, $manager, $household, $product] = $this->basketWorld();
        $basket = $this->basket($repository, $manager, $household, []);
        $first = $this->line($repository, $basket->id, $product);
        $second = $this->line($repository, $basket->id, $product);

        $error = null;
        try {
            $repository->save($basket->withLines([$first, $second]));
        } catch (Throwable $caught) {
            $error = $caught;
        }
        $this->assertNotNull($error, 'Two active lines for one product must be rejected.');
        $this->assertNull($repository->get($basket->id));
    }

    #[Test]
    public function it_retains_the_validated_snapshot_but_allows_first_collection(): void
    {
        [$repository, $manager, $household, $product] = $this->basketWorld();
        $basket = $this->basket($repository, $manager, $household, []);
        $line = $this->line($repository, $basket->id, $product);
        $repository->save($basket->withLines([$line]));
        $validated = $repository->get($basket->id)->validated($manager, new DateTimeImmutable('2026-10-30T12:00:00+00:00'));
        $repository->save($validated);
        $changed = new BasketLine($line->id, $basket->id, $product, 'Changed', 'bottle', 1200,
            false, null, Quantity::fromString('1'), $line->createdAt);
        $rewrite = new Basket($validated->id, $validated->householdId, $validated->householdName,
            BasketState::Validated, $validated->createdBy, $validated->createdAt, [$changed],
            $validated->validatedBy, $validated->validatedAt, $validated->validatedTotalCents);
        $error = null;
        try {
            $repository->save($rewrite);
        } catch (Throwable $caught) {
            $error = $caught;
        }
        $this->assertInstanceOf(BasketNotEditable::class, $error);
        $this->assertSame('Oil', $repository->get($basket->id)->activeLineFor($product)->name);

        $collected = $repository->get($basket->id)->collected($manager, new DateTimeImmutable('2026-10-30T13:00:00+00:00'));
        $repository->save($collected);
        $this->assertSame(BasketState::Collected, $repository->get($basket->id)->state);
        $this->assertSame($manager, $repository->get($basket->id)->collectedBy);
    }

    #[Test]
    public function it_scopes_household_reads_without_exposing_other_household_ids(): void
    {
        [$repository, $manager, $household, $product] = $this->basketWorld();
        $basket = $this->basket($repository, $manager, $household, []);
        $repository->save($basket->withLines([$this->line($repository, $basket->id, $product)]));

        $this->assertSame($basket->id, $repository->getForHousehold($basket->id, $household)?->id);
        $this->assertNull($repository->getForHousehold($basket->id, '00000000-0000-7000-8000-000000000099'));
        $this->assertSame([$basket->id], array_map(fn (Basket $row) => $row->id, $repository->listForHousehold($household)));
        $this->assertSame([], $repository->listForHousehold('00000000-0000-7000-8000-000000000099'));
        $this->assertSame('Oil', $repository->getForHousehold($basket->id, $household)?->activeLines()[0]->name);
    }
}
