<?php

use Guixa\Shared\Application\TransactionRunner;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->runner = app(TransactionRunner::class);
    $this->events = [];
});

it('returns what the work returns', function () {
    expect($this->runner->run(fn () => 42))->toBe(42);
});

it('rolls back and rethrows when the work fails', function () {
    DB::statement('create temporary table runner_probe (value int)');

    $attempt = fn () => $this->runner->run(function () {
        DB::table('runner_probe')->insert(['value' => 1]);

        throw new RuntimeException('boom');
    });

    expect($attempt)->toThrow(RuntimeException::class, 'boom')
        ->and(DB::table('runner_probe')->count())->toBe(0);
});

it('runs afterCommit callbacks once the transaction has committed', function () {
    $this->runner->run(function () {
        $this->runner->afterCommit(fn () => $this->events[] = 'after');
        $this->events[] = 'work';
    });

    expect($this->events)->toBe(['work', 'after']);
});

it('never runs afterCommit callbacks of a rolled back transaction', function () {
    $attempt = fn () => $this->runner->run(function () {
        $this->runner->afterCommit(fn () => $this->events[] = 'after');

        throw new RuntimeException('boom');
    });

    expect($attempt)->toThrow(RuntimeException::class)
        ->and($this->events)->toBe([]);
});
