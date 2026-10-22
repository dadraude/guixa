<?php

namespace Tests\Support\Contracts;

use Guixa\Shared\Domain\Money;
use Guixa\Stock\Domain\StockProduct;
use Guixa\Stock\Domain\StockProductRepository;
use PHPUnit\Framework\Attributes\Test;

/** Behaviour every StockProductRepository promises; see ProbeNoteRepositoryContract for how it is shared. */
trait StockProductRepositoryContract
{
    abstract protected function repository(): StockProductRepository;

    private function product(StockProductRepository $repository, string $name = 'Honey', ?string $containers = '2.000'): StockProduct
    {
        return StockProduct::create($repository->nextId(), $name, 'jar', Money::ofCents(650), false, $containers);
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
    public function it_finds_what_was_added_with_every_field(): void
    {
        $repository = $this->repository();
        $product = $this->product($repository);

        $repository->add($product);
        $found = $repository->get($product->id);

        $this->assertNotNull($found);
        $this->assertSame($product->id, $found->id);
        $this->assertSame('Honey', $found->name());
        $this->assertSame('jar', $found->unit());
        $this->assertSame(650, $found->price()->cents);
        $this->assertFalse($found->isVariable());
        $this->assertSame('2.000', $found->containersPerUnit()?->toString());
        $this->assertTrue($found->isActive());
    }

    #[Test]
    public function it_keeps_a_missing_containers_per_unit_as_null(): void
    {
        $repository = $this->repository();
        $product = $this->product($repository, containers: null);

        $repository->add($product);

        $this->assertNull($repository->get($product->id)?->containersPerUnit());
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        $repository = $this->repository();

        $this->assertNull($repository->get($repository->nextId()));
    }

    #[Test]
    public function it_replaces_a_product_added_twice(): void
    {
        $repository = $this->repository();
        $product = $this->product($repository);
        $repository->add($product);

        $product->change('Wild honey', 'kg', Money::ofCents(1200), true, null);
        $product->deactivate();
        $repository->add($product);

        $found = $repository->get($product->id);
        $this->assertSame('Wild honey', $found?->name());
        $this->assertSame(1200, $found->price()->cents);
        $this->assertTrue($found->isVariable());
        $this->assertNull($found->containersPerUnit());
        $this->assertFalse($found->isActive());
        $this->assertCount(1, $repository->list());
    }

    #[Test]
    public function it_ignores_changes_to_a_fetched_product_until_it_is_added_again(): void
    {
        $repository = $this->repository();
        $product = $this->product($repository);
        $repository->add($product);

        $repository->get($product->id)?->deactivate();

        $this->assertTrue($repository->get($product->id)?->isActive());
    }

    #[Test]
    public function it_lists_products_by_name(): void
    {
        $repository = $this->repository();
        $repository->add($this->product($repository, 'Oil'));
        $repository->add($this->product($repository, 'Honey'));

        $names = array_map(fn (StockProduct $product) => $product->name(), $repository->list());

        $this->assertSame(['Honey', 'Oil'], $names);
    }

    #[Test]
    public function it_lists_products_case_insensitively(): void
    {
        $repository = $this->repository();
        $repository->add($this->product($repository, 'Oil'));
        $repository->add($this->product($repository, 'apple'));
        $repository->add($this->product($repository, 'Honey'));

        $names = array_map(fn (StockProduct $product) => $product->name(), $repository->list());

        $this->assertSame(['apple', 'Honey', 'Oil'], $names);
    }
}
