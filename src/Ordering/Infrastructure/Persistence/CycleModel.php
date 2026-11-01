<?php

namespace Guixa\Ordering\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $pickup_date
 * @property ?string $cancelled_at
 * @property ?string $closed_at
 * @property ?string $settled_at
 */
final class CycleModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'cycles';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['pickup_date' => 'string'];
    }

    /** @return HasMany<CycleProducerModel, $this> */
    public function producers(): HasMany
    {
        return $this->hasMany(CycleProducerModel::class, 'cycle_id')->orderBy('producer_id');
    }
}
