<?php

declare(strict_types=1);

namespace App\Http\Controllers\Directory;

use App\Auth\ActiveProfile;
use App\Auth\MemberRoles;
use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contact details are shared only with people doing commission work: those
 * acting as administrators and members in at least one commission. Every other
 * member sees their own household only.
 */
class DirectoryController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, ActiveProfile $activeProfile, MemberRoles $memberRoles): Response
    {
        $ownMember = $user->member;
        $isFull = $activeProfile->isActingAsAdministrator($user)
            || ($ownMember !== null && $memberRoles->isInAnyCommission($ownMember));

        $households = Household::query()
            ->when(! $isFull, fn ($query) => $query->whereKey($ownMember?->household_id))
            ->with(['members' => fn ($query) => $query->where('is_active', true)->with('user')])
            ->orderBy('number')
            ->get();

        return Inertia::render('directory/Index', [
            'isFull' => $isFull,
            'households' => $households->map(fn (Household $household) => [
                'id' => $household->id,
                'number' => $household->number,
                'name' => $household->name,
                'is_active' => $household->is_active,
                'members' => $household->members
                    ->sortBy(fn (Member $member) => mb_strtolower($member->user->name))
                    ->values()
                    ->map(fn (Member $member) => [
                        'id' => $member->id,
                        'name' => $member->user->name,
                        'email' => $member->user->email,
                        'phone' => $member->phone,
                    ]),
            ]),
        ]);
    }
}
