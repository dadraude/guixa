<?php

declare(strict_types=1);

namespace App\Http\Controllers\Commissions;

use App\Auth\MembershipRoleProtection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commissions\AddCommissionMemberRequest;
use App\Models\Commission;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommissionMemberController extends Controller
{
    public function store(AddCommissionMemberRequest $request, Commission $commission): RedirectResponse
    {
        $commission->members()->syncWithoutDetaching([$request->validated('member_id')]);

        return to_route('commissions.index');
    }

    public function destroy(Request $request, Commission $commission, Member $member, MembershipRoleProtection $protection): RedirectResponse
    {
        $actor = $request->user() ?? abort(403);

        // Locking the member serializes two removals of the same person from two commissions:
        // the second sees the first one's detach and cannot leave them without the role.
        $removed = DB::transaction(function () use ($actor, $commission, $member, $protection): bool {
            $locked = Member::query()->whereKey($member->getKey())->lockForUpdate()->firstOrFail();

            if (! $protection->mayRemoveMemberFromCommission($actor, $commission, $locked)) {
                return false;
            }

            $commission->members()->detach($locked);

            return true;
        });

        abort_unless($removed, 403);

        return to_route('commissions.index');
    }
}
