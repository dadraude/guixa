<?php

declare(strict_types=1);

namespace App\Http\Requests\Households;

use App\Auth\MemberRoles;
use App\Auth\MembershipRoleProtection;
use App\Models\Household;
use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Deactivating or reactivating a household asks whether its members follow. Nothing
 * is preselected, so the choice must be explicit whenever there are members it would
 * affect: the active ones when deactivating, the inactive ones when reactivating.
 * Members the action would skip anyway do not count: if nobody would change, there
 * is nothing to choose (DRA-94).
 */
class ChooseHouseholdMembersRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(MemberRoles $memberRoles, MembershipRoleProtection $protection): array
    {
        return [
            // Optional when nobody would change; the action confirms that under the lock.
            'with_members' => [Rule::requiredIf(fn (): bool => $this->hasMembersToAffect($memberRoles, $protection)), 'nullable', 'boolean'],
        ];
    }

    /**
     * @return bool|null Null when no choice was made.
     */
    public function withMembers(): ?bool
    {
        return $this->filled('with_members') ? $this->boolean('with_members') : null;
    }

    private function hasMembersToAffect(MemberRoles $memberRoles, MembershipRoleProtection $protection): bool
    {
        $household = $this->route('household');
        $actor = $this->user();

        if (! $household instanceof Household || $actor === null) {
            return false;
        }

        $members = $household->members()
            ->where('is_active', $household->is_active)
            ->with('user.administrator')
            ->get();

        $memberRoles->preload($members);

        return $members->contains(fn (Member $member): bool => $protection->mayChangeAlongWithHousehold($actor, $member, $household->is_active));
    }
}
