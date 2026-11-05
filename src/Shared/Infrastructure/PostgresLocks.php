<?php

namespace Guixa\Shared\Infrastructure;

use Guixa\Shared\Application\Locks;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PostgresLocks implements Locks
{
    public function acquire(string $name): void
    {
        if ($name !== self::MEMBERSHIP_CHANGES) {
            throw new InvalidArgumentException("Unknown lock [{$name}].");
        }

        // One transaction lock makes membership and household-state reads current before any write.
        DB::select('select pg_advisory_xact_lock(191983, 1)');
    }
}
