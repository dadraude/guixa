<?php

namespace Guixa\Membership\Application;

final readonly class AccountSummary
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public string $accountId,
        public string $name,
        public string $email,
        public array $roles,
        public ?string $householdId,
        public ?string $householdName,
        public bool $active,
        public bool $invitationPending,
    ) {}
}
