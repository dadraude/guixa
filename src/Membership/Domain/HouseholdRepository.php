<?php

namespace Guixa\Membership\Domain;

interface HouseholdRepository
{
    /** Serialize household membership writes inside their transaction. */
    public function lockMembershipChanges(): void;

    public function nextId(): string;

    public function save(Household $household): void;

    public function get(string $id): ?Household;

    /**
     * @param  list<string>  $ids
     * @return array<string, Household> by id; unknown ids are left out
     */
    public function getMany(array $ids): array;

    /** @return list<Household> */
    public function list(): array;
}
