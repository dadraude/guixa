<?php

namespace App\Http\Controllers\Manage;

use Guixa\Membership\Application\StaffRoleCandidateData;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class RoleCandidateRow
{
    public function __construct(
        public string $accountId,
        public string $name,
        public string $email,
        #[LiteralTypeScriptType('Guixa.Membership.Domain.AccountStatus')]
        public string $status,
    ) {}

    public static function from(StaffRoleCandidateData $candidate): self
    {
        return new self($candidate->accountId, $candidate->name, $candidate->email, $candidate->status->value);
    }
}
