<?php

declare(strict_types=1);

namespace App\Actions;

use App\Auth\CommissionDeletion;
use App\Models\Commission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a commission under a lock on its row, the same one AddCommissionMember takes, so a
 * member added at the same time either shows up here and stops the deletion or finds the
 * commission gone, and is never removed silently by the cascade.
 */
class DeleteCommission
{
    public function __construct(private readonly CommissionDeletion $deletion) {}

    /**
     * @return bool False when the actor may not delete it; nothing changed then.
     *
     * @throws ValidationException When the commission has members.
     */
    public function handle(Commission $commission, User $actor): bool
    {
        return DB::transaction(function () use ($commission, $actor): bool {
            $locked = Commission::query()->whereKey($commission->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->deletion->mayDelete($actor, $locked)) {
                return false;
            }

            if ($locked->members()->exists()) {
                throw ValidationException::withMessages(['commission' => __('A commission with members cannot be deleted.')]);
            }

            $locked->delete();

            return true;
        });
    }
}
