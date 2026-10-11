<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commission;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * Adds a member to a commission under a lock on its row, the same one DeleteCommission takes.
 * Adding someone already in it changes nothing.
 */
class AddCommissionMember
{
    public function handle(Commission $commission, Member $member): void
    {
        DB::transaction(function () use ($commission, $member): void {
            $locked = Commission::query()->whereKey($commission->getKey())->lockForUpdate()->firstOrFail();

            $locked->members()->syncWithoutDetaching([$member->getKey()]);
        });
    }
}
