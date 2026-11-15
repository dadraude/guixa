<?php

namespace Guixa\Sales\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BasketModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'baskets';

    protected $guarded = [];

    /** @return HasMany<BasketLineModel, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(BasketLineModel::class, 'basket_id')->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<BasketAdjustmentModel, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(BasketAdjustmentModel::class, 'basket_id')->orderBy('occurred_at')->orderBy('id');
    }

    /** @return HasMany<BasketCollectionEventModel, $this> */
    public function collectionEvents(): HasMany
    {
        return $this->hasMany(BasketCollectionEventModel::class, 'basket_id')->orderBy('sequence');
    }
}
