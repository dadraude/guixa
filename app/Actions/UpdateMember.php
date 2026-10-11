<?php

declare(strict_types=1);

namespace App\Actions;

use App\Auth\UserSessions;
use App\Models\Member;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Name and email live on the user, so an administrator profile of the same
 * user follows. A null email means the email is left as it is. A different
 * email ends everything the old address could still use: unused magic links,
 * the remember cookie and every open session.
 */
class UpdateMember
{
    public function __construct(private readonly UserSessions $userSessions) {}

    /**
     * @throws ValidationException
     */
    public function handle(Member $member, string $name, ?string $email, ?string $phone): void
    {
        try {
            $this->update($member, $name, $email, $phone);
        } catch (UniqueConstraintViolationException $exception) {
            // Lost the race for the address after the availability check; the transaction is rolled back.
            throw ValidationException::withMessages(['email' => __('This email already belongs to a member.')]);
        }
    }

    private function update(Member $member, string $name, ?string $email, ?string $phone): void
    {
        DB::transaction(function () use ($member, $name, $email, $phone) {
            $member->user->forceFill(['name' => $name]);

            if ($email !== null) {
                // A self-service change requested earlier must not overwrite this
                // email later through its still-valid link.
                $member->user->forceFill([
                    'email' => $email,
                    'pending_email' => null,
                    'pending_email_token_hash' => null,
                    'pending_email_expires_at' => null,
                ]);
            }

            $emailChanged = $member->user->isDirty('email');

            $member->user->save();

            if ($emailChanged) {
                $member->user->magicLinks()->whereNull('used_at')->delete();
                $this->userSessions->end($member->user);
            }

            $member->update(['phone' => $phone]);
        });
    }
}
