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

class CommissionMemberController extends Controller
{
    public function store(AddCommissionMemberRequest $request, Commission $commission): RedirectResponse
    {
        $commission->members()->syncWithoutDetaching([$request->validated('member_id')]);

        return to_route('commissions.index');
    }

    public function destroy(Request $request, Commission $commission, Member $member, MembershipRoleProtection $protection): RedirectResponse
    {
        abort_unless($protection->mayRemoveFromCommission($request->user() ?? abort(403), $commission), 403);

        $commission->members()->detach($member);

        return to_route('commissions.index');
    }
}
