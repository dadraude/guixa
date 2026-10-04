<?php

namespace App\Models;

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
    use HasFactory, HasUuids, Notifiable;

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
     * Whether the user acts as an active administrator, who passes every
     * authorization check.
     */
    public function isActingAsAdministrator(): bool
    {
        return $this->administrator?->is_active === true;
    }

    /**
     * Whether the user is the active owner, the only administrator who
     * manages the other administrators.
     */
    public function isOwner(): bool
    {
        return $this->administrator?->is_owner === true && $this->isActingAsAdministrator();
    }

    /**
     * Whether the user may sign in. An inactive administrator may not; once
     * members exist, one with an active member profile will be allowed to
     * sign in as a member.
     */
    public function canSignIn(): bool
    {
        return ! $this->administrator()->where('is_active', false)->exists();
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
