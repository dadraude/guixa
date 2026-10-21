<?php

namespace Guixa\Shared\Infrastructure;

use Guixa\Shared\Application\TransactionRunner;
use Illuminate\Support\Facades\DB;

final class DatabaseTransactionRunner implements TransactionRunner
{
    public function run(callable $work): mixed
    {
        return DB::transaction(fn () => $work());
    }

    public function afterCommit(callable $callback): void
    {
        DB::afterCommit($callback);
    }
}
