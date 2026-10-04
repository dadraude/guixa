<?php

namespace App\Models;

use App\Auth\MemberRoles;
use App\Auth\Role;
use Database\Factories\CommissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A group of members in charge of an area of the cooperative's work.
 *
 * @property string $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class Commission extends Model
{
    /** @use HasFactory<CommissionFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsToMany<Member, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class)->withTimestamps();
    }

    /**
     * The roles this commission holds, which all its members share.
     *
     * @return list<Role>
     */
    public function roles(): array
    {
        return app(MemberRoles::class)->forCommissions([$this->id]);
    }

    public function assignRole(Role $role): void
    {
        DB::table('commission_role')->insertOrIgnore([
            'commission_id' => $this->id,
            'role' => $role->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function removeRole(Role $role): void
    {
        DB::table('commission_role')
            ->where('commission_id', $this->id)
            ->where('role', $role->value)
            ->delete();
    }
}
