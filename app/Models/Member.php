<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * The member profile of a user: belongs to at most one household (none while
 * they wait for one to be assigned). Name and email live on the user, the
 * sign-in identity.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $household_id
 * @property string|null $phone
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Household|null $household
 */
#[Fillable(['household_id', 'phone', 'is_active'])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this is an active member who also holds the administrator
     * profile. An inactive member who keeps the profile does not count: they
     * cannot act as administrator until reactivated. The user must be loaded
     * with its administrator relation to avoid a query per member.
     */
    public function isActiveAdministrator(): bool
    {
        return $this->is_active && $this->user->administrator !== null;
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * @return BelongsToMany<Commission, $this>
     */
    public function commissions(): BelongsToMany
    {
        return $this->belongsToMany(Commission::class)->withTimestamps();
    }

    /**
     * The single definition of "takes part in a commission": an active member
     * in at least one commission. Inactive members are in none.
     *
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function activeInCommission(Builder $query): void
    {
        $query->where('is_active', true)->whereHas('commissions');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
