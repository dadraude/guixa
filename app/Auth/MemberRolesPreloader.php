<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * Works out the roles of a whole page of members with two queries instead of
 * two per member, and hands them to MemberRoles to remember. Inactive members
 * have no roles and need no query.
 */
class MemberRolesPreloader
{
    public function __construct(private readonly MemberRoles $memberRoles) {}

    /**
     * @param  iterable<Member>  $members
     */
    public function preload(iterable $members): void
    {
        $pendingIds = [];

        foreach ($members as $member) {
            if ($member->is_active) {
                $pendingIds[] = $member->id;
            }
        }

        if ($pendingIds === []) {
            return;
        }

        $rolesByCommission = $this->memberRoles->byCommission();
        $commissionIdsByMember = [];

        foreach (DB::table('commission_member')->whereIn('member_id', $pendingIds)->get(['member_id', 'commission_id']) as $row) {
            $commissionIdsByMember[(string) $row->member_id][] = (string) $row->commission_id;
        }

        $rolesByMember = [];

        foreach ($pendingIds as $memberId) {
            $roles = [];

            foreach ($commissionIdsByMember[$memberId] ?? [] as $commissionId) {
                $roles = [...$roles, ...($rolesByCommission[$commissionId] ?? [])];
            }

            $rolesByMember[$memberId] = array_values(array_unique($roles, SORT_REGULAR));
        }

        $this->memberRoles->remember($rolesByMember);
    }
}
