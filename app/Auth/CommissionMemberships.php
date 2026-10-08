<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * Reads the commission_member pivot. Used only by MemberRoles, so the rest of
 * the code never depends on how membership is stored.
 */
class CommissionMemberships
{
    /**
     * @return list<string>
     */
    public function commissionIdsOf(Member $member): array
    {
        return $this->commissionIdsByMember([(string) $member->id])[(string) $member->id] ?? [];
    }

    /**
     * Commission ids keyed by member id, in one query. Members in no
     * commission are absent.
     *
     * @param  list<string>  $memberIds
     * @return array<string, list<string>>
     */
    public function commissionIdsByMember(array $memberIds): array
    {
        $commissionIdsByMember = [];

        $rows = DB::table('commission_member')
            ->whereIn('member_id', $memberIds)
            ->get(['member_id', 'commission_id']);

        foreach ($rows as $row) {
            $commissionIdsByMember[(string) $row->member_id][] = (string) $row->commission_id;
        }

        return $commissionIdsByMember;
    }
}
