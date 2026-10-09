<?php

declare(strict_types=1);

namespace App\Http\Controllers\Commissions;

use App\Auth\CommissionRoles;
use App\Auth\MemberCommissionList;
use App\Auth\MembershipRoleProtection;
use App\Auth\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commissions\SaveCommissionRequest;
use App\Models\Commission;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CommissionController extends Controller
{
    /**
     * Everyone who is in the cooperative sees names only, never contact details.
     */
    public function index(Request $request, CommissionRoles $commissionRoles, MemberCommissionList $commissionList, MembershipRoleProtection $protection): Response
    {
        $viewer = $request->user() ?? abort(403);
        $canManage = $viewer->can('manage-membership') === true;
        $rolesByCommission = $commissionRoles->byCommission();
        $optionsOf = fn (Commission $commission) => array_map(
            fn (Role $role) => $role->toOption(),
            $rolesByCommission[$commission->id] ?? [],
        );
        $ownMember = $viewer->member;

        return Inertia::render('commissions/Index', [
            'commissions' => Commission::query()
                ->with('members.user')
                ->orderBy('name')
                ->get()
                ->map(fn (Commission $commission) => [
                    'id' => $commission->id,
                    'name' => $commission->name,
                    'members' => $this->namesOf($commission->members),
                    'roles' => $optionsOf($commission),
                    'can_remove_members' => $protection->mayRemoveFromCommissionHolding($viewer, $rolesByCommission[$commission->id] ?? []),
                    'can_delete' => $protection->mayRemoveFromCommissionHolding($viewer, $rolesByCommission[$commission->id] ?? []),
                ]),
            'mine' => $ownMember === null ? [] : $commissionList->of($ownMember),
            'availableRoles' => array_map(fn (Role $role) => $role->toOption(), Role::cases()),
            'canAssignRoles' => $viewer->can('assign-roles') === true,
            'canManage' => $canManage,
            'candidates' => $canManage ? $this->namesOf(Member::query()->with('user')->get()) : [],
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(SaveCommissionRequest $request): RedirectResponse
    {
        Commission::create($request->validated());

        return to_route('commissions.index');
    }

    public function update(SaveCommissionRequest $request, Commission $commission): RedirectResponse
    {
        $commission->update($request->validated());

        return to_route('commissions.index');
    }

    public function destroy(Request $request, Commission $commission, MembershipRoleProtection $protection): RedirectResponse
    {
        abort_unless($protection->mayDeleteCommission($request->user() ?? abort(403), $commission), 403);

        if ($commission->members()->exists()) {
            throw ValidationException::withMessages(['commission' => __('A commission with members cannot be deleted.')]);
        }

        $commission->delete();

        return to_route('commissions.index');
    }

    /**
     * @param  iterable<int, Member>  $members
     * @return array<int, array{id: string, name: string, is_active: bool}>
     */
    private function namesOf(iterable $members): array
    {
        return collect($members)
            ->map(fn (Member $member) => ['id' => $member->id, 'name' => $member->user->name, 'is_active' => $member->is_active])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
