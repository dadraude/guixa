<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\ActiveProfile;
use App\Auth\MemberCommissionList;
use App\Auth\Profile;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The member's own household (if they have one) and its members, and their
     * commissions with the roles those give them; no other household is
     * exposed, and none at all while acting as administrator.
     */
    public function __invoke(#[CurrentUser] User $user, ActiveProfile $activeProfile, MemberCommissionList $commissionList): Response
    {
        $member = $activeProfile->current($user) === Profile::Member ? $user->member : null;
        $household = $member?->household;

        return Inertia::render('Dashboard', [
            'household' => $household === null ? null : $this->householdOf($household),
            'commissions' => $member === null ? [] : $commissionList->of($member),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function householdOf(Household $household): array
    {
        return [
            'number' => $household->number,
            'name' => $household->name,
            'members' => $household->members()
                ->with('user')
                ->get()
                ->sortBy(fn (Member $housemate) => $housemate->user->name)
                ->map(fn (Member $housemate) => ['id' => $housemate->id, 'name' => $housemate->user->name])
                ->values(),
        ];
    }
}
