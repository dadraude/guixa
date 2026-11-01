<?php

namespace Guixa\Stock\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $stock_product_id
 * @property string $type
 * @property string $quantity
 * @property string $occurred_at
 * @property string $recorded_by
 * @property ?string $note
 * @property ?string $cycle_id
 * @property ?string $order_id
 * @property ?string $basket_id
 */
final class StockMovementModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'stock_movements';

    protected $guarded = [];
}
