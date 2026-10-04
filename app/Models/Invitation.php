<?php

namespace App\Models;

use App\Auth\SecretHasher;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The email that lets a new person join a household. Only the hash of its
 * single-use token is stored; expired is derived from the expiry.
 *
 * @property string $id
 * @property string $household_id
 * @property string $email
 * @property string|null $name
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Household $household
 */
#[Fillable(['email', 'name', 'token_hash', 'expires_at'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory, HasUuids;

    public const int DAYS_VALID = 7;

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function status(): InvitationStatus
    {
        return match (true) {
            $this->accepted_at !== null => InvitationStatus::Accepted,
            $this->cancelled_at !== null => InvitationStatus::Cancelled,
            $this->expires_at->isPast() => InvitationStatus::Expired,
            default => InvitationStatus::Pending,
        };
    }

    public function isOpen(): bool
    {
        return $this->accepted_at === null && $this->cancelled_at === null;
    }

    /**
     * Only invitations that are still pending.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('cancelled_at')->where('expires_at', '>', now());
    }

    /**
     * Invitations that can still be managed: pending or expired, not accepted
     * or cancelled.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('cancelled_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeWithToken(Builder $query, string $token): void
    {
        $query->where('token_hash', SecretHasher::hash($token));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
