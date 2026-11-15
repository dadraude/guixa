<?php

namespace Guixa\Membership\Application;

use Guixa\Shared\Domain\Role;

final readonly class StaffRoleGroupData
{
    /** @param list<StaffRoleHolderData> $holders */
    public function __construct(
        public Role $role,
        public array $holders,
    ) {}
}
