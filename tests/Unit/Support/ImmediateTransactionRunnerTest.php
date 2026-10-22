<?php

use Tests\Support\InMemory\ImmediateTransactionRunner;

it('runs afterCommit callbacks once the outermost run finishes', function () {
    $runner = new ImmediateTransactionRunner;
    $log = [];

    $runner->run(function () use ($runner, &$log) {
        $runner->afterCommit(function () use (&$log) {
            $log[] = 'after';
        });
        $runner->run(function () use (&$log) {
            $log[] = 'inner';
        });
        $log[] = 'outer';
    });

    expect($log)->toBe(['inner', 'outer', 'after']);
});

it('drops afterCommit callbacks when the run throws', function () {
    $runner = new ImmediateTransactionRunner;
    $called = false;

    expect(fn () => $runner->run(function () use ($runner, &$called) {
        $runner->afterCommit(function () use (&$called) {
            $called = true;
        });

        throw new RuntimeException('rolled back');
    }))->toThrow(RuntimeException::class);

    expect($called)->toBeFalse();
});
