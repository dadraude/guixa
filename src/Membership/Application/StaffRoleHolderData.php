<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountStatus;

final readonly class StaffRoleHolderData
{
    public function __construct(
        public string $accountId,
        public string $name,
        public string $email,
        public AccountStatus $status,
        public bool $isSelf,
        public bool $canRemove,
        public ?RemoveBlockedReason $removeBlockedReason,
    ) {}
}
