<?php

namespace Tests\Support\InMemory;

use Guixa\Membership\Domain\Household;
use Guixa\Membership\Domain\HouseholdRepository;

/** @extends InMemoryRepository<Household> */
final class InMemoryHouseholdRepository extends InMemoryRepository implements HouseholdRepository
{
    /** @param Household $item */
    public function save(object $item): void
    {
        parent::save($item);
    }

    public function lockMembershipChanges(): void {}

    public function get(string $id): ?Household
    {
        return $this->find($id);
    }

    public function getMany(array $ids): array
    {
        $found = [];

        foreach ($ids as $id) {
            $household = $this->find($id);

            if ($household !== null) {
                $found[$id] = $household;
            }
        }

        return $found;
    }

    public function list(): array
    {
        return $this->all();
    }

    protected function idOf(object $item): string
    {
        return $item->id;
    }
}
