<?php

namespace Tests\Support\Contracts;

use DateTimeImmutable;
use DateTimeZone;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\OrderRepository;
use Guixa\Ordering\Domain\ValueObject\CutoffRule;
use Guixa\Ordering\Domain\ValueObject\OrderableProduct;
use Guixa\Ordering\Domain\ValueObject\PickupWeekday;
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
}
