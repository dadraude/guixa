<?php

namespace App\Actions;

use App\Auth\SecretHasher;
use App\Models\Household;
use App\Models\Invitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Invites an email to a household. A pending invitation for the same email is
 * replaced, so only the newest link works. The email is queued once the
 * transaction commits.
 */
class InviteToHousehold
{
    public function __construct(private readonly QueueInvitationEmail $queueInvitationEmail) {}

    public function handle(Household $household, string $email, ?string $name): Invitation
    {
        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($household, $email, $name, $token) {
            Invitation::query()
                ->usable()
                ->where('email', $email)
                ->update(['cancelled_at' => now()]);

            return $household->invitations()->create([
                'email' => $email,
                'name' => $name,
                'token_hash' => SecretHasher::hash($token),
                'expires_at' => now()->addDays(Invitation::DAYS_VALID),
            ]);
        });

        $this->queueInvitationEmail->handle($invitation, $token);

        return $invitation;
    }
}
