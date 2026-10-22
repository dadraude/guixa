<?php

namespace Tests\Support\Contracts;

use Guixa\Catalog\Domain\Producer;
use Guixa\Catalog\Domain\ProducerRepository;
use Guixa\Catalog\Domain\ValueObject\CutoffRule;
use PHPUnit\Framework\Attributes\Test;

/** Behaviour every ProducerRepository promises; see ProbeNoteRepositoryContract for how it is shared. */
trait ProducerRepositoryContract
{
    abstract protected function repository(): ProducerRepository;

    private function producer(ProducerRepository $repository, string $name = 'Baker'): Producer
    {
        return Producer::create($repository->nextId(), $name, CutoffRule::of(3, '20:00'));
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
    public function it_finds_what_was_added_with_its_cutoff_rule(): void
    {
        $repository = $this->repository();
        $producer = $this->producer($repository);

        $repository->add($producer);
        $found = $repository->get($producer->id);

        $this->assertNotNull($found);
        $this->assertSame($producer->id, $found->id);
        $this->assertSame('Baker', $found->name());
        $this->assertTrue($found->isActive());
        $this->assertSame(3, $found->cutoffRule()->daysBefore);
        $this->assertSame('20:00', $found->cutoffRule()->time);
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        $repository = $this->repository();

        $this->assertNull($repository->get($repository->nextId()));
    }

    #[Test]
    public function it_replaces_a_producer_added_twice(): void
    {
        $repository = $this->repository();
        $producer = $this->producer($repository);
        $repository->add($producer);

        $producer->changeCutoffRule(CutoffRule::of(1, '02:30'));
        $repository->add($producer);

        $found = $repository->get($producer->id);
        $this->assertSame(1, $found?->cutoffRule()->daysBefore);
        $this->assertSame('02:30', $found->cutoffRule()->time);
        $this->assertCount(1, $repository->list());
    }

    #[Test]
    public function it_ignores_changes_to_a_fetched_producer_until_it_is_added_again(): void
    {
        $repository = $this->repository();
        $producer = $this->producer($repository);
        $repository->add($producer);

        $repository->get($producer->id)?->changeCutoffRule(CutoffRule::of(6, '08:00'));

        $this->assertSame(3, $repository->get($producer->id)?->cutoffRule()->daysBefore);
    }

    #[Test]
    public function it_lists_producers_by_name_ignoring_case(): void
    {
        $repository = $this->repository();
        $repository->add($this->producer($repository, 'Oils'));
        $repository->add($this->producer($repository, 'apples'));
        $repository->add($this->producer($repository, 'Baker'));

        $names = array_map(fn (Producer $producer) => $producer->name(), $repository->list());

        $this->assertSame(['apples', 'Baker', 'Oils'], $names);
    }
}
