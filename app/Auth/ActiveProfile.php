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

    public function __construct(private readonly Session $session) {}

    /**
     * The profiles the user may act as, member first.
     *
     * @return list<Profile>
     */
    public function available(User $user): array
    {
        return array_values(array_filter([
            $user->hasActiveMemberProfile() ? Profile::Member : null,
            $user->hasActiveAdministratorProfile() ? Profile::Administrator : null,
        ]));
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
            return $available[0] ?? Profile::Member;
        }

        $stored = $this->session->get(self::SESSION_KEY);
        $chosen = Profile::tryFrom(is_string($stored) ? $stored : '');

        return in_array($chosen, $available, true) ? $chosen : null;
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
