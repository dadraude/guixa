<?php

declare(strict_types=1);

namespace App\Actions;

use App\Auth\MembershipRoleProtection;
use App\Models\Commission;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Takes a member out of a commission. The membership role may only do it while the member
 * keeps the role through another commission, so nobody is left without it.
 */
class RemoveCommissionMember
{
    public function __construct(private readonly MembershipRoleProtection $protection) {}

    /**
     * For callers that already locked the member and checked the protection.
     */
    public function handle(Commission $commission, Member $member): void
    {
        $commission->members()->detach($member);
    }

    /**
     * Removes on behalf of an actor, checking the protection on the locked member:
     * locking serializes two removals of the same person from two commissions, so the
     * second sees the first one's detach and cannot leave them without the role.
     *
     * @return bool False when the protection does not allow it; nothing changed then.
     */
    public function handleAs(Commission $commission, Member $member, User $actor): bool
    {
        return DB::transaction(function () use ($commission, $member, $actor): bool {
            $locked = Member::query()->whereKey($member->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->protection->mayRemoveMemberFromCommission($actor, $commission, $locked)) {
                return false;
            }

            $this->handle($commission, $locked);

            return true;
        });
    }
}
