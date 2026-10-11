<?php

declare(strict_types=1);

namespace App\Http\Controllers\Commissions;

use App\Actions\RemoveCommissionMember;
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

    public function destroy(Request $request, Commission $commission, Member $member, RemoveCommissionMember $removeCommissionMember): RedirectResponse
    {
        $actor = $request->user() ?? abort(403);

        abort_unless($removeCommissionMember->handleAs($commission, $member, $actor), 403);

        return to_route('commissions.index');
    }
}
