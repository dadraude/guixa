<?php

namespace Guixa\Shared\Application;

use Guixa\Shared\Domain\Error\NotAllowed;
use Guixa\Shared\Domain\Role;

final readonly class Actor
{
    /** @var list<Role> */
    public array $roles;

    /**
     * @param  array<Role>  $roles
     */
    public function __construct(
        public string $accountId,
        array $roles,
        public ?string $householdId = null,
        public ?string $producerId = null,
    ) {
        $stored = array_filter($roles, fn (Role $role) => $role !== Role::Member);

        // Membership comes from the household link, not from a role someone was given (R35).
        $this->roles = array_values(array_unique(
            $householdId === null ? $stored : [...$stored, Role::Member],
            SORT_REGULAR,
        ));
    }

    public function hasRole(Role $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function hasAnyRole(string ...$roles): bool
    {
        foreach ($roles as $name) {
            $role = Role::tryFrom($name);

            if ($role !== null && $this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function roleNames(): array
    {
        return array_map(fn (Role $role) => $role->value, $this->roles);
    }

    public function assertManager(): void
    {
        if (! $this->hasRole(Role::Manager)) {
            throw NotAllowed::forRole(Role::Manager->value);
        }
    }

    public function assertMemberOf(string $householdId): void
    {
        if (! $this->hasRole(Role::Member)) {
            throw NotAllowed::forRole(Role::Member->value);
        }

        if ($this->householdId !== $householdId) {
            throw NotAllowed::notYours('household');
        }
    }

    public function assertProducerOf(string $producerId): void
    {
        if (! $this->hasRole(Role::Producer)) {
            throw NotAllowed::forRole(Role::Producer->value);
        }

        if ($this->producerId !== $producerId) {
            throw NotAllowed::notYours('producer');
        }
    }
}
