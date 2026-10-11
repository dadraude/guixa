<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Commission;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * Deleting a commission removes its role assignments, which only administrators do (the assign-roles gate), so a
 * commission holding any role is reserved to them; the membership role deletes the rest.
 */
class CommissionDeletion
{
    public function __construct(private readonly MemberRoles $memberRoles, private readonly Gate $gate) {}

    public function mayDelete(User $actor, Commission $commission): bool
    {
        return $this->mayDeleteHolding($actor, $this->memberRoles->forCommissions([$commission->id]));
    }

    /**
     * For pages that already know the roles of every commission and must not ask again.
     *
     * @param  list<Role>  $commissionRoles
     */
    public function mayDeleteHolding(User $actor, array $commissionRoles): bool
    {
        return $commissionRoles === [] || $this->gate->forUser($actor)->allows('assign-roles');
    }
}
