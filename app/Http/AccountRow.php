<?php

namespace App\Http;

use Guixa\Membership\Application\AccountSummary;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class AccountRow
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public string $accountId,
        public string $name,
        public string $email,
        #[LiteralTypeScriptType('Guixa.Shared.Domain.Role[]')]
        public array $roles,
        public ?string $householdId,
        public ?string $householdName,
        public bool $active,
        public bool $invitationPending,
    ) {}

    public static function from(AccountSummary $account): self
    {
        return new self(
            $account->accountId,
            $account->name,
            $account->email,
            $account->roles,
            $account->householdId,
            $account->householdName,
            $account->active,
            $account->invitationPending,
        );
    }
}
