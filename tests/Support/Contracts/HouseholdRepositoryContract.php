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
}
