<?php

declare(strict_types=1);

namespace App\Actions;

use App\Auth\MemberRoles;
use App\Auth\MembershipRoleProtection;
use App\Auth\UserSessions;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A member can no longer sign in with any profile; nothing else changes: they
 * keep their household, commissions and roles (ADR 0011). Open sessions and
 * remember cookies end for good, so reactivating the person does not revive them.
 */
class DeactivateMember
{
    public function __construct(
        private readonly MembershipRoleProtection $protection,
        private readonly MemberRoles $memberRoles,
        private readonly UserSessions $userSessions,
    ) {}

    /**
     * For callers that already locked the member and checked the protection (DeactivateHousehold).
     */
    public function handle(Member $member): void
    {
        $member->update(['is_active' => false]);
        $this->userSessions->end($member->user);
    }

    /**
     * Deactivates on behalf of an actor, re-checking the protection on the locked member:
     * the administrator profile or a membership-role commission may have been added since
     * the caller's own check.
     *
     * Race safety: AddAdministratorProfile::add locks this same members row (lockForUpdate)
     * and checks is_active under it, so both writes are serialized on the row; under
     * Postgres READ COMMITTED the administrators row is visible to the load made after
     * acquiring the lock. Commission writes need no lock because inactive members may
     * join commissions, so any interleaving equals an allowed serial order.
     *
     * @return bool False when the protection no longer allows it; nothing changed then.
     */
    public function handleAs(Member $member, User $actor): bool
    {
        return DB::transaction(function () use ($member, $actor): bool {
            $locked = Member::query()
                ->with('user.administrator')
                ->whereKey($member->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // The roles are remembered per request: forget what the caller's check read.
            $this->memberRoles->forget($locked);

            if (! $this->protection->mayDeactivateMember($actor, $locked)) {
                return false;
            }

            $this->handle($locked);

            return true;
        });
    }
}
