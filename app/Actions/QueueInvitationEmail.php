<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use Illuminate\Support\Facades\Mail;

/**
 * Queues the email with the invitation link. The mail is queued after commit,
 * so it is safe to call inside a transaction.
 */
class QueueInvitationEmail
{
    public function handle(Invitation $invitation, string $token): void
    {
        Mail::to($invitation->email)->queue(new InvitationMail(
            url: route('invitations.show', ['token' => $token]),
            householdName: $invitation->household->name,
            name: $invitation->name,
        ));
    }
}
