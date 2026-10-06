<?php

declare(strict_types=1);

namespace App\Http\Controllers\Members;

use App\Actions\DeactivateMember;
use App\Actions\ReactivateMember;
use App\Auth\EmailChangeLevels;
use App\Auth\MembershipRoleProtection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\UpdateMemberRequest;
use App\Models\Household;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request, EmailChangeLevels $emailChangeLevels, MembershipRoleProtection $protection): Response
    {
        $actor = $request->user() ?? abort(403);

        $members = Member::query()
            ->with('user')
            ->get()
            ->sortBy(fn (Member $member) => mb_strtolower($member->user->name))
            ->values()
            ->map(fn (Member $member) => [
                'id' => $member->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'phone' => $member->phone,
                'household_id' => $member->household_id,
                'is_active' => $member->is_active,
                'can_change_email' => $emailChangeLevels->canChange($actor, $member),
                'can_deactivate' => $protection->mayDeactivateMember($actor, $member),
            ]);

        $households = Household::query()
            ->where('is_active', true)
            ->orderBy('number')
            ->get(['id', 'number', 'name']);

        return Inertia::render('members/Index', [
            'members' => $members,
            'households' => $households,
        ]);
    }

    /**
     * The email applies at once, with no confirmation: the membership role
     * fixes details for people who cannot do it themselves. Name and email
     * live on the user, so an administrator profile of the same user follows.
     */
    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($member, $validated) {
            $member->user->forceFill(['name' => $validated['name']]);

            if (isset($validated['email'])) {
                // A self-service change requested earlier must not overwrite this
                // email later through its still-valid link.
                $member->user->forceFill([
                    'email' => $validated['email'],
                    'pending_email' => null,
                    'pending_email_token_hash' => null,
                    'pending_email_expires_at' => null,
                ]);
            }

            $member->user->save();
            $member->update(['phone' => $validated['phone'] ?? null]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member updated.')]);

        return to_route('members.index');
    }

    public function deactivate(Request $request, Member $member, DeactivateMember $deactivateMember, MembershipRoleProtection $protection): RedirectResponse
    {
        abort_unless($protection->mayDeactivateMember($request->user() ?? abort(403), $member), 403);

        $deactivateMember->handle($member);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member deactivated.')]);

        return to_route('members.index');
    }

    public function reactivate(Member $member, ReactivateMember $reactivateMember): RedirectResponse
    {
        $reactivateMember->handle($member);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member reactivated.')]);

        return to_route('members.index');
    }
}
