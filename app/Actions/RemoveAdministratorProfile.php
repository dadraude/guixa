<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Administrator;

/**
 * Removes the administrator profile. Nothing else changes: the person stays an
 * active member and what they did as administrator is kept. Their open
 * administrator sessions end on their next request, because the acting
 * profile is recomputed from the database every time (see ActiveProfile).
 */
class RemoveAdministratorProfile
{
    public function handle(Administrator $administrator): void
    {
        // The last guard: the owner can never lose the profile from the app.
        abort_if($administrator->is_owner, 403);

        $administrator->delete();
    }
}
