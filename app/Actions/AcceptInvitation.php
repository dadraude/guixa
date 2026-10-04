<?php

namespace App\Actions;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a pending invitation into a member of its household. Attaching a
 * member profile to an existing user (an administrator) is not supported yet,
 * so an email that already has a user is refused and the invitation stays
 * pending.
 */
class AcceptInvitation
{
    /**
     * @throws InvitationUnavailable when the invitation cannot be used any more
     * @throws ValidationException when a user already exists for the email
     */
    public function handle(string $token, string $name, string $password): User
    {
        try {
            return DB::transaction(fn () => $this->accept($token, $name, $password));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => __('This email already has an account, so the invitation cannot be accepted. Ask the membership commission for help.'),
            ]);
        }
    }

    private function accept(string $token, string $name, string $password): User
    {
        $invitation = Invitation::query()->withToken($token)->usable()->lockForUpdate()->first();

        if ($invitation === null) {
            throw new InvitationUnavailable;
        }

        $user = User::create(['name' => $name, 'email' => $invitation->email, 'password' => $password]);
        $user->member()->create(['household_id' => $invitation->household_id]);

        $invitation->forceFill(['accepted_at' => now()])->save();

        return $user;
    }
}
