<?php

namespace App\Actions;

use App\Auth\SecretHasher;
use App\Models\Invitation;
use Illuminate\Support\Str;

/**
 * Gives an open invitation a new link and a fresh expiry, so the previous link
 * stops working and an expired invitation becomes valid again.
 */
class ResendInvitation
{
    public function __construct(private readonly QueueInvitationEmail $queueInvitationEmail) {}

    public function handle(Invitation $invitation): void
    {
        $token = Str::random(64);

        $invitation->forceFill([
            'token_hash' => SecretHasher::hash($token),
            'expires_at' => now()->addDays(Invitation::DAYS_VALID),
        ])->save();

        $this->queueInvitationEmail->handle($invitation, $token);
    }
}
