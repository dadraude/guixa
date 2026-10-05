<?php

namespace App\Http\Controllers\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\UpdateMemberRequest;
use App\Models\Household;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(): Response
    {
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

        $member->user->update(['name' => $validated['name'], 'email' => $validated['email']]);
        $member->update(['phone' => $validated['phone'] ?? null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member updated.')]);

        return to_route('members.index');
    }
}
