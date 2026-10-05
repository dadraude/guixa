<?php

declare(strict_types=1);

namespace App\Http\Controllers\Commissions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commissions\AddCommissionMemberRequest;
use App\Models\Commission;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;

class CommissionMemberController extends Controller
{
    public function store(AddCommissionMemberRequest $request, Commission $commission): RedirectResponse
    {
        $commission->members()->syncWithoutDetaching([$request->validated('member_id')]);

        return to_route('commissions.index');
    }

    public function destroy(Commission $commission, Member $member): RedirectResponse
    {
        $commission->members()->detach($member);

        return to_route('commissions.index');
    }
}
