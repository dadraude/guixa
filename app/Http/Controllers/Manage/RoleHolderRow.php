<?php

namespace App\Http\Controllers\Manage;

use Guixa\Membership\Application\StaffRoleHolderData;
use Illuminate\Support\Facades\Lang;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class RoleHolderRow
{
    public function __construct(
        public string $accountId,
        public string $name,
        public string $email,
        #[LiteralTypeScriptType('Guixa.Membership.Domain.AccountStatus')]
        public string $status,
        public bool $isSelf,
        public bool $canRemove,
        public ?string $removeBlockedReason,
    ) {}

    public static function from(StaffRoleHolderData $holder): self
    {
        return new self(
            $holder->accountId,
            $holder->name,
            $holder->email,
            $holder->status->value,
            $holder->isSelf,
            $holder->canRemove,
            $holder->removeBlockedReason === null ? null : Lang::string($holder->removeBlockedReason->value),
        );
    }
}
