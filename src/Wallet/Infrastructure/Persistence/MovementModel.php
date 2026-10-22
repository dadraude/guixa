<?php

namespace Guixa\Wallet\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $household_id
 * @property string $type
 * @property int $amount_cents
 * @property string $occurred_at
 * @property string $recorded_by
 * @property string|null $note
 * @property string|null $cycle_id
 */
final class MovementModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'wallet_movements';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }
}
