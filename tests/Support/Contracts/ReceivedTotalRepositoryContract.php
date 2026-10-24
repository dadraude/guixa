<?php

namespace Tests\Support\Contracts;

use Guixa\Ordering\Domain\ReceivedTotal;
use Guixa\Ordering\Domain\ReceivedTotalRepository;
use Guixa\Shared\Domain\Quantity;
use PHPUnit\Framework\Attributes\Test;

/** Behaviour every ReceivedTotalRepository promises; see ProbeNoteRepositoryContract for how it is shared. */
trait ReceivedTotalRepositoryContract
{
    abstract protected function repository(): ReceivedTotalRepository;

    private const CYCLE_A = '00000000-0000-7000-8000-0000000000c1';

    private const CYCLE_B = '00000000-0000-7000-8000-0000000000c2';

    private const BAKER = '00000000-0000-7000-8000-0000000000b1';

    private const GROWER = '00000000-0000-7000-8000-0000000000b2';

    private const BREAD = '00000000-0000-7000-8000-0000000000f1';

    private const APPLES = '00000000-0000-7000-8000-0000000000f2';

    /** Lets an adapter that enforces foreign keys create the rows the totals refer to. */
    protected function referencedRowsExist(): void {}

    private function total(string $cycleId, string $producerId, string $productId, string $received): ReceivedTotal
    {
        return new ReceivedTotal($cycleId, $producerId, $productId, Quantity::fromString($received));
    }

    private function received(array $totals): array
    {
        return array_map(fn (Quantity $quantity) => $quantity->toString(), $totals);
    }

    #[Test]
    public function it_has_no_totals_until_one_is_recorded(): void
    {
        $this->referencedRowsExist();

        $this->assertSame([], $this->repository()->ofProducer(self::CYCLE_A, self::BAKER));
    }

    #[Test]
    public function it_stores_the_total_of_a_product(): void
    {
        $this->referencedRowsExist();
        $repository = $this->repository();

        $repository->set($this->total(self::CYCLE_A, self::BAKER, self::BREAD, '2.500'));

        $this->assertSame([self::BREAD => '2.500'], $this->received($repository->ofProducer(self::CYCLE_A, self::BAKER)));
    }

    #[Test]
    public function it_replaces_the_total_a_product_had(): void
    {
        $this->referencedRowsExist();
        $repository = $this->repository();
        $repository->set($this->total(self::CYCLE_A, self::BAKER, self::BREAD, '3'));

        $repository->set($this->total(self::CYCLE_A, self::BAKER, self::BREAD, '0'));

        $this->assertSame([self::BREAD => '0.000'], $this->received($repository->ofProducer(self::CYCLE_A, self::BAKER)));
    }

    #[Test]
    public function it_keeps_cycles_and_producers_apart(): void
    {
        $this->referencedRowsExist();
        $repository = $this->repository();
        $repository->set($this->total(self::CYCLE_A, self::BAKER, self::BREAD, '3'));
        $repository->set($this->total(self::CYCLE_B, self::BAKER, self::BREAD, '4'));
        $repository->set($this->total(self::CYCLE_A, self::GROWER, self::APPLES, '1.250'));

        $this->assertSame([self::BREAD => '3.000'], $this->received($repository->ofProducer(self::CYCLE_A, self::BAKER)));
        $this->assertSame([self::BREAD => '4.000'], $this->received($repository->ofProducer(self::CYCLE_B, self::BAKER)));
        $this->assertSame([self::APPLES => '1.250'], $this->received($repository->ofProducer(self::CYCLE_A, self::GROWER)));
    }
}
