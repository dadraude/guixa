<?php

declare(strict_types=1);

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
            // Locks the household row so concurrent household changes are serialized.
            $locked = Household::query()->whereKey($household->getKey())->lockForUpdate()->firstOrFail();

            // A stale second request must not deactivate members reactivated since.
            if (! $locked->is_active) {
                return;
            }

            $locked->forceFill(['is_active' => false])->save();
            // Fresh query: a preloaded relation could miss members added meanwhile.
            $locked->members()->get()->each(fn (Member $member) => $this->deactivateMember->handle($member));
        });
    }
}
