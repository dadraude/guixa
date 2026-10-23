<?php

namespace Tests\Support\Contracts;

use DateTimeImmutable;
use Guixa\Membership\Domain\Household;
use Guixa\Membership\Domain\HouseholdRepository;
use PHPUnit\Framework\Attributes\Test;

trait HouseholdRepositoryContract
{
    abstract protected function repository(): HouseholdRepository;

    #[Test]
    public function it_finds_what_was_saved(): void
    {
        $repository = $this->repository();
        $household = Household::create($repository->nextId(), 'Casa Roca');

        $repository->save($household);

        $found = $repository->get($household->id);
        $this->assertSame('Casa Roca', $found?->name);
        $this->assertTrue($found->isActive());
    }

    #[Test]
    public function it_returns_null_for_an_unknown_id(): void
    {
        $repository = $this->repository();

        $this->assertNull($repository->get($repository->nextId()));
    }

    #[Test]
    public function it_replaces_a_household_saved_twice_and_keeps_deactivation(): void
    {
        $repository = $this->repository();
        $id = $repository->nextId();
        $repository->save(Household::create($id, 'Casa Roca'));

        $repository->save(Household::restore($id, 'Casa Pons', new DateTimeImmutable('2026-10-01 12:00:00+00:00')));

        $found = $repository->get($id);
        $this->assertSame('Casa Pons', $found?->name);
        $this->assertFalse($found->isActive());
        $this->assertCount(1, $repository->list());
    }

    #[Test]
    public function it_lists_every_household(): void
    {
        $repository = $this->repository();
        $repository->save(Household::create($repository->nextId(), 'A'));
        $repository->save(Household::create($repository->nextId(), 'B'));

        $this->assertCount(2, $repository->list());
    }

    #[Test]
    public function it_finds_several_households_by_id_and_leaves_unknown_ones_out(): void
    {
        $repository = $this->repository();
        $first = Household::create($repository->nextId(), 'A');
        $second = Household::create($repository->nextId(), 'B');
        $repository->save($first);
        $repository->save($second);
        $repository->save(Household::create($repository->nextId(), 'C'));
        $unknown = $repository->nextId();

        $found = $repository->getMany([$second->id, $unknown, $first->id]);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], array_keys($found));
        $this->assertSame('A', $found[$first->id]->name);
        $this->assertSame([], $repository->getMany([]));
    }
}
