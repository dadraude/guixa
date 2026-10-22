<?php

namespace Guixa\Membership\Domain;

interface HouseholdRepository
{
    public function nextId(): string;

    public function save(Household $household): void;

    public function get(string $id): ?Household;

    /** @return list<Household> */
    public function list(): array;
}
