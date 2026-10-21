<?php

namespace Guixa\Shared\Application;

interface TransactionRunner
{
    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function run(callable $work): mixed;

    /** Runs after the outermost transaction commits; never runs if it rolls back. */
    public function afterCommit(callable $callback): void;
}
