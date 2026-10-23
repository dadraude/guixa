<?php

namespace Guixa\Catalog\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property bool $active
 * @property int $cutoff_days_before
 * @property string $cutoff_time
 */
final class ProducerModel extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'producers';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'cutoff_days_before' => 'integer'];
    }
}
