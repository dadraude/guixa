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
}
