<?php

declare(strict_types=1);

namespace App\Http\Controllers\Commissions;

use App\Auth\MemberRoles;
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
    public function index(Request $request, MemberRoles $memberRoles): Response
    {
        $canManage = $request->user()?->can('manage-membership') === true;
        $rolesByCommission = $memberRoles->byCommission();
        $optionsOf = fn (Commission $commission) => array_map(
            fn (Role $role) => $role->toOption(),
            $rolesByCommission[$commission->id] ?? [],
        );
        $ownMember = $request->user()?->member;

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
                ]),
            'mine' => $ownMember === null ? [] : $memberRoles->commissionsOf($ownMember),
            'availableRoles' => array_map(fn (Role $role) => $role->toOption(), Role::cases()),
            'canAssignRoles' => $request->user()?->can('assign-roles') === true,
            'canManage' => $canManage,
            'candidates' => $canManage ? $this->namesOf(Member::query()->with('user')->where('is_active', true)->get()) : [],
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

    public function destroy(Commission $commission): RedirectResponse
    {
        if ($commission->members()->exists()) {
            throw ValidationException::withMessages(['commission' => __('A commission with members cannot be deleted.')]);
        }

        $commission->delete();

        return to_route('commissions.index');
    }

    /**
     * @param  iterable<int, Member>  $members
     * @return array<int, array{id: string, name: string}>
     */
    private function namesOf(iterable $members): array
    {
        return collect($members)
            ->map(fn (Member $member) => ['id' => $member->id, 'name' => $member->user->name])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
