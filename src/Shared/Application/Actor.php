<?php

namespace Guixa\Shared\Application;

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

    public static function manager(string $accountId): self
    {
        return new self($accountId, [Role::Manager]);
    }

    public static function memberOf(string $accountId, string $householdId): self
    {
        return new self($accountId, [], $householdId);
    }

    public static function producer(string $accountId, string $producerId): self
    {
        return new self($accountId, [Role::Producer], producerId: $producerId);
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
}
