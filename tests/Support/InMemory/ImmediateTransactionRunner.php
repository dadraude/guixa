<?php

namespace Tests\Support\InMemory;

use Guixa\Shared\Application\TransactionRunner;
use Throwable;

/** Runs work at once, with the commit semantics of the real runner: afterCommit callbacks wait for the outermost run and are dropped if it throws. */
final class ImmediateTransactionRunner implements TransactionRunner
{
    private int $depth = 0;

    /** @var list<callable> */
    private array $afterCommit = [];

    public function run(callable $work): mixed
    {
        $this->depth++;

        try {
            $result = $work();
        } catch (Throwable $e) {
            $this->depth--;

            if ($this->depth === 0) {
                $this->afterCommit = [];
            }

            throw $e;
        }

        $this->depth--;

        if ($this->depth === 0) {
            $callbacks = $this->afterCommit;
            $this->afterCommit = [];

            foreach ($callbacks as $callback) {
                $callback();
            }
        }

        return $result;
    }

    public function afterCommit(callable $callback): void
    {
        if ($this->depth === 0) {
            $callback();

            return;
        }

        $this->afterCommit[] = $callback;
    }
}
