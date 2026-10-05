<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invitation;

/**
 * Makes an invitation unusable. The row is kept so there is a record of it.
 */
class CancelInvitation
{
    public function handle(Invitation $invitation): void
    {
        $invitation->forceFill(['cancelled_at' => now()])->save();
    }
}
