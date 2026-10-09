<?php

declare(strict_types=1);

namespace App\Http\Controllers\Households;

use App\Actions\CreateHousehold;
use App\Actions\DeactivateHousehold;
use App\Actions\ReactivateHousehold;
use App\Auth\MemberRoles;
use App\Auth\MembershipRoleProtection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Households\ChooseHouseholdMembersRequest;
use App\Http\Requests\Households\SaveHouseholdRequest;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HouseholdController extends Controller
{
    public function index(Request $request, MembershipRoleProtection $protection, MemberRoles $memberRoles): Response
    {
        $viewer = $request->user() ?? abort(403);
        // Informational only: an active household with nobody active in a
        // commission is flagged so the membership role can encourage it to
        // take part. Nothing is blocked.
        $households = Household::query()
            ->withCommissionMemberFlag()
            ->with('members.user.administrator')
            ->orderBy('number')
            ->get();

        // Two queries for all the members instead of two per member in the can_deactivate checks.
        $memberRoles->preload($households->flatMap->members);

        $households = $households->map(fn (Household $household) => [
            'id' => $household->id,
            'number' => $household->number,
            'name' => $household->name,
            'is_active' => $household->is_active,
            'without_commission' => $household->is_active && ! $household->has_commission_member,
            'affected_members' => $this->affectedMembers($household, $viewer, $protection),
        ]);

        return Inertia::render('households/Index', [
            'households' => $households,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * The members the next action on the household would reach (the active ones to
     * deactivate, the inactive ones to reactivate), marking those the viewer may not change on their own.
     *
     * @return list<array{name: string, left_out: bool}>
     */
    private function affectedMembers(Household $household, User $viewer, MembershipRoleProtection $protection): array
    {
        $affected = $household->members
            ->filter(fn (Member $member) => $member->is_active === $household->is_active)
            ->map(fn (Member $member) => [
                'name' => $member->user->name,
                'left_out' => $household->is_active
                    ? ! $protection->mayDeactivateAlongWithHousehold($viewer, $member)
                    : ! $protection->mayReactivateMember($viewer, $member),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->all();

        return array_values($affected);
    }

    public function store(SaveHouseholdRequest $request, CreateHousehold $createHousehold): RedirectResponse
    {
        $createHousehold->handle($request->safe()->string('name')->toString());

        return to_route('households.index');
    }

    public function update(SaveHouseholdRequest $request, Household $household): RedirectResponse
    {
        $household->update($request->validated());

        return to_route('households.index');
    }

    public function deactivate(ChooseHouseholdMembersRequest $request, Household $household, DeactivateHousehold $deactivateHousehold): RedirectResponse
    {
        $skippedNames = $deactivateHousehold->handle($household, $request->user() ?? abort(403), $request->withMembers());

        Inertia::flash('toast', ['type' => 'success', 'message' => $skippedNames === []
            ? __('Household deactivated.')
            : __('Household deactivated. These members stay active: :names.', ['names' => implode(', ', $skippedNames)])]);

        return to_route('households.index');
    }

    public function reactivate(ChooseHouseholdMembersRequest $request, Household $household, ReactivateHousehold $reactivateHousehold): RedirectResponse
    {
        $skippedNames = $reactivateHousehold->handle($household, $request->user() ?? abort(403), $request->withMembers());

        Inertia::flash('toast', ['type' => 'success', 'message' => match (true) {
            $skippedNames !== [] => __('Household reactivated. These members stay inactive: :names.', ['names' => implode(', ', $skippedNames)]),
            $request->withMembers() => __('Household and its members reactivated.'),
            default => __('Household reactivated. Its members stay inactive until you reactivate them.'),
        }]);

        return to_route('households.index');
    }
}
