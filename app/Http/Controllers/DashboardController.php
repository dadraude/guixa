<?php

namespace App\Http\Controllers;

use App\Auth\ActiveProfile;
use App\Auth\Profile;
use App\Models\Member;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The member's own household and its members; no other household is
     * exposed, and none at all while acting as administrator.
     */
    public function __invoke(Request $request, ActiveProfile $activeProfile): Response
    {
        $user = $request->user();
        $member = $activeProfile->current($user) === Profile::Member ? $user->member : null;

        return Inertia::render('Dashboard', [
            'household' => $member === null ? null : $this->householdOf($member),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function householdOf(Member $member): array
    {
        $household = $member->household;

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
