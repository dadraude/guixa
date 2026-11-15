<?php

namespace App\Http\Controllers\Manage;

use Guixa\Membership\Application\StaffRoleGroupData;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final readonly class RoleGroupRow
{
    /**
     * @param  list<RoleHolderRow>  $holders
     */
    public function __construct(
        #[LiteralTypeScriptType('Guixa.Shared.Domain.Role')]
        public string $role,
        public array $holders,
    ) {}

    public static function from(StaffRoleGroupData $group): self
    {
        return new self($group->role->value, array_map(RoleHolderRow::from(...), $group->holders));
    }
}
