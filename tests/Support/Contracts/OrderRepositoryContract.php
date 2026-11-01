<?php

namespace Tests\Support\Contracts;

use DateTimeImmutable;
use DateTimeZone;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\Order;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\PickupStatus;
use Guixa\Ordering\Domain\ValueObject\CutoffRule;
use Guixa\Ordering\Domain\ValueObject\OrderableProduct;
use Guixa\Ordering\Domain\ValueObject\PickupWeekday;
use Guixa\Ordering\Domain\ValueObject\ReturnItemOffer;
use Guixa\Ordering\Domain\ValueObject\StockOffer;
use Guixa\Shared\Domain\Money;
use Guixa\Shared\Domain\Quantity;
use PHPUnit\Framework\Attributes\Test;

/** Behaviour every OrderRepository promises; see ProbeNoteRepositoryContract for how it is shared. */
trait OrderRepositoryContract
{
    abstract protected function repository(): OrderRepository;

    private const CYCLE = '00000000-0000-7000-8000-0000000000c1';

    private const HOUSEHOLD = '00000000-0000-7000-8000-0000000000e1';

    private const OTHER_HOUSEHOLD = '00000000-0000-7000-8000-0000000000e2';

    private const BAKER = '00000000-0000-7000-8000-0000000000b1';

    private const BREAD = '00000000-0000-7000-8000-0000000000f1';

    private const APPLES = '00000000-0000-7000-8000-0000000000f2';

    private const HONEY = '00000000-0000-7000-8000-0000000000f3';

    private const CHEESE = '00000000-0000-7000-8000-0000000000f5';

    private const JAR = '00000000-0000-7000-8000-0000000000f4';

    private const ANNA = '00000000-0000-7000-8000-0000000000a1';

    /** Lets an adapter that enforces foreign keys create the rows the orders refer to. */
    protected function referencedRowsExist(): void {}

    private function madrid(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('Europe/Madrid'));
    }

    private function openCycle(): Cycle
    {
        $this->referencedRowsExist();
        $cycle = Cycle::open(self::CYCLE, $this->madrid('2026-11-07'), PickupWeekday::of(6), $this->madrid('2026-10-22 10:00'));
        $cycle->addParticipant(self::BAKER, CutoffRule::of(3, '20:00'), $this->madrid('2026-10-22 10:00'));

        return $cycle;
    }

    private function product(string $id, string $name, int $cents, ?string $containers = null, bool $variable = false): OrderableProduct
    {
        return new OrderableProduct(
            $id, self::BAKER, $name, 'unit', Money::ofCents($cents), $variable,
            $containers === null ? null : Quantity::fromString($containers),
        );
    }

    #[Test]
    public function it_stores_the_not_delivered_mark_of_lines_and_keeps_it_when_the_order_is_added_again(): void
    {
        $repository = $this->repository();
        $cycle = $this->openCycle();
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        foreach ([[self::BREAD, 'Loaf'], [self::APPLES, 'Apples']] as [$id, $name]) {
            $order->setLine($this->product($id, $name, 350), Quantity::fromString('2'), $cycle, $repository->nextId(), self::ANNA, $this->madrid('2026-10-22 10:00'));
        }
        $repository->add($order);
        $this->assertSame([], $repository->notDeliveredProductIds($order->id));

        $repository->markNotDelivered($order->id, [self::APPLES, '00000000-0000-7000-8000-0000000000ff']);
        $repository->add($repository->ofHousehold(self::CYCLE, self::HOUSEHOLD));

        $this->assertSame([self::APPLES], $repository->notDeliveredProductIds($order->id));
    }

    #[Test]
    public function it_retains_removed_line_use_and_readds_with_a_new_identity_and_time(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $firstTime = $this->madrid('2026-10-22 10:00');
        $secondTime = $this->madrid('2026-10-22 10:01');
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine($this->product(self::BREAD, 'Loaf', 350), Quantity::fromString('2'), $cycle, $repository->nextId(), self::ANNA, $firstTime);
        $repository->add($order);
        $firstId = $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD)->lines()[0]->id;
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->removeLine(self::BREAD, $cycle, $secondTime);
        $repository->add($order);

        $this->assertSame([], $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD)->lines());
        $this->assertTrue($repository->hasWeeklyReference(self::BREAD));
        $this->assertFalse($repository->hasUnsettledLinesOf(self::BREAD));

        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine($this->product(self::BREAD, 'Loaf', 350), Quantity::fromString('1'), $cycle, $repository->nextId(), self::ANNA, $secondTime);
        $repository->add($order);
        $newLine = $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD)->lines()[0];
        $this->assertNotSame($firstId, $newLine->id);
        $this->assertSame($secondTime->getTimestamp(), $newLine->createdAt->getTimestamp());
    }

    #[Test]
    public function it_retains_cancelled_cycle_references_without_current_orders(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine($this->product(self::BREAD, 'Loaf', 350), Quantity::fromString('1'), $cycle, $repository->nextId(), self::ANNA, $this->madrid('2026-10-22 10:00'));
        $repository->add($order);
        $repository->deleteOfCycle(self::CYCLE);

        $this->assertNull($repository->ofHousehold(self::CYCLE, self::HOUSEHOLD));
        $this->assertSame([], $repository->ofCycle(self::CYCLE));
        $this->assertSame([], $repository->lineCountsOfHousehold(self::HOUSEHOLD));
        $this->assertFalse($repository->hasUnsettledLinesOf(self::BREAD));
        $this->assertTrue($repository->hasWeeklyReference(self::BREAD));
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
    public function it_has_no_order_for_a_household_that_never_ordered(): void
    {
        $this->openCycle();

        $this->assertNull($this->repository()->ofHousehold(self::CYCLE, self::HOUSEHOLD));
    }

    #[Test]
    public function it_hands_out_the_same_empty_order_each_time_it_is_locked(): void
    {
        $this->openCycle();
        $repository = $this->repository();

        $first = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $second = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);

        $this->assertSame($first->id, $second->id);
        $this->assertSame([], $second->lines());
        $this->assertSame($first->id, $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD)?->id);
    }

    #[Test]
    public function it_keeps_the_orders_of_two_households_apart(): void
    {
        $this->openCycle();
        $repository = $this->repository();

        $this->assertNotSame(
            $repository->lockFor(self::CYCLE, self::HOUSEHOLD)->id,
            $repository->lockFor(self::CYCLE, self::OTHER_HOUSEHOLD)->id,
        );
    }

    #[Test]
    public function it_lists_the_orders_of_a_cycle_with_their_lines(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $now = $this->madrid('2026-10-22 10:00');

        foreach ([self::HOUSEHOLD => '2', self::OTHER_HOUSEHOLD => '3'] as $household => $quantity) {
            $order = $repository->lockFor(self::CYCLE, $household);
            $order->setLine($this->product(self::BREAD, 'Loaf', 350), Quantity::fromString($quantity), $cycle, $repository->nextId(), self::ANNA, $now);
            $repository->add($order);
        }

        $orders = $repository->ofCycle(self::CYCLE);

        $quantities = [];
        foreach ($orders as $order) {
            $quantities[$order->householdId] = $order->lines()[0]->ordered->toString();
        }
        $this->assertSame([self::HOUSEHOLD => '2.000', self::OTHER_HOUSEHOLD => '3.000'], $quantities);
    }

    #[Test]
    public function it_lists_no_orders_for_a_cycle_nobody_ordered_in(): void
    {
        $this->openCycle();

        $this->assertSame([], $this->repository()->ofCycle(self::CYCLE));
    }

    #[Test]
    public function it_lists_only_pending_pickup_cycles_of_the_requested_household(): void
    {
        $this->openCycle();
        $repository = $this->repository();
        $orderId = $repository->nextId();
        $otherId = $repository->nextId();
        $repository->add(Order::restore($orderId, self::CYCLE, self::HOUSEHOLD, [], PickupStatus::Pending));
        $repository->add(Order::restore($otherId, self::CYCLE, self::OTHER_HOUSEHOLD, [], PickupStatus::Pending));

        $this->assertSame([self::CYCLE], $repository->pendingPickupCycleIdsOfHousehold(self::HOUSEHOLD));
        $this->assertSame([self::CYCLE], $repository->pendingPickupCycleIdsOfHousehold(self::OTHER_HOUSEHOLD));

        foreach ([PickupStatus::None, PickupStatus::Completed, PickupStatus::NotCollected, PickupStatus::NothingToCollect] as $status) {
            $repository->add(Order::restore($orderId, self::CYCLE, self::HOUSEHOLD, [], $status));
            $this->assertSame([], $repository->pendingPickupCycleIdsOfHousehold(self::HOUSEHOLD), $status->value);
            $this->assertSame([self::CYCLE], $repository->pendingPickupCycleIdsOfHousehold(self::OTHER_HOUSEHOLD));
        }

        $repository->add(Order::restore($orderId, self::CYCLE, self::HOUSEHOLD, [], PickupStatus::Pending));
        $this->assertSame([self::CYCLE], $repository->pendingPickupCycleIdsOfHousehold(self::HOUSEHOLD));
    }

    #[Test]
    public function it_counts_the_lines_of_a_household_per_cycle(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $now = $this->madrid('2026-10-22 10:00');
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine($this->product(self::BREAD, 'Loaf', 350), Quantity::fromString('2'), $cycle, $repository->nextId(), self::ANNA, $now);
        $order->setLine($this->product(self::APPLES, 'Apples', 280, variable: true), Quantity::fromString('1'), $cycle, $repository->nextId(), self::ANNA, $now);
        $repository->add($order);
        $repository->lockFor(self::CYCLE, self::OTHER_HOUSEHOLD);

        $this->assertSame([self::CYCLE => 2], $repository->lineCountsOfHousehold(self::HOUSEHOLD));
        $this->assertSame([], $repository->lineCountsOfHousehold(self::OTHER_HOUSEHOLD));
    }

    #[Test]
    public function it_stores_a_line_with_its_snapshot(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $now = $this->madrid('2026-10-22 10:00:30.250000');
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine($this->product(self::APPLES, 'Apples', 280, '2', variable: true), Quantity::fromString('1.250'), $cycle, $repository->nextId(), self::ANNA, $now);
        $repository->add($order);

        $line = $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD)?->lines()[0];

        $this->assertNotNull($line);
        $this->assertSame([self::APPLES, self::BAKER, 'Apples', 'unit', 280, true, '2.000', '1.250', '1.250', self::ANNA], [
            $line->productId, $line->producerId, $line->name, $line->unit, $line->unitPrice->cents, $line->variable,
            $line->containersPerUnit?->toString(), $line->ordered->toString(), $line->allocated->toString(), $line->addedBy,
        ]);
        $this->assertSame($now->format('Y-m-d H:i:s.u'), $line->createdAt->setTimezone($now->getTimezone())->format('Y-m-d H:i:s.u'));
    }

    #[Test]
    public function it_stores_a_changed_line_and_forgets_a_removed_one(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $now = $this->madrid('2026-10-22 10:00');
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine($this->product(self::BREAD, 'Loaf', 350), Quantity::fromString('2'), $cycle, $repository->nextId(), self::ANNA, $now);
        $order->setLine($this->product(self::APPLES, 'Apples', 280, variable: true), Quantity::fromString('1'), $cycle, $repository->nextId(), self::ANNA, $now);
        $repository->add($order);

        $stored = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $stored->setLine($this->product(self::BREAD, 'Loaf', 400), Quantity::fromString('5'), $cycle, $repository->nextId(), self::ANNA, $now);
        $stored->removeLine(self::APPLES, $cycle, $now);
        $repository->add($stored);

        $lines = $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD)?->lines() ?? [];
        $this->assertCount(1, $lines);
        $this->assertSame([self::BREAD, 400, '5.000'], [$lines[0]->productId, $lines[0]->unitPrice->cents, $lines[0]->ordered->toString()]);
    }

    #[Test]
    public function it_ignores_changes_to_a_fetched_order_until_it_is_added_again(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $repository->add($repository->lockFor(self::CYCLE, self::HOUSEHOLD));

        $repository->lockFor(self::CYCLE, self::HOUSEHOLD)
            ->setLine($this->product(self::BREAD, 'Loaf', 350), Quantity::fromString('2'), $cycle, $repository->nextId(), self::ANNA, $this->madrid('2026-10-22 10:00'));

        $this->assertSame([], $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD)?->lines());
    }

    #[Test]
    public function it_knows_whether_a_product_has_lines_in_a_cycle_that_is_not_settled(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $this->assertFalse($repository->hasUnsettledLinesOf(self::BREAD));

        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine($this->product(self::BREAD, 'Loaf', 350), Quantity::fromString('2'), $cycle, $repository->nextId(), self::ANNA, $this->madrid('2026-10-22 10:00'));
        $repository->add($order);

        $this->assertTrue($repository->hasUnsettledLinesOf(self::BREAD));
        $this->assertFalse($repository->hasUnsettledLinesOf(self::APPLES));
    }

    #[Test]
    public function it_stores_dropped_lines_and_a_pending_pickup_when_something_is_still_allocated(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $now = $this->madrid('2026-10-22 10:00');
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine($this->product(self::BREAD, 'Loaf', 350), Quantity::fromString('2'), $cycle, $repository->nextId(), self::ANNA, $now);
        $order->setLine($this->product(self::APPLES, 'Apples', 280, variable: true), Quantity::fromString('1'), $cycle, $repository->nextId(), self::ANNA, $now);
        $this->assertSame(PickupStatus::None, $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD)?->pickup());

        $order->drop([self::APPLES => true]);
        $repository->add($order);

        $stored = $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD);
        $this->assertSame(PickupStatus::Pending, $stored?->pickup());
        $dropped = [];
        foreach ($stored?->lines() ?? [] as $line) {
            $dropped[$line->productId] = $line->dropped;
        }
        $this->assertSame([self::BREAD => false, self::APPLES => true], $dropped);
    }

    #[Test]
    public function it_stores_a_delivered_quantity_apart_from_the_allocation_and_an_opened_empty_pickup(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $now = $this->madrid('2026-10-22 10:00');
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine($this->product(self::APPLES, 'Apples', 280, variable: true), Quantity::fromString('1'), $cycle, $repository->nextId(), self::ANNA, $now);
        $order->drop([]);
        $order->adjustDelivered(self::APPLES, Quantity::fromString('1.120'));
        $repository->add($order);
        $empty = $repository->lockFor(self::CYCLE, self::OTHER_HOUSEHOLD);
        $empty->openPickup();
        $repository->add($empty);

        $line = $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD)?->lines()[0];
        $this->assertSame('1.120', $line?->delivered()->toString());
        $this->assertSame('1.000', $line?->allocated->toString());
        $this->assertTrue($line?->isAdjusted());
        $this->assertSame(PickupStatus::Pending, $repository->ofHousehold(self::CYCLE, self::OTHER_HOUSEHOLD)?->pickup());
    }

    #[Test]
    public function it_stores_a_completed_pickup_with_its_moment_and_the_amount_charged(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $now = $this->madrid('2026-10-22 10:00');
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine($this->product(self::BREAD, 'Bread', 300, variable: false), Quantity::fromString('4'), $cycle, $repository->nextId(), self::ANNA, $now);
        $order->drop([]);
        $order->complete($this->madrid('2026-11-07 11:30'));
        $repository->add($order);

        $stored = $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD);
        $this->assertSame(PickupStatus::Completed, $stored?->pickup());
        $this->assertSame(1200, $stored?->charged()?->cents);
        $this->assertEquals($this->madrid('2026-11-07 11:30')->getTimestamp(), $stored?->pickupDoneAt()?->getTimestamp());
        $this->assertNull($repository->lockFor(self::CYCLE, self::OTHER_HOUSEHOLD)->charged());
    }

    #[Test]
    public function it_stores_nothing_to_collect_when_every_allocation_is_zero_or_dropped(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $now = $this->madrid('2026-10-22 10:00');
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine($this->product(self::BREAD, 'Loaf', 350), Quantity::fromString('2'), $cycle, $repository->nextId(), self::ANNA, $now);
        $order->reallocate(self::BREAD, Quantity::fromString('0'));
        $other = $repository->lockFor(self::CYCLE, self::OTHER_HOUSEHOLD);
        $other->setLine($this->product(self::APPLES, 'Apples', 280, variable: true), Quantity::fromString('1'), $cycle, $repository->nextId(), self::ANNA, $now);

        $order->drop([]);
        $other->drop([self::APPLES => true]);
        $repository->add($order);
        $repository->add($other);

        $this->assertSame(PickupStatus::NothingToCollect, $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD)?->pickup());
        $this->assertSame(PickupStatus::NothingToCollect, $repository->ofHousehold(self::CYCLE, self::OTHER_HOUSEHOLD)?->pickup());
    }

    #[Test]
    public function it_gives_an_order_without_lines_no_pickup_when_settling(): void
    {
        $this->openCycle();
        $repository = $this->repository();
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);

        $order->drop([]);
        $repository->add($order);

        $this->assertSame(PickupStatus::None, $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD)?->pickup());
    }

    #[Test]
    public function it_excludes_cancelled_orders_and_keeps_their_line_references(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine(
            $this->product(self::BREAD, 'Bread', 350), Quantity::fromString('2'), $cycle,
            $repository->nextId(), self::ANNA, $this->madrid('2026-10-22 10:00'),
        );
        $repository->add($order);
        $other = $repository->lockFor(self::CYCLE, self::OTHER_HOUSEHOLD);

        $repository->deleteOfCycle(self::CYCLE);

        $this->assertNull($repository->ofHousehold(self::CYCLE, self::HOUSEHOLD));
        $this->assertNull($repository->ofHousehold(self::CYCLE, self::OTHER_HOUSEHOLD));
        $this->assertSame([], $repository->ofCycle(self::CYCLE));
        $this->assertTrue($repository->hasWeeklyReference(self::BREAD));
    }

    #[Test]
    public function it_stores_an_allocation_set_by_hand_and_one_the_reception_computed(): void
    {
        $cycle = $this->openCycle();
        $repository = $this->repository();
        $now = $this->madrid('2026-10-22 10:00');
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->setLine($this->product(self::BREAD, 'Loaf', 350), Quantity::fromString('4'), $cycle, $repository->nextId(), self::ANNA, $now);
        $order->setLine($this->product(self::APPLES, 'Apples', 280, variable: true), Quantity::fromString('2'), $cycle, $repository->nextId(), self::ANNA, $now);
        $repository->add($order);

        $stored = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $stored->allocate(self::BREAD, Quantity::fromString('3'));
        $stored->reallocate(self::APPLES, Quantity::fromString('1.500'));
        $repository->add($stored);

        $lines = [];
        foreach ($repository->ofHousehold(self::CYCLE, self::HOUSEHOLD)?->lines() ?? [] as $line) {
            $lines[$line->productId] = [$line->ordered->toString(), $line->allocated->toString(), $line->allocatedByHand];
        }
        $this->assertSame([
            self::BREAD => ['4.000', '3.000', true],
            self::APPLES => ['2.000', '1.500', false],
        ], $lines);
    }

    #[Test]
    public function it_stores_an_allocation_only_line_and_leaves_it_out_of_the_lines_a_household_ordered(): void
    {
        $this->openCycle();
        $repository = $this->repository();
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->allocateNew($this->product(self::BREAD, 'Loaf', 350), Quantity::fromString('1'), $repository->nextId(), self::ANNA, $this->madrid('2026-10-22 10:00'));
        $repository->add($order);

        $line = $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD)?->lines()[0];

        $this->assertNotNull($line);
        $this->assertSame(['0.000', '1.000', true, 350], [$line->ordered->toString(), $line->allocated->toString(), $line->allocatedByHand, $line->unitPrice->cents]);
        $this->assertSame([], $repository->lineCountsOfHousehold(self::HOUSEHOLD));
    }

    #[Test]
    public function it_stores_stock_and_return_lines_with_their_snapshots_and_removes_them_whole(): void
    {
        $this->openCycle();
        $repository = $this->repository();
        $order = $repository->lockFor(self::CYCLE, self::HOUSEHOLD);
        $order->openPickup();
        $honey = new StockOffer(self::HONEY, 'Honey', 'jar', Money::ofCents(650), false, Quantity::fromString('2'), true, Quantity::fromString('9'));
        $cheese = new StockOffer(self::CHEESE, 'Cheese', 'kg', Money::ofCents(2000), true, null, true, Quantity::fromString('9'));
        $order->addStock($honey, Quantity::fromString('2'), $repository->nextId());
        $order->addStock($cheese, Quantity::fromString('0.250'), $repository->nextId());
        $order->addReturn(new ReturnItemOffer(self::JAR, 'Glass jar', Money::ofCents(50)), 4, $repository->nextId());
        $repository->add($order);

        $stored = $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD);
        [$storedHoney, $storedCheese] = $stored->stockLines();

        $this->assertSame([self::HONEY, 'Honey', 'jar', 650, false, '2.000', '2.000'], [$storedHoney->stockProductId, $storedHoney->name, $storedHoney->unit, $storedHoney->unitPrice->cents, $storedHoney->variable, $storedHoney->containersPerUnit?->toString(), $storedHoney->quantity->toString()]);
        $this->assertSame([self::CHEESE, true, null, '0.250'], [$storedCheese->stockProductId, $storedCheese->variable, $storedCheese->containersPerUnit, $storedCheese->quantity->toString()]);
        $this->assertCount(1, $stored->returnLines());
        $this->assertSame([self::JAR, 'Glass jar', 50, 4], [$stored->returnLines()[0]->returnItemId, $stored->returnLines()[0]->name, $stored->returnLines()[0]->amount->cents, $stored->returnLines()[0]->quantity]);

        $stored->addStock($honey, Quantity::fromString('1'), $repository->nextId());
        $stored->removeStock(self::CHEESE);
        $stored->removeReturn(self::JAR);
        $repository->add($stored);

        $again = $repository->ofHousehold(self::CYCLE, self::HOUSEHOLD);
        $this->assertSame(['3.000'], array_map(fn ($line) => $line->quantity->toString(), $again->stockLines()));
        $this->assertSame([], $again->returnLines());
        $this->assertTrue($repository->hasStockReference(self::CHEESE));
        $this->assertTrue($repository->hasStockReference(self::HONEY));
    }

    #[Test]
    public function it_returns_the_stock_and_return_lines_of_every_order_of_a_cycle(): void
    {
        $this->openCycle();
        $repository = $this->repository();
        $honey = new StockOffer(self::HONEY, 'Honey', 'jar', Money::ofCents(650), false, null, true, Quantity::fromString('9'));
        $jar = new ReturnItemOffer(self::JAR, 'Glass jar', Money::ofCents(50));

        foreach ([[self::HOUSEHOLD, 2, 3], [self::OTHER_HOUSEHOLD, 1, 0]] as [$household, $stock, $returned]) {
            $order = $repository->lockFor(self::CYCLE, $household);
            $order->openPickup();
            $order->addStock($honey, Quantity::fromString((string) $stock), $repository->nextId());

            if ($returned > 0) {
                $order->addReturn($jar, $returned, $repository->nextId());
            }

            $repository->add($order);
        }

        $byHousehold = [];
        foreach ($repository->ofCycle(self::CYCLE) as $order) {
            $byHousehold[$order->householdId] = [
                array_map(fn ($line) => $line->quantity->toString(), $order->stockLines()),
                array_map(fn ($line) => $line->quantity, $order->returnLines()),
            ];
        }

        $this->assertSame([self::HOUSEHOLD => [['2.000'], [3]], self::OTHER_HOUSEHOLD => [['1.000'], []]], $byHousehold);
    }
}
