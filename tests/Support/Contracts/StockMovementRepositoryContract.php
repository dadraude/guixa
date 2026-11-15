<?php

namespace Tests\Support\Contracts;

use DateTimeImmutable;
use DomainException;
use Guixa\Shared\Domain\Quantity;
use Guixa\Stock\Domain\MovementType;
use Guixa\Stock\Domain\StockMovement;
use Guixa\Stock\Domain\StockMovementRepository;
use Guixa\Stock\Domain\ValueObject\SignedQuantity;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Test;

/** Behaviour every StockMovementRepository promises; see ProbeNoteRepositoryContract for how it is shared. */
trait StockMovementRepositoryContract
{
    abstract protected function repository(): StockMovementRepository;

    /** An id the repository accepts as a stock product (the database needs a real one). */
    abstract protected function productId(): string;

    /** Create a basket accepted by this adapter. */
    abstract protected function basket(): string;

    /** Create a correction source for a basket that this adapter accepts. */
    abstract protected function correctionFor(string $basket): string;

    /** Lets an adapter that enforces foreign keys create the cycle the tests refer to. */
    protected function cycleExists(string $cycleId): void {}

    private const string ACCOUNT = '0198f0a0-0000-7000-8000-000000000001';

    private function entry(StockMovementRepository $repository, string $productId, string $quantity, string $at = '2026-10-22T10:00:00+00:00'): StockMovement
    {
        return StockMovement::entry($repository->nextId(), $productId, Quantity::fromString($quantity), new DateTimeImmutable($at), self::ACCOUNT, 'bought');
    }

    #[Test]
    public function it_recognizes_permanent_use_after_movements_net_to_zero(): void
    {
        $repository = $this->repository();
        $productId = $this->productId();
        $this->assertFalse($repository->hasProductReference($productId));
        $repository->add($this->entry($repository, $productId, '1'));
        $repository->add(StockMovement::adjustment($repository->nextId(), $productId, SignedQuantity::fromString('-1'), new DateTimeImmutable('2026-10-22T11:00:00+00:00'), self::ACCOUNT, 'returned', null));
        $this->assertSame('0.000', $repository->available($productId)->toString());
        $this->assertTrue($repository->hasProductReference($productId));
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
        $productId = $this->productId();
        $cycleId = '0198f0a0-0000-7000-8000-0000000000c1';
        $this->cycleExists($cycleId);
        $basketId = $this->basket();
        $at = new DateTimeImmutable('2026-10-22T10:00:00+00:00');

        $repository->add(StockMovement::entry($repository->nextId(), $productId, Quantity::fromString('10'), $at, self::ACCOUNT, 'bought from X'));
        $repository->add(StockMovement::adjustment($repository->nextId(), $productId, SignedQuantity::fromString('-1.5'), $at->modify('+1 minute'), self::ACCOUNT, 'broken jar', $cycleId));
        $repository->add(StockMovement::basketSale($repository->nextId(), $productId, Quantity::fromString('2'), $at->modify('+2 minutes'), self::ACCOUNT, $basketId));

        [$entry, $adjustment, $sale] = $repository->forProduct($productId);

        $this->assertSame(MovementType::Entry, $entry->type);
        $this->assertSame($productId, $entry->productId);
        $this->assertSame('10.000', $entry->quantity->toString());
        $this->assertSame('bought from X', $entry->note);
        $this->assertSame(self::ACCOUNT, $entry->recordedBy);
        $this->assertSame($at->getTimestamp(), $entry->occurredAt->getTimestamp());
        $this->assertNull($entry->cycleId);
        $this->assertNull($entry->basketId);
        $this->assertSame(MovementType::Adjustment, $adjustment->type);
        $this->assertSame($productId, $adjustment->productId);
        $this->assertSame('-1.500', $adjustment->quantity->toString());
        $this->assertSame('broken jar', $adjustment->note);
        $this->assertSame($cycleId, $adjustment->cycleId);
        $this->assertSame(MovementType::Sale, $sale->type);
        $this->assertSame($productId, $sale->productId);
        $this->assertSame('-2.000', $sale->quantity->toString());
        $this->assertNull($sale->note);
        $this->assertSame($basketId, $sale->basketId);
    }

    #[Test]
    public function it_lists_a_products_movements_oldest_first_and_only_its_own(): void
    {
        $repository = $this->repository();
        $productId = $this->productId();
        $repository->add($this->entry($repository, $productId, '2', '2026-10-22T12:00:00+00:00'));
        $repository->add($this->entry($repository, $productId, '1', '2026-10-22T09:00:00+00:00'));
        $repository->add($this->entry($repository, $this->productId(), '7'));

        $quantities = array_map(fn (StockMovement $movement) => $movement->quantity->toString(), $repository->forProduct($productId));

        $this->assertSame(['1.000', '2.000'], $quantities);
    }

    #[Test]
    public function it_breaks_ties_between_movements_of_the_same_instant_by_id(): void
    {
        $repository = $this->repository();
        $productId = $this->productId();
        $at = new DateTimeImmutable('2026-10-22T10:00:00+00:00');

        foreach (['0198f0a0-0000-7000-8000-00000000000b' => '2', '0198f0a0-0000-7000-8000-00000000000a' => '1'] as $id => $quantity) {
            $repository->add(StockMovement::entry($id, $productId, Quantity::fromString($quantity), $at, self::ACCOUNT, null));
        }

        $quantities = array_map(fn (StockMovement $movement) => $movement->quantity->toString(), $repository->forProduct($productId));

        $this->assertSame(['1.000', '2.000'], $quantities);
    }

    #[Test]
    public function it_sums_the_available_stock_of_one_product(): void
    {
        $repository = $this->repository();
        $productId = $this->productId();
        $repository->add($this->entry($repository, $productId, '10'));
        $repository->add(StockMovement::adjustment($repository->nextId(), $productId, SignedQuantity::fromString('-1.250'), new DateTimeImmutable('2026-10-22T11:00:00+00:00'), self::ACCOUNT, 'dropped', null));
        $repository->add(StockMovement::basketSale($repository->nextId(), $productId, Quantity::fromString('0.500'), new DateTimeImmutable('2026-10-22T12:00:00+00:00'), self::ACCOUNT, $this->basket()));

        $this->assertSame('8.250', $repository->available($productId)->toString());
    }

    #[Test]
    public function it_has_nothing_available_for_a_product_without_movements(): void
    {
        $repository = $this->repository();
        $productId = $this->productId();

        $this->assertSame('0.000', $repository->available($productId)->toString());
        $this->assertArrayNotHasKey($productId, $repository->availableByProduct());
    }

    #[Test]
    public function it_sums_the_available_stock_by_product(): void
    {
        $repository = $this->repository();
        $honey = $this->productId();
        $oil = $this->productId();
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
        $productId = $this->productId();
        $repository->add($this->entry($repository, $productId, '9999999.999'));
        $repository->add($this->entry($repository, $productId, '9999999.999'));

        $this->assertSame('19999999.998', $repository->available($productId)->toString());
        $this->assertSame('19999999.998', $repository->availableByProduct()[$productId]->toString());
    }

    #[Test]
    public function it_keeps_distinct_basket_sales_with_their_sources(): void
    {
        $repository = $this->repository();
        $product = $this->productId();
        $first = $this->basket();
        $second = $this->basket();
        $at = new DateTimeImmutable('2026-10-22T10:00:00+00:00');
        $repository->add(StockMovement::basketSale($repository->nextId(), $product, Quantity::fromString('1'), $at, self::ACCOUNT, $first));
        $repository->add(StockMovement::basketSale($repository->nextId(), $product, Quantity::fromString('2'), $at->modify('+1 minute'), self::ACCOUNT, $second));

        $movements = $repository->forProduct($product);
        $this->assertSame([$first, $second], array_map(fn (StockMovement $movement) => $movement->basketId, $movements));
        $this->assertSame('-3.000', $repository->available($product)->toString());
    }

    #[Test]
    public function it_rejects_a_duplicate_basket_sale_for_one_product_but_allows_another_product(): void
    {
        $repository = $this->repository();
        $firstProduct = $this->productId();
        $secondProduct = $this->productId();
        $basket = $this->basket();
        $at = new DateTimeImmutable('2026-10-22T10:00:00+00:00');
        $repository->add(StockMovement::basketSale($repository->nextId(), $firstProduct, Quantity::fromString('1'), $at, self::ACCOUNT, $basket));
        $repository->add(StockMovement::basketSale($repository->nextId(), $secondProduct, Quantity::fromString('1'), $at, self::ACCOUNT, $basket));

        $rejected = null;
        try {
            $repository->add(StockMovement::basketSale($repository->nextId(), $firstProduct, Quantity::fromString('1'), $at, self::ACCOUNT, $basket));
        } catch (DomainException|QueryException $exception) {
            $rejected = $exception;
        }

        $this->assertNotNull($rejected);
        $this->assertCount(1, $repository->forProduct($firstProduct));
        $this->assertCount(1, $repository->forProduct($secondProduct));
    }

    #[Test]
    public function it_keeps_a_basket_correction_linked_to_its_sale_and_rejects_a_duplicate_correction_per_product(): void
    {
        $repository = $this->repository();
        $product = $this->productId();
        $other = $this->productId();
        $basket = $this->basket();
        $correction = $this->correctionFor($basket);
        $at = new DateTimeImmutable('2026-10-22T10:00:00+00:00');
        $repository->add(StockMovement::basketSale($repository->nextId(), $product, Quantity::fromString('3'), $at, self::ACCOUNT, $basket));
        $correct = fn (string $productId) => StockMovement::basketCorrection($repository->nextId(), $productId,
            SignedQuantity::fromString('1'), $at->modify('+1 minute'), self::ACCOUNT, 'Came back', $basket, $correction);
        $repository->add($correct($product));
        $repository->add($correct($other));

        $stored = $repository->forProduct($product)[1];
        $this->assertSame(MovementType::Adjustment, $stored->type);
        $this->assertSame([$basket, $correction, 'Came back', '1.000'], [$stored->basketId, $stored->correctionId, $stored->note, $stored->quantity->toString()]);
        $this->assertSame('-2.000', $repository->available($product)->toString());

        $rejected = null;
        try {
            $repository->add($correct($product));
        } catch (DomainException|QueryException $exception) {
            $rejected = $exception;
        }
        $this->assertNotNull($rejected);
        $this->assertCount(2, $repository->forProduct($product));
    }
}
