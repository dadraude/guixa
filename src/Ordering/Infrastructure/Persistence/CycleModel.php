<?php

namespace Guixa\Ordering\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $pickup_date
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
}
