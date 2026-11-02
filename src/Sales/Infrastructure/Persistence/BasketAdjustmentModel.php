<?php

namespace Guixa\Sales\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class BasketAdjustmentModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'basket_adjustments';

    protected $guarded = [];
}
