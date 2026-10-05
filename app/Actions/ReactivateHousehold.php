<?php

namespace App\Actions;

use App\Models\Household;

/**
 * The household comes back with the same number and data. Its members stay
 * inactive until they are reactivated one by one.
 */
class ReactivateHousehold
{
    public function handle(Household $household): void
    {
        $household->forceFill(['is_active' => true])->save();
    }
}
