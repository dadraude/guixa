<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single rule for when a user's email change is pending: its link can still
 * confirm it, so the expiry is strictly in the future, as ConfirmEmailChange checks.
 * The method and the scope are twins; keep them in step.
 *
 * @mixin User
 */
trait HasPendingEmailChange
{
    public function hasPendingEmailChange(): bool
    {
        return $this->pending_email !== null && $this->pending_email_expires_at?->isFuture() === true;
    }

    /**
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function withPendingEmailChange(Builder $query): void
    {
        $query->whereNotNull('pending_email')->where('pending_email_expires_at', '>', now());
    }
}
