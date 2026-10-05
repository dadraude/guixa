<?php

namespace App\Actions;

use App\Models\Household;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * A household leaves the cooperative with all its members. Its number, data
 * and history are kept.
 */
class DeactivateHousehold
{
    public function __construct(private readonly DeactivateMember $deactivateMember) {}

    public function handle(Household $household): void
    {
        DB::transaction(function () use ($household) {
            $household->forceFill(['is_active' => false])->save();
            $household->members->each(fn (Member $member) => $this->deactivateMember->handle($member));
        });
    }
}
