<?php

namespace Tests\Support\Contracts;

use Guixa\Catalog\Domain\Product;
use Guixa\Catalog\Domain\ProductRepository;
use Guixa\Shared\Domain\Money;
use PHPUnit\Framework\Attributes\Test;

/** Behaviour every ProductRepository promises; see ProbeNoteRepositoryContract for how it is shared. */
trait ProductRepositoryContract
{
    abstract protected function repository(): ProductRepository;

    /** A producer id the repository accepts (the database needs the producer to exist). */
    abstract protected function newProducerId(): string;

    private function product(ProductRepository $repository, string $producerId, string $name = 'Bread', ?string $containers = '2.000'): Product
    {
        return Product::create($repository->nextId(), $producerId, $name, 'loaf', Money::ofCents(350), false, $containers);
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
        $producerId = $this->newProducerId();
        $product = $this->product($repository, $producerId);

        $repository->add($product);
        $found = $repository->get($product->id);

        $this->assertNotNull($found);
        $this->assertSame($product->id, $found->id);
        $this->assertSame($producerId, $found->producerId);
        $this->assertSame('Bread', $found->name());
        $this->assertSame('loaf', $found->unit());
        $this->assertSame(350, $found->price()->cents);
        $this->assertFalse($found->isVariable());
        $this->assertSame('2.000', $found->containersPerUnit()?->toString());
        $this->assertTrue($found->isActive());
    }

    #[Test]
    public function it_keeps_a_missing_containers_per_unit_as_null(): void
    {
        $repository = $this->repository();
        $product = $this->product($repository, $this->newProducerId(), containers: null);

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
        $producerId = $this->newProducerId();
        $product = $this->product($repository, $producerId);
        $repository->add($product);

        $product->change('Rye bread', 'kg', Money::ofCents(1200), true, null);
        $product->deactivate();
        $repository->add($product);

        $found = $repository->get($product->id);
        $this->assertSame('Rye bread', $found?->name());
        $this->assertSame(1200, $found->price()->cents);
        $this->assertTrue($found->isVariable());
        $this->assertNull($found->containersPerUnit());
        $this->assertFalse($found->isActive());
        $this->assertCount(1, $repository->listByProducer($producerId));
    }

    #[Test]
    public function it_ignores_changes_to_a_fetched_product_until_it_is_added_again(): void
    {
        $repository = $this->repository();
        $product = $this->product($repository, $this->newProducerId());
        $repository->add($product);

        $repository->get($product->id)?->deactivate();

        $this->assertTrue($repository->get($product->id)?->isActive());
    }

    #[Test]
    public function it_lists_only_the_products_of_one_producer_by_name_ignoring_case(): void
    {
        $repository = $this->repository();
        $baker = $this->newProducerId();
        $cheesemaker = $this->newProducerId();
        $repository->add($this->product($repository, $baker, 'Rye'));
        $repository->add($this->product($repository, $baker, 'apple pie'));
        $repository->add($this->product($repository, $baker, 'Bread'));
        $repository->add($this->product($repository, $cheesemaker, 'Cheese'));

        $names = array_map(fn (Product $product) => $product->name(), $repository->listByProducer($baker));

        $this->assertSame(['apple pie', 'Bread', 'Rye'], $names);
        $this->assertSame([], $repository->listByProducer($this->newProducerId()));
    }
}
