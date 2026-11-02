<?php

namespace Guixa\Sales\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BasketAdjustmentModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'basket_adjustments';

    protected $guarded = [];

    /** @return HasMany<BasketAdjustmentLineModel, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(BasketAdjustmentLineModel::class, 'adjustment_id')->orderBy('product_id');
    }
}
