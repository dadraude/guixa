<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The member's own household and its members; no other household is exposed.
     */
    public function __invoke(Request $request): Response
    {
        $member = $request->user()?->member;

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
