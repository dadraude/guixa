<?php

namespace Tests\Support\Contracts;

use Guixa\Shared\Domain\Money;
use Guixa\Stock\Domain\ReturnItem;
use Guixa\Stock\Domain\ReturnItemRepository;
use PHPUnit\Framework\Attributes\Test;

/** Behaviour every ReturnItemRepository promises; see ProbeNoteRepositoryContract for how it is shared. */
trait ReturnItemRepositoryContract
{
    abstract protected function repository(): ReturnItemRepository;

    private function item(ReturnItemRepository $repository, string $name = 'Glass jar'): ReturnItem
    {
        return ReturnItem::create($repository->nextId(), $name, Money::ofCents(50));
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
        $item = $this->item($repository);

        $repository->add($item);
        $found = $repository->get($item->id);

        $this->assertNotNull($found);
        $this->assertSame($item->id, $found->id);
        $this->assertSame('Glass jar', $found->name());
        $this->assertSame(50, $found->amount()->cents);
        $this->assertTrue($found->isActive());
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        $repository = $this->repository();

        $this->assertNull($repository->get($repository->nextId()));
    }

    #[Test]
    public function it_replaces_an_item_added_twice(): void
    {
        $repository = $this->repository();
        $item = $this->item($repository);
        $repository->add($item);

        $item->change('Crate', Money::ofCents(300));
        $item->deactivate();
        $repository->add($item);

        $found = $repository->get($item->id);
        $this->assertSame('Crate', $found?->name());
        $this->assertSame(300, $found->amount()->cents);
        $this->assertFalse($found->isActive());
        $this->assertCount(1, $repository->list());
    }

    #[Test]
    public function it_ignores_changes_to_a_fetched_item_until_it_is_added_again(): void
    {
        $repository = $this->repository();
        $item = $this->item($repository);
        $repository->add($item);

        $repository->get($item->id)?->deactivate();

        $this->assertTrue($repository->get($item->id)?->isActive());
    }

    #[Test]
    public function it_lists_items_by_name(): void
    {
        $repository = $this->repository();
        $repository->add($this->item($repository, 'Crate'));
        $repository->add($this->item($repository, 'Bottle'));

        $names = array_map(fn (ReturnItem $item) => $item->name(), $repository->list());

        $this->assertSame(['Bottle', 'Crate'], $names);
    }

    #[Test]
    public function it_lists_items_case_insensitively(): void
    {
        $repository = $this->repository();
        $repository->add($this->item($repository, 'Crate'));
        $repository->add($this->item($repository, 'bottle'));
        $repository->add($this->item($repository, 'Jar'));

        $names = array_map(fn (ReturnItem $item) => $item->name(), $repository->list());

        $this->assertSame(['bottle', 'Crate', 'Jar'], $names);
    }
}
