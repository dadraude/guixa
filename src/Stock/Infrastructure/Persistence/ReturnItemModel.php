<?php

namespace Guixa\Stock\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property int $amount_cents
 * @property bool $active
 */
final class ReturnItemModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'return_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'active' => 'boolean'];
    }
}
