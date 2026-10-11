<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPendingEmailChange;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $remember_token
 * @property int $session_version
 * @property string|null $pending_email
 * @property string|null $pending_email_token_hash
 * @property Carbon|null $pending_email_expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Administrator|null $administrator
 * @property-read Member|null $member
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'pending_email_token_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPendingEmailChange, HasUuids, Notifiable;

    /**
     * Mirrors the column default, so a user just created already carries it.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['session_version' => 0];

    /**
     * Emails are stored in lower case, so lookups and uniqueness ignore case.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => Str::lower($value));
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function pendingEmail(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : Str::lower($value));
    }

    /**
     * @return HasOne<Administrator, $this>
     */
    public function administrator(): HasOne
    {
        return $this->hasOne(Administrator::class);
    }

    /**
     * @return HasOne<Member, $this>
     */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    /**
     * @return HasMany<MagicLink, $this>
     */
    public function magicLinks(): HasMany
    {
        return $this->hasMany(MagicLink::class);
    }

    /**
     * Active or inactive belongs to the person, not to a profile: it is the
     * member profile's flag. A user with no member profile is always active.
     * The owner may have a member profile, but nobody can deactivate it
     * (see MembershipRoleProtection).
     */
    public function isActive(): bool
    {
        return $this->member === null || $this->member->is_active;
    }

    public function hasActiveAdministratorProfile(): bool
    {
        return $this->isActive() && $this->administrator !== null;
    }

    public function hasActiveMemberProfile(): bool
    {
        return $this->member?->is_active === true;
    }

    /**
     * Whether the user may sign in: they are active and have an active
     * profile, or no profile at all. An inactive person gets in with no
     * profile, administrator included; reactivating them brings the
     * administrator profile back untouched.
     */
    public function canSignIn(): bool
    {
        // Relations as properties: loaded once per request and shared with the gates and ActiveProfile.
        // Each request authenticates a fresh User, so a cached relation cannot outlive a deactivation.
        if ($this->administrator === null && $this->member === null) {
            return true;
        }

        return $this->hasActiveAdministratorProfile() || $this->hasActiveMemberProfile();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'pending_email_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
