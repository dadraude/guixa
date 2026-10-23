<?php

namespace Tests\Support\Contracts;

use DateTimeImmutable;
use DateTimeZone;
use Guixa\Ordering\Domain\Cycle;
use Guixa\Ordering\Domain\CycleRepository;
use Guixa\Ordering\Domain\ProducerOrder;
use Guixa\Ordering\Domain\ValueObject\CutoffRule;
use Guixa\Ordering\Domain\ValueObject\PickupWeekday;
use PHPUnit\Framework\Attributes\Test;

/** Behaviour every CycleRepository promises; see ProbeNoteRepositoryContract for how it is shared. */
trait CycleRepositoryContract
{
    abstract protected function repository(): CycleRepository;

    private const BAKER = '00000000-0000-7000-8000-0000000000b1';

    private const GROWER = '00000000-0000-7000-8000-0000000000b2';

    /** Lets an adapter that enforces foreign keys create the producers the tests refer to. */
    protected function producersExist(string ...$producerIds): void {}

    private function madrid(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('Europe/Madrid'));
    }

    private function cycle(CycleRepository $repository, string $pickupDate = '2026-11-07'): Cycle
    {
        $this->producersExist(self::BAKER, self::GROWER);

        return Cycle::open(
            $repository->nextId(),
            $this->madrid($pickupDate),
            PickupWeekday::of((int) $this->madrid($pickupDate)->format('N')),
            $this->madrid('2026-10-22 10:00'),
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
    public function it_finds_what_was_added_with_its_pickup_date_and_participants(): void
    {
        $repository = $this->repository();
        $cycle = $this->cycle($repository);
        $now = $this->madrid('2026-10-22 10:00');
        $cycle->addParticipant(self::BAKER, CutoffRule::of(3, '20:00'), $now);
        // 2 days before at 12:00 is Thursday 5th; with the clock past it the cutoff is null.
        $cycle->addParticipant(self::GROWER, CutoffRule::of(2, '12:00'), $this->madrid('2026-11-05 13:00'));
        $repository->add($cycle);

        $found = $repository->get($cycle->id);

        $this->assertNotNull($found);
        $this->assertSame('2026-11-07', $found->pickupDate()->format('Y-m-d'));
        $orders = $found->producerOrders();
        usort($orders, fn (ProducerOrder $a, ProducerOrder $b) => $a->producerId <=> $b->producerId);
        $this->assertSame([self::BAKER, self::GROWER], array_map(fn (ProducerOrder $order) => $order->producerId, $orders));
        $this->assertTrue($orders[0]->isParticipating());
        $this->assertEquals(
            $this->madrid('2026-11-04 20:00')->getTimestamp(),
            $orders[0]->cutoff()?->getTimestamp(),
        );
        $this->assertNull($orders[1]->cutoff());
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        $repository = $this->repository();

        $this->assertNull($repository->get($repository->nextId()));
        $this->assertNull($repository->getForUpdate($repository->nextId()));
        $this->assertNull($repository->getForShare($repository->nextId()));
    }

    #[Test]
    public function it_adds_the_participants_of_a_cycle_added_again(): void
    {
        $repository = $this->repository();
        $cycle = $this->cycle($repository);
        $cycle->addParticipant(self::BAKER, CutoffRule::of(3, '20:00'), $this->madrid('2026-10-22 10:00'));
        $repository->add($cycle);

        $found = $repository->getForUpdate($cycle->id);
        $found?->addParticipant(self::GROWER, CutoffRule::of(2, '12:00'), $this->madrid('2026-10-22 10:00'));
        $repository->add($found);

        $this->assertCount(2, $repository->get($cycle->id)?->producerOrders() ?? []);
        $this->assertCount(1, $repository->list());
    }

    #[Test]
    public function it_ignores_changes_to_a_fetched_cycle_until_it_is_added_again(): void
    {
        $repository = $this->repository();
        $cycle = $this->cycle($repository);
        $repository->add($cycle);

        $repository->get($cycle->id)?->addParticipant(self::BAKER, CutoffRule::of(3, '20:00'), $this->madrid('2026-10-22 10:00'));

        $this->assertCount(0, $repository->get($cycle->id)?->producerOrders() ?? []);
    }

    #[Test]
    public function it_lists_cycles_by_pickup_date_earliest_first(): void
    {
        $repository = $this->repository();
        foreach (['2026-11-14', '2026-11-07', '2026-12-05'] as $date) {
            $repository->add($this->cycle($repository, $date));
        }

        $dates = array_map(fn (Cycle $cycle) => $cycle->pickupDate()->format('Y-m-d'), $repository->list());

        $this->assertSame(['2026-11-07', '2026-11-14', '2026-12-05'], $dates);
    }

    #[Test]
    public function it_knows_whether_a_cycle_has_a_pickup_date(): void
    {
        $repository = $this->repository();
        $repository->add($this->cycle($repository, '2026-11-07'));

        $this->assertTrue($repository->existsOnPickupDate($this->madrid('2026-11-07')));
        $this->assertFalse($repository->existsOnPickupDate($this->madrid('2026-11-14')));
    }

    #[Test]
    public function it_stores_a_cancelled_cycle_and_no_longer_counts_its_pickup_date(): void
    {
        $repository = $this->repository();
        $cycle = $this->cycle($repository, '2026-11-07');
        $repository->add($cycle);
        $this->assertFalse($repository->get($cycle->id)->isCancelled());

        $stored = $repository->getForUpdate($cycle->id);
        $stored->cancel($this->madrid('2026-10-23 09:00'));
        $repository->add($stored);

        $found = $repository->get($cycle->id);
        $this->assertTrue($found->isCancelled());
        $this->assertSame($this->madrid('2026-10-23 09:00')->getTimestamp(), $found->cancelledAt()->getTimestamp());
        $this->assertFalse($repository->existsOnPickupDate($this->madrid('2026-11-07')));
    }

    #[Test]
    public function it_stores_a_moved_and_a_cleared_cutoff(): void
    {
        $repository = $this->repository();
        $now = $this->madrid('2026-10-22 10:00');
        $cycle = $this->cycle($repository);
        $cycle->addParticipant(self::BAKER, CutoffRule::of(3, '20:00'), $now);
        $cycle->addParticipant(self::GROWER, CutoffRule::of(2, '12:00'), $now);
        $repository->add($cycle);

        $stored = $repository->getForUpdate($cycle->id);
        $stored->setCutoff(self::BAKER, $this->madrid('2026-11-05 12:00'), $now);
        $stored->clearCutoff(self::GROWER);
        $repository->add($stored);

        $orders = [];
        foreach ($repository->get($cycle->id)->producerOrders() as $order) {
            $orders[$order->producerId] = $order->cutoff()?->getTimestamp();
        }
        $this->assertSame($this->madrid('2026-11-05 12:00')->getTimestamp(), $orders[self::BAKER]);
        $this->assertNull($orders[self::GROWER]);
    }

    #[Test]
    public function it_finds_a_cycle_to_read_under_a_shared_lock(): void
    {
        $repository = $this->repository();
        $cycle = $this->cycle($repository);
        $cycle->addParticipant(self::BAKER, CutoffRule::of(3, '20:00'), $this->madrid('2026-10-22 10:00'));
        $repository->add($cycle);

        $found = $repository->getForShare($cycle->id);

        $this->assertSame($cycle->id, $found?->id);
        $this->assertCount(1, $found->producerOrders());
    }
}
