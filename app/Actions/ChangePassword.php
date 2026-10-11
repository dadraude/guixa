<?php

declare(strict_types=1);

namespace App\Actions;

use App\Auth\UserSessions;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;

class ChangePassword
{
    public function __construct(private readonly UserSessions $sessions) {}

    public function handle(User $user, string $password, Session $session): void
    {
        $changed = DB::transaction(function () use ($user, $password): User {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->update(['password' => $password]);
            $this->sessions->end($locked);

            return $locked;
        });

        // Keep the version this change generated: a later revocation must still end this session.
        $user->setRawAttributes($changed->getAttributes(), sync: true);
        $this->sessions->stamp($session, $user);
    }
}
