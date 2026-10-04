<?php

namespace App\Actions;

use App\Auth\SecretHasher;
use App\Mail\EmailChangeMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Stores the new email as pending and queues the link that confirms it. The
 * current email stays in use until the link is opened; asking again replaces
 * the previous link.
 */
class RequestEmailChange
{
    public const int HOURS_VALID = 24;

    public function handle(User $user, string $newEmail): void
    {
        $token = Str::random(64);

        DB::transaction(function () use ($user, $newEmail, $token) {
            $user->forceFill([
                'pending_email' => $newEmail,
                'pending_email_token_hash' => SecretHasher::hash($token),
                'pending_email_expires_at' => now()->addHours(self::HOURS_VALID),
            ])->save();

            Mail::to($newEmail)->queue(new EmailChangeMail(
                url: route('email-change.confirm', ['token' => $token]),
            ));
        });
    }
}
