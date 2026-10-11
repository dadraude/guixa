<?php

declare(strict_types=1);

namespace App\Actions;

use App\Auth\MemberRoles;
use App\Auth\MembershipRoleProtection;
use App\Models\Member;
use App\Models\User;
use App\Support\MemberStatusChange;
use Illuminate\Support\Facades\DB;

/**
 * A member can be reactivated whatever the state of their household (ADR 0011):
 * if the household is inactive they are treated as a member without a household
 * until it is reactivated. Their commissions were never taken away.
 */
class ReactivateMember
{
    public function __construct(
        private readonly MembershipRoleProtection $protection,
        private readonly MemberRoles $memberRoles,
    ) {}

    public function handle(Member $member): void
    {
        $member->update(['is_active' => true]);
    }

    public function handleAs(Member $member, User $actor): MemberStatusChange
    {
        return DB::transaction(function () use ($member, $actor): MemberStatusChange {
            $locked = Member::query()
                ->with('user.administrator')
                ->whereKey($member->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->memberRoles->forget($locked);

            if (! $this->protection->mayReactivateMember($actor, $locked)) {
                return MemberStatusChange::Forbidden;
            }

            if ($locked->is_active) {
                return MemberStatusChange::AlreadyInThatState;
            }

            $this->handle($locked);

            return MemberStatusChange::Changed;
        });
    }
}
