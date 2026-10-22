<?php

namespace Guixa\Stock\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string $unit
 * @property int $price_cents
 * @property bool $variable
 * @property ?string $containers_per_unit
 * @property bool $active
 */
final class StockProductModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'stock_products';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['price_cents' => 'integer', 'variable' => 'boolean', 'active' => 'boolean'];
    }
}
