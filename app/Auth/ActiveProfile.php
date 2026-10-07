<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * The profile a user is acting as, kept in the session. Only the active
 * profile grants permissions, so someone with both profiles must choose one
 * after signing in and can switch later without signing in again.
 */
class ActiveProfile
{
    private const string SESSION_KEY = 'active_profile';

    public function __construct(
        private readonly Session $session,
        private readonly UsableMember $usableMember,
    ) {}

    /**
     * The profiles the user may act as, member first. A member profile with
     * no household and no commission can do nothing, so it is not available
     * to someone who is also an administrator; that is recomputed on every
     * request, so gaining or losing a household or commission applies at once.
     *
     * @return list<Profile>
     */
    public function available(User $user): array
    {
        return array_values(array_filter([
            $this->isMemberProfileAvailable($user) ? Profile::Member : null,
            $user->hasActiveAdministratorProfile() ? Profile::Administrator : null,
        ]));
    }

    private function isMemberProfileAvailable(User $user): bool
    {
        if (! $user->hasActiveMemberProfile()) {
            return false;
        }

        // Without an administrator profile the waiting member profile is all
        // the person has, so it stays available.
        if (! $user->hasActiveAdministratorProfile()) {
            return true;
        }

        $member = $user->member;

        if ($member === null) {
            return false;
        }

        return $this->usableMember->check($member);
    }

    /**
     * The profile in use: the only one available, or the chosen one when
     * there are two. Null while a person with both has not chosen yet. A
     * user with no active profile acts as a member without a household.
     */
    public function current(User $user): ?Profile
    {
        $available = $this->available($user);

        if (count($available) < 2) {
            return $this->onlyAvailable($available);
        }

        $stored = $this->session->get(self::SESSION_KEY);
        $chosen = Profile::tryFrom(is_string($stored) ? $stored : '');

        return in_array($chosen, $available, true) ? $chosen : null;
    }

    /**
     * Acting as administrator because it is the only usable profile is
     * remembered, so a household or commission assigned later in the session
     * adds the switcher without forcing the choice screen on the person.
     *
     * @param  list<Profile>  $available
     */
    private function onlyAvailable(array $available): Profile
    {
        $only = $available[0] ?? Profile::Member;

        if ($only === Profile::Administrator) {
            $this->choose($only);
        }

        return $only;
    }

    public function isChoicePending(User $user): bool
    {
        return $this->current($user) === null;
    }

    public function choose(Profile $profile): void
    {
        $this->session->put(self::SESSION_KEY, $profile->value);
    }

    public function isActingAsAdministrator(User $user): bool
    {
        return $this->current($user) === Profile::Administrator;
    }

    /**
     * Whether the user is the owner acting as administrator, the only one who
     * manages the other administrators.
     */
    public function isActingAsOwner(User $user): bool
    {
        return $this->isActingAsAdministrator($user) && $user->administrator?->is_owner === true;
    }
}
