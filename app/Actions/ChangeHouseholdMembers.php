<?php

declare(strict_types=1);

namespace App\Actions;

use App\Auth\MemberRoles;
use App\Models\Household;
use App\Models\Member;
use Closure;

/**
 * Applies a status change to the members a household has at this moment and
 * whose active flag matches the one being left. The members the actor may not
 * change on their own are skipped and named. Must run inside the transaction
 * that already locked the household, so the lock order is household first.
 */
class ChangeHouseholdMembers
{
    public function __construct(private readonly MemberRoles $memberRoles) {}

    /**
     * @param  bool  $currentlyActive  The active flag the members to change have now.
     * @param  Closure(Member): bool  $mayChange
     * @param  Closure(Member): mixed  $change
     * @return list<string> The names of the members left untouched.
     */
    public function handle(Household $household, bool $currentlyActive, Closure $mayChange, Closure $change): array
    {
        // Locked after the household and selected by their current household, so one moved
        // elsewhere meanwhile is never touched; a fresh query, as a preloaded relation could be stale.
        $members = Member::query()
            ->where('household_id', $household->getKey())
            ->where('is_active', $currentlyActive)
            ->with('user.administrator')
            ->lockForUpdate()
            ->get();
        $this->memberRoles->preload($members);

        $skippedNames = [];

        foreach ($members as $member) {
            if (! $mayChange($member)) {
                $skippedNames[] = $member->user->name;

                continue;
            }

            $change($member);
        }

        return $skippedNames;
    }
}
