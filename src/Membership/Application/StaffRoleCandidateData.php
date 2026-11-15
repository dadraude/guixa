<?php

namespace Guixa\Membership\Application;

use Guixa\Membership\Domain\AccountStatus;

final readonly class StaffRoleCandidateData
{
    public function __construct(
        public string $accountId,
        public string $name,
        public string $email,
        public AccountStatus $status,
    ) {}
}
