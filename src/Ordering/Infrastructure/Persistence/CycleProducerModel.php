<?php

namespace Guixa\Ordering\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Read projection for the cycle_producers table, whose key is (cycle_id, producer_id).
 *
 * @property string $producer_id
 * @property bool $participating
 * @property ?string $closes_at
 * @property ?string $sent_at
 * @property ?string $validated_at
 * @property ?string $distributed_at
 */
final class CycleProducerModel extends Model
{
    public $timestamps = false;

    protected $table = 'cycle_producers';
}
