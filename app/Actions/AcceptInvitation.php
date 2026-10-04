<?php

namespace App\Actions;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Turns a pending invitation into a member of its household. A new person
 * gets a user with the chosen password; someone who already has a user (an
 * administrator) gets the member profile attached to it, keeping their
 * password.
 */
class AcceptInvitation
{
    /**
     * @param  string|null  $password  required unless the email already has a user
     *
     * @throws InvitationUnavailable when the invitation cannot be used any more
     * @throws ValidationException when the email already belongs to a member
     */
    public function handle(string $token, string $name, ?string $password): User
    {
        try {
            return DB::transaction(fn () => $this->accept($token, $name, $password));
        } catch (UniqueConstraintViolationException) {
            throw $this->alreadyAMember();
        }
    }

    private function accept(string $token, string $name, ?string $password): User
    {
        $invitation = Invitation::query()->withToken($token)->usable()->lockForUpdate()->first();

        if ($invitation === null) {
            throw new InvitationUnavailable;
        }

        $user = $invitation->existingUser() ?? $this->createUser($invitation, $name, $password);

        if ($user->member()->exists()) {
            throw $this->alreadyAMember();
        }

        $user->update(['name' => $name]);
        $user->member()->create(['household_id' => $invitation->household_id]);

        $invitation->forceFill(['accepted_at' => now()])->save();

        return $user;
    }

    private function createUser(Invitation $invitation, string $name, ?string $password): User
    {
        $password ?? throw new LogicException('A new user needs a password.');

        return User::create(['name' => $name, 'email' => $invitation->email, 'password' => $password]);
    }

    private function alreadyAMember(): ValidationException
    {
        return ValidationException::withMessages([
            'email' => __('This email already belongs to a member.'),
        ]);
    }
}
