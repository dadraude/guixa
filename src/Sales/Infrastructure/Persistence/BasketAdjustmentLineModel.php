<?php

namespace Guixa\Sales\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class BasketAdjustmentLineModel extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'basket_adjustment_lines';

    protected $guarded = [];
}
