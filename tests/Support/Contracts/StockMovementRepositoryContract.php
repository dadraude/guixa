<?php

namespace Tests\Support\Contracts;

use DateTimeImmutable;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Domain\MovementType;
use Guixa\Stock\Domain\StockMovement;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Domain\ValueObject\SignedQuantity;
use PHPUnit\Framework\Attributes\Test;

/** Behaviour every StockMovementRepository promises; see ProbeNoteRepositoryContract for how it is shared. */
trait StockMovementRepositoryContract
{
    abstract protected function repository(): StockMovementRepository;

    /** An id the repository accepts as a stock product (the database needs a real one). */
    abstract protected function stockProductId(): string;

    private const string ACCOUNT = '0198f0a0-0000-7000-8000-000000000001';

    private function entry(StockMovementRepository $repository, string $productId, string $quantity, string $at = '2026-10-22T10:00:00+00:00'): StockMovement
    {
        return StockMovement::entry($repository->nextId(), $productId, Quantity::fromString($quantity), new DateTimeImmutable($at), self::ACCOUNT, 'bought');
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
    public function it_keeps_every_field_of_an_entry_an_adjustment_and_a_sale(): void
    {
        $repository = $this->repository();
        $productId = $this->stockProductId();
        $cycleId = '0198f0a0-0000-7000-8000-0000000000c1';
        $orderId = '0198f0a0-0000-7000-8000-0000000000d1';
        $at = new DateTimeImmutable('2026-10-22T10:00:00+00:00');

        $repository->add(StockMovement::entry($repository->nextId(), $productId, Quantity::fromString('10'), $at, self::ACCOUNT, 'bought from X'));
        $repository->add(StockMovement::adjustment($repository->nextId(), $productId, SignedQuantity::fromString('-1.5'), $at->modify('+1 minute'), self::ACCOUNT, 'broken jar', $cycleId));
        $repository->add(StockMovement::sale($repository->nextId(), $productId, Quantity::fromString('2'), $at->modify('+2 minutes'), self::ACCOUNT, $orderId));

        [$entry, $adjustment, $sale] = $repository->forProduct($productId);

        $this->assertSame(MovementType::Entry, $entry->type);
        $this->assertSame('10.000', $entry->quantity->toString());
        $this->assertSame('bought from X', $entry->note);
        $this->assertSame(self::ACCOUNT, $entry->recordedBy);
        $this->assertSame($at->getTimestamp(), $entry->occurredAt->getTimestamp());
        $this->assertNull($entry->cycleId);
        $this->assertNull($entry->orderId);
        $this->assertSame(MovementType::Adjustment, $adjustment->type);
        $this->assertSame('-1.500', $adjustment->quantity->toString());
        $this->assertSame('broken jar', $adjustment->note);
        $this->assertSame($cycleId, $adjustment->cycleId);
        $this->assertSame(MovementType::Sale, $sale->type);
        $this->assertSame('-2.000', $sale->quantity->toString());
        $this->assertNull($sale->note);
        $this->assertSame($orderId, $sale->orderId);
    }

    #[Test]
    public function it_lists_a_products_movements_oldest_first_and_only_its_own(): void
    {
        $repository = $this->repository();
        $productId = $this->stockProductId();
        $repository->add($this->entry($repository, $productId, '2', '2026-10-22T12:00:00+00:00'));
        $repository->add($this->entry($repository, $productId, '1', '2026-10-22T09:00:00+00:00'));
        $repository->add($this->entry($repository, $this->stockProductId(), '7'));

        $quantities = array_map(fn (StockMovement $movement) => $movement->quantity->toString(), $repository->forProduct($productId));

        $this->assertSame(['1.000', '2.000'], $quantities);
    }

    #[Test]
    public function it_sums_the_available_stock_of_one_product(): void
    {
        $repository = $this->repository();
        $productId = $this->stockProductId();
        $repository->add($this->entry($repository, $productId, '10'));
        $repository->add(StockMovement::adjustment($repository->nextId(), $productId, SignedQuantity::fromString('-1.250'), new DateTimeImmutable('2026-10-22T11:00:00+00:00'), self::ACCOUNT, 'dropped', null));
        $repository->add(StockMovement::sale($repository->nextId(), $productId, Quantity::fromString('0.500'), new DateTimeImmutable('2026-10-22T12:00:00+00:00'), self::ACCOUNT, '0198f0a0-0000-7000-8000-0000000000d1'));

        $this->assertSame('8.250', $repository->available($productId)->toString());
        $this->assertSame('8.250', $repository->availableForUpdate($productId)->toString());
    }

    #[Test]
    public function it_has_nothing_available_for_a_product_without_movements(): void
    {
        $repository = $this->repository();
        $productId = $this->stockProductId();

        $this->assertSame('0.000', $repository->available($productId)->toString());
        $this->assertSame('0.000', $repository->availableForUpdate($productId)->toString());
        $this->assertArrayNotHasKey($productId, $repository->availableByProduct());
    }

    #[Test]
    public function it_sums_the_available_stock_by_product(): void
    {
        $repository = $this->repository();
        $honey = $this->stockProductId();
        $oil = $this->stockProductId();
        $repository->add($this->entry($repository, $honey, '3'));
        $repository->add($this->entry($repository, $honey, '4'));
        $repository->add($this->entry($repository, $oil, '1.5'));

        $available = $repository->availableByProduct();

        $this->assertSame('7.000', $available[$honey]->toString());
        $this->assertSame('1.500', $available[$oil]->toString());
    }

    #[Test]
    public function it_sums_totals_beyond_seven_integer_digits_exactly(): void
    {
        $repository = $this->repository();
        $productId = $this->stockProductId();
        $repository->add($this->entry($repository, $productId, '9999999.999'));
        $repository->add($this->entry($repository, $productId, '9999999.999'));

        $this->assertSame('19999999.998', $repository->available($productId)->toString());
        $this->assertSame('19999999.998', $repository->availableForUpdate($productId)->toString());
        $this->assertSame('19999999.998', $repository->availableByProduct()[$productId]->toString());
    }
}
